<?php
/**
 * Read-only environment diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Infrastructure;

final class Diagnostics {
	public function collect(): array {
		return array(
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
	}

	private function has_redis(): bool {
		if ( class_exists( 'Redis' ) || defined( 'WP_REDIS_CLIENT' ) || defined( 'WP_REDIS_HOST' ) ) {
			return true;
		}

		global $wp_object_cache;

		return is_object( $wp_object_cache ) && false !== stripos( get_class( $wp_object_cache ), 'redis' );
	}
}
