<?php
/**
 * Backend-neutral fragment cache with stampede protection.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Contracts\FragmentCacheInterface;

final class FragmentCache implements FragmentCacheInterface {
	/** @var FragmentStoreInterface */
	private $store;

	public function __construct( FragmentStoreInterface $store ) {
		$this->store = $store;
	}

	public function get( FragmentKey $key ): FragmentResult {
		$entry = $this->store->get( $key->identifier() );
		if ( ! $entry ) {
			return new FragmentResult( 'miss' );
		}

		return new FragmentResult( 'hit', $entry->value(), $entry );
	}

	public function put( FragmentKey $key, $value, int $ttl, array $dependencies = array() ): bool {
		$entry = FragmentEntry::create( $key, $value, $ttl, $dependencies );
		return $entry ? $this->store->set( $entry, max( 1, $ttl ) ) : false;
	}

	public function delete( FragmentKey $key ): bool {
		return $this->store->delete( $key->identifier() );
	}

	public function invalidate_dependencies( array $dependencies ): int {
		return $this->store->invalidate_dependencies( $dependencies );
	}

	public function flush(): int {
		return $this->store->flush();
	}

	public function remember( FragmentKey $key, int $ttl, array $dependencies, callable $generator ): FragmentResult {
		$result = $this->get( $key );
		if ( $result->is_hit() ) {
			return $result;
		}

		if ( ! $this->store->acquire_lock( $key->identifier(), min( 30, max( 1, $ttl ) ) ) ) {
			$result = $this->get( $key );
			return $result->is_hit() ? $result : new FragmentResult( 'locked' );
		}

		try {
			$result = $this->get( $key );
			if ( $result->is_hit() ) {
				return $result;
			}
			$value  = $generator();
			$stored = $this->put( $key, $value, $ttl, $dependencies );
			return new FragmentResult( $stored ? 'generated' : 'uncached', $value );
		} finally {
			$this->store->release_lock( $key->identifier() );
		}
	}

	public function store(): FragmentStoreInterface {
		return $this->store;
	}
}
