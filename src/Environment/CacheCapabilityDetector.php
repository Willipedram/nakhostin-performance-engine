<?php
/** Detects WordPress and object-cache capabilities. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;

final class CacheCapabilityDetector {
	public function detect( string $content_directory, array $dropin ): array {
		global $wp_object_cache;
		$class = is_object( $wp_object_cache ) ? strtolower( get_class( $wp_object_cache ) ) : '';
		$persistent = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
		$redis_available = class_exists( 'Redis' ) || class_exists( 'Predis\\Client' ) || defined( 'WP_REDIS_HOST' );
		$memcached_available = class_exists( 'Memcached' ) || class_exists( 'Memcache' );
		$active_plugins = function_exists( 'get_option' ) ? (array) get_option( 'active_plugins', array() ) : array();
		$known_page_caches = array( 'litespeed-cache/litespeed-cache.php', 'wp-rocket/wp-rocket.php', 'w3-total-cache/w3-total-cache.php', 'wp-super-cache/wp-cache.php', 'cache-enabler/cache-enabler.php' );
		$detected_plugins = array_values( array_intersect( $known_page_caches, $active_plugins ) );
		return array(
			'wp_cache' => defined( 'WP_CACHE' ) && WP_CACHE,
			'advanced_cache' => ! empty( $dropin['exists'] ),
			'object_cache_dropin' => is_file( rtrim( $content_directory, '/\\' ) . '/object-cache.php' ),
			'persistent_object_cache' => (bool) $persistent,
			'object_cache_runtime_only' => ! $persistent,
			'redis_available' => $redis_available,
			'redis_active' => $persistent && false !== strpos( $class, 'redis' ),
			'memcached_available' => $memcached_available,
			'memcached_active' => $persistent && false !== strpos( $class, 'memcache' ),
			'cache_plugin_active' => ! empty( $detected_plugins ),
			'cache_plugins' => $detected_plugins,
		);
	}
}
