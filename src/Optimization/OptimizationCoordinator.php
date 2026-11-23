<?php
namespace Nakhostin\PerformanceEngine\Optimization;
use Nakhostin\PerformanceEngine\Assets\AssetRuntimeOptimizer;
use Nakhostin\PerformanceEngine\CSS\CSSRuntimeOptimizer;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptRuntimeOptimizer;

final class OptimizationCoordinator {
	private $settings; private $storage; private $owners; private $policy; private $css; private $javascript; private $assets; private $context; private $manifest; private $manifest_loaded = false;
	public function __construct( Settings $settings, OptimizationManifestStorage $storage, OptimizationOwnershipResolver $owners, OptimizationPolicy $policy, CSSRuntimeOptimizer $css, JavaScriptRuntimeOptimizer $javascript, AssetRuntimeOptimizer $assets ) { $this->settings = $settings; $this->storage = $storage; $this->owners = $owners; $this->policy = $policy; $this->css = $css; $this->javascript = $javascript; $this->assets = $assets; }
	public function register(): void { add_filter( 'npe/css/optimization_enabled', array( $this, 'allow_css' ), 5 ); add_filter( 'npe/javascript/optimization_enabled', array( $this, 'allow_javascript' ), 5 ); add_filter( 'npe/fonts/optimization_enabled', array( $this, 'allow_fonts' ), 5 ); if ( ! $this->settings->get( 'optimization.enabled', false ) ) { return; } add_action( 'wp_enqueue_scripts', array( $this, 'apply_assets' ), 999 ); add_action( 'wp_head', array( $this, 'render_head' ), 2 ); if ( $this->settings->get( 'fonts.preload_enabled', false ) ) { add_filter( 'wp_preload_resources', array( $this, 'preload_fonts' ), 30 ); } }
	public function allow_css( $enabled ): bool { return (bool) $enabled && ! in_array( $this->owners->owners( array( 'css' ) )['css'], array( 'external', 'litespeed' ), true ); }
	public function allow_javascript( $enabled ): bool { return (bool) $enabled && ! in_array( $this->owners->owners( array( 'javascript' ) )['javascript'], array( 'external', 'litespeed' ), true ); }
	public function allow_fonts( $enabled ): bool { return (bool) $enabled && ! in_array( $this->owners->owners( array( 'fonts' ) )['fonts'], array( 'external', 'litespeed' ), true ); }
	public function context(): OptimizationContext { if ( $this->context ) { return $this->context; } $type = function_exists( 'is_cart' ) && is_cart() ? 'cart' : ( function_exists( 'is_checkout' ) && is_checkout() ? 'checkout' : ( function_exists( 'is_account_page' ) && is_account_page() ? 'account' : ( function_exists( 'is_product' ) && is_product() ? 'product' : ( function_exists( 'is_shop' ) && is_shop() ? 'shop' : 'page' ) ) ) ); $this->context = new OptimizationContext( array( 'url' => $this->url(), 'page_type' => $type, 'logged_in' => is_user_logged_in(), 'admin' => is_admin(), 'providers' => $this->owners->owners( array( 'css', 'javascript', 'fonts', 'assets' ) ) ) ); return $this->context; }
	public function manifest(): ?OptimizationManifest { if ( ! $this->manifest_loaded ) { $this->manifest = $this->storage->for_url( $this->context()->get( 'url' ) ); $this->manifest_loaded = true; } return $this->manifest; }
	public function apply_assets(): void { $context = $this->context(); if ( ! $this->settings->get( 'optimization.enabled', false ) || $context->is_protected() ) { return; } $manifest = $this->manifest(); $safe = (bool) $this->settings->get( 'optimization.safe_mode', true ); if ( ! $safe && $manifest && ! $manifest->is_stale() && true === ( $manifest->section( 'capture' )['complete'] ?? false ) && $this->settings->get( 'assets.bundle_enabled', false ) ) { $this->apply_bundles( $context, $manifest ); } if ( $this->policy->allows( $context, $manifest, 'css', true )['allowed'] ) { $this->css->apply( $manifest->section( 'css' ), $safe || ! $this->settings->get( 'css.unused_css_enabled', false ) ); } if ( $this->policy->allows( $context, $manifest, 'javascript', (bool) $this->settings->get( 'javascript.defer_enabled', false ) )['allowed'] ) { $js = $manifest->section( 'javascript' ); if ( ! empty( $js['manifest'] ) ) { $this->javascript->apply( new JavaScriptManifest( $js['manifest'] ), $context->to_array(), true, (bool) $this->settings->get( 'assets.unload_enabled', false ) && ! $safe ); } } if ( $this->policy->allows( $context, $manifest, 'assets', (bool) $this->settings->get( 'assets.unload_enabled', false ) )['allowed'] && ! $safe ) { $this->assets->apply( $manifest->section( 'assets' )['decisions'] ?? array() ); } }
	public function render_head(): void {
		if ( $this->settings->get( 'optimization.safe_mode', true ) || ! $this->settings->get( 'css.critical_css_enabled', false ) ) {
			return;
		}
		$context = $this->context();
		if ( $context->is_protected() ) {
			return;
		}
		$manifest = $this->manifest();
		if ( $manifest && $this->policy->allows( $context, $manifest, 'css', true )['allowed'] ) {
			$this->css->inline( (string) ( $manifest->section( 'css' )['critical'] ?? '' ) );
		}
	}

