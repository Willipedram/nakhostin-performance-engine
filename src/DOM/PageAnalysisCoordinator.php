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
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ObservedScriptResolver;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
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
	/** @var string */ private $capture_url = '';
	/** @var string */ private $html_buffer = '';
	/** @var bool */ private $last_success = false;

	public function __construct( DOMAnalyzer $dom_analyzer, DOMStorageInterface $dom_storage, ComponentRegistry $components, CSSAnalyzer $css_analyzer, CSSStorage $css_storage, StylesheetSourceCollector $stylesheets, ScriptDiscovery $script_discovery, LocalScriptSourceProvider $script_sources, ObservedScriptResolver $script_resolver, JavaScriptAnalyzer $javascript_analyzer, JavaScriptStorage $javascript_storage, Settings $settings ) {
		$this->dom_analyzer = $dom_analyzer; $this->dom_storage = $dom_storage; $this->components = $components; $this->css_analyzer = $css_analyzer; $this->css_storage = $css_storage; $this->stylesheets = $stylesheets; $this->script_discovery = $script_discovery; $this->script_sources = $script_sources; $this->script_resolver = $script_resolver; $this->javascript_analyzer = $javascript_analyzer; $this->javascript_storage = $javascript_storage; $this->settings = $settings;
	}

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_capture' ), -1000 );
	}

	public function request( string $url ): bool {
		$token = strtolower( wp_generate_password( 32, false, false ) );
		set_transient( self::TRANSIENT_PREFIX . $token, array( 'url' => $url ), 5 * MINUTE_IN_SECONDS );
		$response = wp_safe_remote_get(
			add_query_arg( self::QUERY_ARG, rawurlencode( $token ), $url ),
			array( 'timeout' => 30, 'redirection' => 2, 'limit_response_size' => DOMSnapshot::MAX_BYTES, 'user-agent' => 'NPE Page Intelligence/' . NPE_VERSION )
		);
		$result = get_transient( self::RESULT_PREFIX . $token );
		delete_transient( self::TRANSIENT_PREFIX . $token );
		delete_transient( self::RESULT_PREFIX . $token );
		return ! is_wp_error( $response )
			&& 200 === wp_remote_retrieve_response_code( $response )
			&& in_array( $result, array( 'success', 'partial' ), true );
	}

	public function maybe_capture(): void {
		$token = isset( $_GET[ self::QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- One-time high-entropy transient token is the authorization mechanism.
		if ( '' === $token ) { return; }
		$job = get_transient( self::TRANSIENT_PREFIX . $token );
		if ( ! is_array( $job ) || empty( $job['url'] ) ) { return; }
		delete_transient( self::TRANSIENT_PREFIX . $token );
		$this->capture_url = esc_url_raw( (string) $job['url'] );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
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
		try {
			$source_url = '' !== $source_url ? $source_url : $this->capture_url;
			$manifest = $this->dom_analyzer->create_manifest( new DOMSnapshot( $html, array( 'source_url' => $source_url, 'versions' => $this->versions() ) ) );
			$this->dom_storage->save( $manifest );
			$data       = $manifest->to_array();
			$components = array();
			$complete   = true;

			try {
				$components = $this->components->ingest_manifest( $data );
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'components', get_class( $error ) );
			}
			try {
				$css = $this->stylesheets->collect( $data );
				$this->css_storage->save( $this->css_analyzer->analyze_stylesheet( $css['css'], $data ) );
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'css', get_class( $error ) );
			}
			try {
				$assets  = $this->script_discovery->discover();
				$sources = $this->script_sources->load( $assets );
				$options = array( 'page_handles' => $this->script_resolver->resolve( $assets, $data['scripts'] ?? array() ) );
				$context = array( 'is_admin' => false, 'page_type' => $data['page_type'] ?? 'unknown', 'source_hashes' => $sources['hashes'], 'settings_hash' => hash( 'sha256', (string) wp_json_encode( $this->settings->all() ) ), 'versions' => $this->versions() );
				$this->javascript_storage->save( $this->javascript_analyzer->analyze_assets( $assets, $components, $context, $options, $sources['contents'] ) );
			} catch ( Throwable $error ) {
				$complete = false;
				do_action( 'npe/analysis/error', 'javascript', get_class( $error ) );
			}
			$this->last_success = true;
			if ( '' !== $token ) { set_transient( self::RESULT_PREFIX . $token, $complete ? 'success' : 'partial', MINUTE_IN_SECONDS ); }
		} catch ( Throwable $error ) {
			if ( '' !== $token ) { set_transient( self::RESULT_PREFIX . $token, 'failed', MINUTE_IN_SECONDS ); }
			do_action( 'npe/analysis/error', 'dom', get_class( $error ) );
		}
		return $html;
	}

	public function succeeded(): bool {
		return $this->last_success;
	}

	private function versions(): array {
		$theme = wp_get_theme();
		return array( 'wordpress' => get_bloginfo( 'version' ), 'theme' => $theme->get( 'Version' ), 'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '', 'elementor' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '', 'npe' => NPE_VERSION );
	}
}
