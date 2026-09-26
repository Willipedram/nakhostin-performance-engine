<?php
/**
 * Fragment store using only the public WordPress object-cache API.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class WordPressObjectCacheFragmentStore implements FragmentStoreInterface {
	private const GROUP = 'npe_fragments';
	private const INDEX_OPTION = 'npe_fragment_cache_index';

	public function get( string $key ): ?FragmentEntry {
		$found = false;
		$data  = wp_cache_get( $key, self::GROUP, false, $found );
		$entry = $found && is_array( $data ) ? FragmentEntry::from_array( $data ) : null;
		if ( ! $entry || ! hash_equals( $key, $entry->identifier() ) || $entry->is_expired() ) {
			if ( $found || isset( $this->index()[ $key ] ) ) {
				$this->delete( $key );
			}
			$this->increment( 'misses' );
			return null;
		}
		$this->increment( 'hits' );
		$this->increment( 'fragment:' . $key );
		return $entry;
	}

	public function set( FragmentEntry $entry, int $ttl ): bool {
		$stored = wp_cache_set( $entry->identifier(), $entry->to_array(), self::GROUP, max( 1, $ttl ) );
		if ( ! $stored ) {
			return false;
		}
		$updated = $this->mutate_index( static function ( array $index ) use ( $entry ): array {
			$index[ $entry->identifier() ] = array(
			'name'         => $entry->name(),
			'dependencies' => $entry->dependencies(),
			'expires_at'   => $entry->expires_at(),
		);
			return $index;
		} );
		if ( ! $updated ) {
			wp_cache_delete( $entry->identifier(), self::GROUP );
		}
		return $updated;
	}

	public function delete( string $key ): bool {
		$deleted = wp_cache_delete( $key, self::GROUP );
		$updated = $this->mutate_index( static function ( array $index ) use ( $key ): array { unset( $index[ $key ] ); return $index; } );
		return $deleted || $updated;
	}

	public function invalidate_dependencies( array $dependencies ): int {
		$dependencies = array_values( array_unique( array_filter( array_map( 'sanitize_key', $dependencies ) ) ) );
		$count        = 0;
		$this->mutate_index( static function ( array $index ) use ( $dependencies, &$count ): array {
			foreach ( $index as $key => $metadata ) {
				if ( array_intersect( $dependencies, (array) ( $metadata['dependencies'] ?? array() ) ) ) {
					wp_cache_delete( $key, self::GROUP );
					unset( $index[ $key ] );
					++$count;
				}
			}
			return $index;
		} );
		return $count;
	}

	public function flush(): int {
		$count = 0;
		$this->mutate_index( static function ( array $index ) use ( &$count ): array {
			foreach ( array_keys( $index ) as $key ) {
				wp_cache_delete( $key, self::GROUP );
				wp_cache_delete( 'metric:fragment:' . $key, self::GROUP );
				++$count;
			}
			return array();
		} );
		return $count;
	}

	public function acquire_lock( string $key, int $ttl ): bool {
		return wp_cache_add( 'lock:' . $key, time(), self::GROUP, max( 1, $ttl ) );
	}

	public function release_lock( string $key ): void {
		wp_cache_delete( 'lock:' . $key, self::GROUP );
	}

	public function statistics(): array {
		$hits   = (int) wp_cache_get( 'metric:hits', self::GROUP );
		$misses = (int) wp_cache_get( 'metric:misses', self::GROUP );
		return array( 'backend' => $this->backend_name(), 'entries' => count( $this->index() ), 'hits' => $hits, 'misses' => $misses );
	}

	public function top( int $limit = 10 ): array {
		$items = array();
		foreach ( $this->index() as $key => $metadata ) {
			$items[] = array( 'name' => (string) ( $metadata['name'] ?? '' ), 'hits' => (int) wp_cache_get( 'metric:fragment:' . $key, self::GROUP ) );
		}
		usort( $items, static function ( array $left, array $right ): int { return $right['hits'] <=> $left['hits']; } );
		return array_slice( $items, 0, max( 0, $limit ) );
	}

	public function backend_name(): string {
		return 'wordpress-object-cache';
	}

	private function index(): array {
		$index = get_option( self::INDEX_OPTION, array() );
		return is_array( $index ) ? $index : array();
	}

	private function increment( string $metric ): void {
		$key = 'metric:' . $metric;
		if ( false === wp_cache_incr( $key, 1, self::GROUP ) ) {
			wp_cache_add( $key, 1, self::GROUP, 0 );
		}
	}

	private function mutate_index( callable $callback ): bool {
		$locked = false;
		for ( $attempt = 0; $attempt < 20; ++$attempt ) {
			if ( wp_cache_add( 'index-lock', time(), self::GROUP, 5 ) ) {
				$locked = true;
				break;
			}
			usleep( 10000 );
		}
		if ( ! $locked ) {
			return false;
		}

		try {
			$index = $callback( $this->index() );
			if ( ! is_array( $index ) ) {
				return false;
			}
			return update_option( self::INDEX_OPTION, $index, false ) || $index === $this->index();
		} finally {
			wp_cache_delete( 'index-lock', self::GROUP );
		}
	}
}
