<?php
/**
 * Read-only environment diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Infrastructure;

use Nakhostin\PerformanceEngine\Diagnostics\CacheHealthCheck;
use Nakhostin\PerformanceEngine\Diagnostics\ObjectCacheHealthCheck;
use Nakhostin\PerformanceEngine\Diagnostics\PHPHealthCheck;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedGuestVaryProbe;
use Nakhostin\PerformanceEngine\Security\SecurityHeaderAnalyzer;

final class Diagnostics {
	/** @var EnvironmentReport|null */ private $environment;
	/** @var CacheHealthCheck|null */ private $cache_health;
	/** @var LiteSpeedGuestVaryProbe|null */ private $guest_vary;
	public function __construct( ?EnvironmentReport $environment = null, ?CacheHealthCheck $cache_health = null, ?LiteSpeedGuestVaryProbe $guest_vary = null ) { $this->environment = $environment; $this->cache_health = $cache_health; $this->guest_vary = $guest_vary; }
	public function collect(): array {
		$results = array(
			'wordpress_version' => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'server_software'   => isset( $_SERVER['SERVER_SOFTWARE'] )
				? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) )
				: __( 'Unknown', 'nakhostin-performance-engine' ),
			'https'             => is_ssl(),
			'multisite'         => is_multisite(),
			'woocommerce'       => class_exists( 'WooCommerce' ),
			'elementor'         => defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' ),
			'litespeed_cache'   => defined( 'LSCWP_V' ) || has_action( 'litespeed_init' ),
			'object_cache'      => (bool) wp_using_ext_object_cache(),
			'redis'             => $this->has_redis(),
		);
		if ( $this->environment ) { $results['detected_server'] = $this->environment->server(); $results['reverse_proxy'] = (string) ( (array) $this->environment->get( 'reverse_proxy', array( 'provider' => 'none' ) ) )['provider']; $results['wp_cache'] = (bool) $this->environment->get( 'wp_cache', false ); $results['advanced_cache'] = (bool) $this->environment->get( 'advanced_cache', false ); $results['object_cache_dropin'] = (bool) $this->environment->get( 'object_cache_dropin', false ); $results['cache_plugin_active'] = (bool) $this->environment->get( 'cache_plugin_active', false ); $results['gzip_capable'] = (bool) $this->environment->get( 'gzip_capable', false ); $results['gzip_active'] = (bool) $this->environment->get( 'gzip_active', false ); $results['brotli_capable'] = (bool) $this->environment->get( 'brotli_capable', false ); $results['brotli_active'] = (bool) $this->environment->get( 'brotli_active', false ); }
		if ( $this->cache_health ) { foreach ( $this->cache_health->run() as $key => $value ) { $results[ 'cache_' . $key ] = $value; } }
		if ( $this->guest_vary ) {
			$probe = $this->guest_vary->last();
			$results['litespeed_guest_vary_state'] = (string) ( $probe['state'] ?? 'not_tested' );
			$results['litespeed_guest_vary_http_status'] = (int) ( $probe['http_status'] ?? 0 );
			$results['litespeed_guest_vary_duration_ms'] = (float) ( $probe['duration_ms'] ?? 0 );
			$results['litespeed_guest_vary_message'] = (string) ( $probe['message'] ?? __( 'Not tested', 'nakhostin-performance-engine' ) );
		}
		return $results;
	}

	/**
	 * Build the complete, sanitized-support-report source data.
	 *
	 * Empty report sections are misleading, so every advertised section contains
	 * either measured facts or an explicit collection state.
	 */
	public function report(): array {
		$server      = $this->collect();
		$settings    = ( new Settings() )->all();
		$cache       = $this->cache_health ? $this->cache_health->run() : array( 'status' => 'not_collected' );
		$guest_vary  = $this->guest_vary ? $this->guest_vary->last() : array();
		$headers     = $this->response_headers();
		$performance = $settings['performance'] ?? array();

		return array(
			'server'           => $server,
			'php'              => ( new PHPHealthCheck() )->run(),
			'cache'            => $cache,
			'object_cache'     => ( new ObjectCacheHealthCheck() )->run(),
			'performance'      => array(
				'enabled'           => (bool) ( $performance['enabled'] ?? false ),
				'hook_profiling'    => (bool) ( $performance['hook_profiling'] ?? false ),
				'sample_rate'       => (int) ( $performance['sample_rate'] ?? 0 ),
				'memory_usage_bytes' => memory_get_usage( true ),
				'peak_memory_bytes'  => memory_get_peak_usage( true ),
			),
			'optimization'     => array(
				'enabled'              => (bool) ( $settings['optimization']['enabled'] ?? false ),
				'safe_mode'            => (bool) ( $settings['optimization']['safe_mode'] ?? true ),
				'bundle_enabled'       => (bool) ( $settings['assets']['bundle_enabled'] ?? false ),
				'unload_enabled'       => (bool) ( $settings['assets']['unload_enabled'] ?? false ),
				'critical_css_enabled' => (bool) ( $settings['css']['critical_css_enabled'] ?? false ),
				'javascript_defer'     => (bool) ( $settings['javascript']['defer_enabled'] ?? false ),
				'javascript_delay'     => (bool) ( $settings['javascript']['delay_enabled'] ?? false ),
				'font_preload'         => (bool) ( $settings['fonts']['preload_enabled'] ?? false ),
			),
			'integrations'     => array(
				'woocommerce'               => (bool) $server['woocommerce'],
				'elementor'                 => (bool) $server['elementor'],
				'litespeed_server'          => in_array( (string) ( $server['detected_server'] ?? '' ), array( 'litespeed', 'openlitespeed' ), true ),
				'litespeed_cache_plugin'    => (bool) $server['litespeed_cache'],
				'litespeed_guest_vary_state' => (string) ( $guest_vary['state'] ?? 'not_tested' ),
			),
			'security_headers' => array_merge(
				array( 'collection' => empty( $headers ) ? 'not_available_in_admin_response' : 'current_admin_response' ),
				( new SecurityHeaderAnalyzer() )->analyze( $headers, (bool) $server['https'] )->to_array()
			),
		);
	}

	/** @return array<string,string> */
	private function response_headers(): array {
		$headers = array();
		foreach ( headers_list() as $line ) {
			if ( false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $name, $value ) = explode( ':', $line, 2 );
			$headers[ trim( $name ) ] = trim( $value );
		}
		return $headers;
	}

	private function has_redis(): bool {
		if ( class_exists( 'Redis' ) || defined( 'WP_REDIS_CLIENT' ) || defined( 'WP_REDIS_HOST' ) ) {
			return true;
		}

		global $wp_object_cache;

		return is_object( $wp_object_cache ) && false !== stripos( get_class( $wp_object_cache ), 'redis' );
	}
}