	public function preload_fonts( array $resources ): array {
		$context = $this->context();
		if ( $context->is_protected() ) {
			return $resources;
		}
		$manifest = $this->manifest();
		if ( ! $manifest || ! $this->policy->allows( $context, $manifest, 'fonts', true )['allowed'] ) {
			return $resources;
		}
		foreach ( array_slice( (array) ( $manifest->section( 'fonts' )['preloads'] ?? array() ), 0, 2 ) as $font ) {
			$url = esc_url_raw( (string) ( $font['url'] ?? '' ), array( 'http', 'https' ) );
			if ( '' !== $url ) {
				$resources[] = array( 'href' => $url, 'as' => 'font', 'type' => $font['type'] ?? 'font/woff2', 'crossorigin' => 'anonymous' );
			}
		}
		return $resources;
	}

	private function apply_bundles( OptimizationContext $context, OptimizationManifest $manifest ): void {
		global $wp_styles, $wp_scripts;
		$guard = new BundleGuard();
		$directory = BundleLocation::directory() . '/';
		$url = BundleLocation::url() . '/';
		foreach ( array( 'css' => $wp_styles, 'javascript' => $wp_scripts ) as $type => $manager ) {
			if ( ! $this->policy->allows( $context, $manifest, $type, true )['allowed'] ) {
				continue;
			}
			$bundle = (array) ( $manifest->section( $type )['bundle'] ?? array() );
			if ( empty( $bundle['source_urls'] ) || ! $guard->available( $bundle, $type, $directory . $type, $url . $type ) || ! is_object( $manager ) ) {
				continue;
			}
			$handles = 'css' === $type
				? $this->stylesheet_handles( $guard, $manager, $bundle['source_urls'] )
				: array_values( (array) ( $bundle['handles'] ?? array() ) );
			if ( ! $guard->can_replace( $manager, $handles, $type ) ) {
				continue;
			}
			if ( 'css' === $type ) {
				wp_enqueue_style( 'npe-page-bundle', $bundle['url'], array(), $bundle['content_hash'] );
				foreach ( $handles as $handle ) {
					wp_dequeue_style( $handle );
				}
			} else {
				foreach ( $handles as $handle ) {
					$registered = $manager->registered[ $handle ];
					if ( $guard->source( $registered->src, $manager->base_url ) !== $guard->source( (string) ( $bundle['source_urls'][ $handle ] ?? '' ), $manager->base_url ) || (string) $registered->ver !== ( $bundle['source_versions'][ $handle ] ?? null ) ) {
						continue 2;
					}
				}
				wp_enqueue_script( 'npe-page-bundle', $bundle['url'], (array) ( $bundle['external_dependencies'] ?? array() ), $bundle['content_hash'], true );
				foreach ( $handles as $handle ) {
					wp_dequeue_script( $handle );
				}
			}
			// The next request must recheck the generated file and the live queue.
			add_filter( 'npe/cache/capture_response', '__return_false' );
		}
	}

	private function stylesheet_handles( BundleGuard $guard, $manager, array $sources ): array {
		$handles = array();
		$matched = array();
		foreach ( $manager->queue as $handle ) {
			$asset = $manager->registered[ $handle ] ?? null;
			if ( ! $asset || ! is_string( $asset->src ) ) {
				continue;
			}
			$source = $guard->source( $asset->src, $manager->base_url );
			$version = false === $asset->ver ? $manager->default_version : $asset->ver;
			if ( $version ) {
				$source = add_query_arg( 'ver', $version, $source );
			}
			if ( in_array( $source, $sources, true ) ) {
				$handles[] = $handle;
				$matched[] = $source;
			}
		}
		return array_values( $sources ) === $matched ? $handles : array();
	}

	private function url(): string { $host = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^A-Za-z0-9.\-:\[\]]/', '', wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ); $uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; return ( is_ssl() ? 'https://' : 'http://' ) . $host . '/' . ltrim( strtok( $uri, '?' ) ?: '/', '/' ); }
}
