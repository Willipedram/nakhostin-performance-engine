<?php
/**
 * Read-only WordPress object-cache capability detection.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class ObjectCacheDetector {
	public function detect(): array {
		$persistent = wp_using_ext_object_cache();
		$backend    = 'wordpress-runtime';
		$class_name = '';

		global $wp_object_cache;
		if ( is_object( $wp_object_cache ) ) {
			$class_name = strtolower( get_class( $wp_object_cache ) );
		}
		$redis_available     = class_exists( 'Redis' ) || class_exists( 'RedisCluster' );
		$memcached_available = class_exists( 'Memcached' ) || class_exists( 'Memcache' );
		if ( false !== strpos( $class_name, 'redis' ) ) {
			$backend = 'redis';
		} elseif ( false !== strpos( $class_name, 'memcached' ) ) {
			$backend = 'memcached';
		} elseif ( false !== strpos( $class_name, 'memcache' ) ) {
			$backend = 'memcache';
		} elseif ( $persistent ) {
			$backend = 'persistent-object-cache';
		}
		$healthy = false;
		if ( $persistent && function_exists( 'wp_cache_set' ) && function_exists( 'wp_cache_get' ) && function_exists( 'wp_cache_delete' ) ) {
			$probe = 'health-' . substr( hash( 'sha256', uniqid( '', true ) ), 0, 12 );
			$healthy = wp_cache_set( $probe, $probe, 'npe_health', 30 ) && $probe === wp_cache_get( $probe, 'npe_health' );
			wp_cache_delete( $probe, 'npe_health' );
		}

		return array(
			'available'  => function_exists( 'wp_cache_get' ) && function_exists( 'wp_cache_set' ),
			'persistent' => $persistent,
			'backend'    => $backend,
			'redis'      => $redis_available,
			'memcached'  => $memcached_available,
			'healthy'    => $healthy,
		);
	}
}
