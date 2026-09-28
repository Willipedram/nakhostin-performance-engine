<?php
/**
 * One-click, one-time frontend capture for DOM, component, CSS, and JavaScript analysis.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\CSSStorage;
use Nakhostin\PerformanceEngine\CSS\StylesheetSourceCollector;
use Nakhostin\PerformanceEngine\CSS\FontAnalyzer;
use Nakhostin\PerformanceEngine\CSS\FontStorage;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ObservedScriptResolver;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use Nakhostin\PerformanceEngine\CSS\FontManifest;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Throwable;

final class PageAnalysisCoordinator {
	public const QUERY_ARG = 'npe_analysis_token';
	private const TRANSIENT_PREFIX = 'npe_analysis_';
	private const RESULT_PREFIX = 'npe_analysis_result_';

	/** @var DOMAnalyzer */ private $dom_analyzer;
	/** @var DOMStorageInterface */ private $dom_storage;
	/** @var ComponentRegistry */ private $components;
	/** @var CSSAnalyzer */ private $css_analyzer;
	/** @var CSSStorage */ private $css_storage;
	/** @var StylesheetSourceCollector */ private $stylesheets;
	/** @var ScriptDiscovery */ private $script_discovery;
	/** @var LocalScriptSourceProvider */ private $script_sources;
	/** @var ObservedScriptResolver */ private $script_resolver;
	/** @var JavaScriptAnalyzer */ private $javascript_analyzer;
	/** @var JavaScriptStorage */ private $javascript_storage;
	/** @var Settings */ private $settings;
	/** @var FontAnalyzer|null */ private $font_analyzer;
	/** @var FontStorage|null */ private $font_storage;
	/** @var string */ private $capture_url = '';
	/** @var string */ private $html_buffer = '';
	/** @var bool */ private $last_success = false;
	/** @var string */ private $last_error = '';

	public function __construct( DOMAnalyzer $dom_analyzer, DOMStorageInterface $dom_storage, ComponentRegistry $components, CSSAnalyzer $css_analyzer, CSSStorage $css_storage, StylesheetSourceCollector $stylesheets, ScriptDiscovery $script_discovery, LocalScriptSourceProvider $script_sources, ObservedScriptResolver $script_resolver, JavaScriptAnalyzer $javascript_analyzer, JavaScriptStorage $javascript_storage, Settings $settings, ?FontAnalyzer $font_analyzer = null, ?FontStorage $font_storage = null ) {
		$this->dom_analyzer = $dom_analyzer; $this->dom_storage = $dom_storage; $this->components = $components; $this->css_analyzer = $css_analyzer; $this->css_storage = $css_storage; $this->stylesheets = $stylesheets; $this->script_discovery = $script_discovery; $this->script_sources = $script_sources; $this->script_resolver = $script_resolver; $this->javascript_analyzer = $javascript_analyzer; $this->javascript_storage = $javascript_storage; $this->settings = $settings;
		$this->font_analyzer = $font_analyzer; $this->font_storage = $font_storage;
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_capture' ), -1000 );
	}

	public function request( string $url ): bool {
		$this->last_success = false;
		$this->last_error = '';
		$token = strtolower( wp_generate_password( 32, false, false ) );
		set_transient( self::TRANSIENT_PREFIX . $token, array( 'url' => $url ), 5 * MINUTE_IN_SECONDS );
		$response = wp_safe_remote_get(
			add_query_arg( array( self::QUERY_ARG => $token ), $url ),
			array(
				'timeout'             => 30,
				'redirection'         => 2,
				'limit_response_size' => DOMSnapshot::MAX_BYTES,
				'user-agent'          => 'NPE Page Intelligence/' . NPE_VERSION,
				'headers'             => array( 'Cache-Control' => 'no-cache', 'X-NPE-Analysis' => '1' ),
			)
		);
		$result = get_transient( self::RESULT_PREFIX . $token );
		delete_transient( self::TRANSIENT_PREFIX . $token );
		delete_transient( self::RESULT_PREFIX . $token );
		if ( is_wp_error( $response ) ) {
			$this->last_error = 'http-' . sanitize_key( $response->get_error_code() ?: 'request-failed' );
			return false;
		}
		$status = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status ) {
			$this->last_error = 'http-status-' . absint( $status );
			return false;
		}
		if ( in_array( $result, array( 'success', 'partial' ), true ) ) {
			return true;
		}

		// A reverse proxy or page cache can serve the analysis URL without running
		// the capture callback. Analyze the bounded response locally instead of
		// turning a valid public page into a permanently failed queue item.
		$html = wp_remote_retrieve_body( $response );
		if ( is_string( $html ) && preg_match( '/<(?:!doctype\s+html|html|body)\b/i', $html ) ) {
			$this->capture( $html, '', $url );
			if ( $this->last_success ) {
				return true;
			}
		}

		if ( '' === $this->last_error ) {
			$this->last_error = 'capture-' . ( is_string( $result ) && '' !== $result ? sanitize_key( $result ) : 'result-missing' );
		}
		return false;
	}

	public function maybe_capture(): void {
		$token = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- One-time high-entropy transient token is the authorization mechanism.
		if ( '' === $token ) { return; }
		$job = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! is_array( $job ) || empty( $job['url'] ) ) { return; }
		delete_transient( self::TRANSIENT_PREFIX . $token );
		$this->capture_url = esc_url_raw( (string) $job['url'] );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		if ( ! defined( 'DONOTCACHEDB' ) ) { define( 'DONOTCACHEDB', true ); }
		do_action( 'litespeed_control_set_nocache', 'NPE DOM analysis' );
		nocache_headers();
		ob_start( function ( string $html, int $phase = 0 ) use ( $token ): string {
			$this->html_buffer .= $html;
			if ( 0 !== ( $phase & PHP_OUTPUT_HANDLER_FINAL ) ) {
				$this->capture( $this->html_buffer, $token );
				$this->html_buffer = '';
			}
			return $html;
		} );
	}

	public function capture( string $html, string $token = '', string $source_url = '' ): string {
		$this->last_success = false;
		$this->last_error = '';
		try {
			$source_url = '' !== $source_url ? $source_url : $this->capture_url;
			$manifest = $this->dom_analyzer->create_manifest( new DOMSnapshot( $html, array( 'source_url' => $source_url, 'versions' => $this->versions() ) ) );
			$this->dom_storage->save( $manifest );
			$data       = $manifest->to_array();
			$components = array();
			$complete   = true;
			$css_source = '';
			$css_manifest = null;
			$javascript_manifest = null;
			$font_manifest = null;
			$document_complete = strlen( $html ) < DOMSnapshot::MAX_BYTES && 1 === preg_match( '/<\/body\s*>\s*<\/html\s*>\s*$/i', trim( $html ) );
			if ( ! $document_complete ) { $complete = false; }
			$analysis_sources = array( 'css_files' => array(), 'css_complete' => false, 'script_assets' => array(), 'script_contents' => array(), 'capture_complete' => false, 'capture_status' => $document_complete ? 'complete' : 'incomplete-document' );

			try {
				$components = $this->components->ingest_manifest( $data );
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'components', get_class( $error ) );
			}
			try {
				$css = $this->stylesheets->collect( $data );
				$analysis_sources['css_files'] = (array) ( $css['files'] ?? array() );
				$analysis_sources['css_complete'] = ! empty( $css['fetched_files'] ) && empty( $css['skipped_files'] ) && (int) $css['fetched_files'] === count( array_unique( (array) ( $data['stylesheets'] ?? array() ) ) );
				$css_source = (string) ( $css['css'] ?? '' );
				$css_manifest = $this->css_analyzer->analyze_stylesheet( $css['css'], $data );
				$this->css_storage->save( $css_manifest );
				if ( null !== $this->font_analyzer && null !== $this->font_storage ) {
					$font_manifest = $this->font_analyzer->analyze( (array) ( $css['files'] ?? array() ), $data, $css_manifest );
					$this->font_storage->save( $font_manifest );
				}
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'css', get_class( $error ) );
			}
			try {
				$assets  = $this->script_discovery->discover();
				$sources = $this->script_sources->load( $assets );
				$analysis_sources['script_assets'] = $assets;
				$analysis_sources['script_contents'] = (array) ( $sources['contents'] ?? array() );
				$page_handles = $this->script_resolver->resolve( $assets, $data['scripts'] ?? array() );
				$options = array( 'page_handles' => $page_handles, 'defer_handles' => $page_handles, 'capture_complete' => $document_complete );
				$context = array( 'is_admin' => false, 'page_type' => $data['page_type'] ?? 'unknown', 'source_hashes' => $sources['hashes'], 'settings_hash' => hash( 'sha256', (string) wp_json_encode( $this->settings->all() ) ), 'versions' => $this->versions() );
				$javascript_manifest = $this->javascript_analyzer->analyze_assets( $assets, $components, $context, $options, $sources['contents'] );
				$this->javascript_storage->save( $javascript_manifest );
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'javascript', get_class( $error ) );
			}
			$analysis_sources['capture_complete'] = $complete && $document_complete && null !== $javascript_manifest;
			$analysis_sources['capture_status'] = $analysis_sources['capture_complete'] ? 'complete' : ( 'complete' === $analysis_sources['capture_status'] ? 'analysis-incomplete' : $analysis_sources['capture_status'] );
			do_action( 'npe/analysis/completed', $source_url, $data, $components, $css_source, $css_manifest, $javascript_manifest, $font_manifest, $analysis_sources );
			$this->last_success = true;
			if ( '' !== $token ) { set_transient( self::RESULT_PREFIX . $token, $complete ? 'success' : 'partial', MINUTE_IN_SECONDS ); }
		} catch ( Throwable $error ) {
			$this->last_error = 'dom-' . sanitize_key( get_class( $error ) );
			if ( '' !== $token ) { set_transient( self::RESULT_PREFIX . $token, 'failed', MINUTE_IN_SECONDS ); }
			do_action( 'npe/analysis/error', 'dom', get_class( $error ) );
		}
		return $html;
	}

	public function succeeded(): bool {
		return $this->last_success;
	}

	public function last_error(): string {
		return $this->last_error;
	}

	private function versions(): array {
		$theme = wp_get_theme();
		return array( 'wordpress' => get_bloginfo( 'version' ), 'theme' => $theme->get( 'Version' ), 'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '', 'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '', 'npe' => NPE_VERSION );
	}
}
