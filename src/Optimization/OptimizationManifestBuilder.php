<?php
/** Builds immutable per-page optimization and generated-asset manifests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Optimization;

use Nakhostin\PerformanceEngine\Assets\AssetRegistry;
use Nakhostin\PerformanceEngine\Assets\AssetUsagePlanner;
use Nakhostin\PerformanceEngine\CSS\CSSBundleWriter;
use Nakhostin\PerformanceEngine\CSS\CriticalCSSGenerator;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use Nakhostin\PerformanceEngine\CSS\FontManifest;
use Nakhostin\PerformanceEngine\CSS\FontPreloadPlanner;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptBundleWriter;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use Throwable;

final class OptimizationManifestBuilder {
	private $storage; private $critical; private $fonts; private $assets; private $settings; private $css_writer; private $js_writer; private $bundle_url;
	public function __construct( OptimizationManifestStorage $storage, CriticalCSSGenerator $critical, FontPreloadPlanner $fonts, AssetUsagePlanner $assets, Settings $settings, ?CSSBundleWriter $css_writer = null, ?JavaScriptBundleWriter $js_writer = null, string $bundle_url = '' ) { $this->storage = $storage; $this->critical = $critical; $this->fonts = $fonts; $this->assets = $assets; $this->settings = $settings; $this->css_writer = $css_writer; $this->js_writer = $js_writer; $this->bundle_url = trailingslashit( $bundle_url ); }
	public function register(): void { add_action( 'npe/analysis/completed', array( $this, 'build' ), 10, 8 ); }

	public function build( string $url, array $dom, array $components, string $css_source, ?CSSManifest $css, ?JavaScriptManifest $javascript, ?FontManifest $font, array $sources = array() ): bool {
		if ( ! $this->settings->get( 'optimization.enabled', false ) || '' === $url || ! $css || ! $javascript ) { return false; }
		$signature = sanitize_key( (string) ( $dom['dom_signature'] ?? hash( 'sha256', $url ) ) );
		$critical = $this->critical->generate( $css_source, $css, $signature, $this->component_ids( $components ) );
		$registry = new AssetRegistry(); $js = $javascript->to_array();
		foreach ( (array) ( $js['assets'] ?? array() ) as $handle => $asset ) { $registry->register( (string) $handle, 'script', (array) ( $asset['dependencies'] ?? array() ) ); }
		$safe_mode = (bool) $this->settings->get( 'optimization.safe_mode', true );
		$capture_complete = true === ( $sources['capture_complete'] ?? false ) && true === ( $js['capture']['complete'] ?? false );
		$decisions = $this->assets->plan(
			$registry,
			array(
				'required'            => $js['required_by_page'] ?? array(),
				'observed'            => $js['observed'] ?? array(),
				'enqueued'            => $js['enqueued'] ?? array(),
				'component_required'  => $js['component_required'] ?? array(),
				'protected'           => $js['protected'] ?? array(),
				'explicitly_absent'   => $js['explicitly_absent'] ?? array(),
				'capture_complete'    => $capture_complete,
				'dependency_complete' => true === ( $js['dependency_complete'] ?? false ),
			),
			$safe_mode
		);
		foreach ( $decisions as $handle => &$decision ) { $decision['type'] = 'script'; } unset( $decision );
		$preloads = $font ? $this->fonts->plan( $font, 2 ) : array();
		$bundles = $this->bundle_plan( $css, $javascript, $sources, $safe_mode, $capture_complete );
		$summary = array( 'css' => array( 'observed' => (int) ( $css->to_array()['selector_count'] ?? 0 ), 'critical_bytes' => strlen( (string) ( $critical->to_array()['css'] ?? '' ) ), 'bundle_bytes' => (int) ( $bundles['css']['optimized_size'] ?? 0 ), 'bundle_status' => (string) $bundles['css']['status'] ), 'javascript' => array( 'observed' => count( (array) ( $js['observed'] ?? array() ) ), 'deferred' => count( (array) ( $js['deferred'] ?? array() ) ), 'bundle_bytes' => (int) ( $bundles['javascript']['optimized_size'] ?? 0 ), 'bundle_status' => (string) $bundles['javascript']['status'] ), 'fonts' => array( 'preloaded' => count( $preloads ) ) );
		return $this->storage->save( new OptimizationManifest( array( 'page_signature' => $signature, 'generated_at' => time(), 'source_url' => esc_url_raw( $url ), 'dom_signature' => (string) ( $dom['dom_signature'] ?? '' ), 'capture' => array( 'status' => $capture_complete ? 'complete' : (string) ( $sources['capture_status'] ?? 'incomplete' ), 'complete' => $capture_complete ), 'css' => array( 'critical' => $critical->to_array()['css'], 'stylesheets' => array(), 'bundle' => $bundles['css'] ), 'javascript' => array( 'manifest' => $js, 'bundle' => $bundles['javascript'] ), 'fonts' => array( 'preloads' => $preloads ), 'assets' => array( 'decisions' => array_values( $decisions ), 'scope' => 'page-relevant-only', 'global_registration_count' => (int) ( $js['registration_diagnostics']['registered'] ?? 0 ) ), 'components' => $this->component_ids( $components ), 'summary' => $summary ) ) );
	}

	private function bundle_plan( CSSManifest $css, JavaScriptManifest $javascript, array $sources, bool $safe_mode, bool $capture_complete ): array {
		$status = ! $this->settings->get( 'assets.bundle_enabled', false ) ? 'disabled' : ( $safe_mode ? 'safe-mode' : ( ! $capture_complete ? 'incomplete-source-capture' : 'no-eligible-assets' ) );
		$result = array( 'css' => array( 'status' => $status ), 'javascript' => array( 'status' => $status ) );
		if ( 'no-eligible-assets' !== $status ) { return $result; }
		$built = $this->build_bundles( $css, $javascript, $sources );
		foreach ( array( 'css', 'javascript' ) as $type ) {
			if ( ! empty( $built[ $type ] ) ) { $result[ $type ] = isset( $built[ $type ]['status'] ) ? $built[ $type ] : array_merge( $built[ $type ], array( 'status' => 'generated' ) ); }
			elseif ( 'css' === $type && empty( $sources['css_complete'] ) ) { $result[ $type ]['status'] = 'incomplete-source-capture'; }
			elseif ( 'javascript' === $type && empty( $sources['script_contents'] ) ) { $result[ $type ]['status'] = 'source-unavailable'; }
			elseif ( 'javascript' === $type && ( ! empty( $javascript->to_array()['observed'] ) || ! empty( $javascript->to_array()['retained'] ) ) ) { $result[ $type ]['status'] = 'external-or-unsafe-assets'; }
		}
		return $result;
	}

	private function build_bundles( CSSManifest $css, JavaScriptManifest $javascript, array $sources ): array {
		$result = array();
		try { if ( $this->css_writer && ! empty( $sources['css_complete'] ) && ! empty( $sources['css_files'] ) ) { $result['css'] = $this->css_writer->build( (array) $sources['css_files'], $css ); } } catch ( Throwable $error ) { $result['css'] = array( 'status' => 'writer-failed' ); do_action( 'npe/analysis/error', 'css-bundle', get_class( $error ) ); }
		try {
			if ( $this->js_writer && ! empty( $sources['script_assets'] ) && ! empty( $sources['script_contents'] ) ) {
				$data = $javascript->to_array(); $allowed = array();
				foreach ( (array) ( $data['bundles'] ?? array() ) as $plan ) { if ( 'classic' === ( $plan['type'] ?? '' ) ) { $allowed = array_merge( $allowed, (array) ( $plan['handles'] ?? array() ) ); } }
				$allowed = array_fill_keys( array_unique( $allowed ), true );
				// A retained handle must never pull a dequeued dependency back into the page.
				$asset_rows = (array) ( $data['assets'] ?? array() ); $changed = true;
				while ( $changed ) { $changed = false; foreach ( $asset_rows as $handle => $row ) { if ( isset( $allowed[ $handle ] ) ) { continue; } foreach ( (array) ( $row['dependencies'] ?? array() ) as $dependency ) { if ( isset( $allowed[ $dependency ] ) ) { unset( $allowed[ $dependency ] ); $changed = true; } } } }
				$assets = array_values( array_filter( (array) $sources['script_assets'], static function ( $asset ) use ( $allowed ): bool { return $asset instanceof ScriptAsset && isset( $allowed[ $asset->handle() ] ); } ) );
				if ( $assets ) { $bundle = $this->js_writer->build( 'page', $assets, (array) $sources['script_contents'] ); $external = array(); foreach ( $assets as $asset ) { foreach ( $asset->dependencies() as $dependency ) { if ( ! isset( $allowed[ $dependency ] ) ) { $external[] = $dependency; } } } $bundle['external_dependencies'] = array_values( array_unique( $external ) ); $bundle['url'] = $this->bundle_url . 'javascript/' . $bundle['filename']; $result['javascript'] = $bundle; }
			}
		} catch ( Throwable $error ) { $result['javascript'] = array( 'status' => 'writer-failed' ); do_action( 'npe/analysis/error', 'javascript-bundle', get_class( $error ) ); }
		return $result;
	}

	private function component_ids( array $components ): array { $ids = array(); foreach ( $components as $component ) { if ( is_object( $component ) && method_exists( $component, 'id' ) ) { $ids[] = $component->id(); } elseif ( is_object( $component ) && method_exists( $component, 'get_id' ) ) { $ids[] = $component->get_id(); } elseif ( is_object( $component ) && method_exists( $component, 'to_array' ) ) { $data = $component->to_array(); $ids[] = (string) ( $data['id'] ?? '' ); } } return array_values( array_filter( array_unique( $ids ) ) ); }
}
