<?php
/**
 * Read-only environment diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Infrastructure;

use Nakhostin\PerformanceEngine\Diagnostics\CacheHealthCheck;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedGuestVaryProbe;

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
			'object_cache'      => wp_using_ext_object_cache(),
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

	private function has_redis(): bool {
		if ( class_exists( 'Redis' ) || defined( 'WP_REDIS_CLIENT' ) || defined( 'WP_REDIS_HOST' ) ) {
			return true;
		}

		global $wp_object_cache;

		return is_object( $wp_object_cache ) && false !== stripos( get_class( $wp_object_cache ), 'redis' );
	}
}
