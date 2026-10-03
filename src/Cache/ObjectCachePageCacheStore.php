<?php
/** Persistent WordPress object-cache page store for application mode only. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class ObjectCachePageCacheStore implements PageCacheStoreInterface {
	private const GROUP = 'npe_page_cache'; private const INDEX = 'index';
	public function get( string $key, $default = null ) { $entry = $this->read( $key ); return $entry ? $entry->to_array() : $default; }
	public function set( string $key, $value, int $ttl = 0 ): bool { $entry = $value instanceof CacheEntry ? $value : ( is_array( $value ) ? CacheEntry::from_array( $value ) : null ); return $entry && hash_equals( $key, $entry->key() ) && $this->write( $entry ); }
	public function delete( string $key ): bool { $index = $this->index(); unset( $index[ $key ] ); wp_cache_set( self::INDEX, $index, self::GROUP ); return wp_cache_delete( $key, self::GROUP ); }
	public function has( string $key ): bool { return null !== $this->read( $key ); }
	public function get_name(): string { return 'persistent-object-cache'; }
	public function read( string $key ): ?CacheEntry { $data = wp_cache_get( $key, self::GROUP ); $entry = is_array( $data ) ? CacheEntry::from_array( $data ) : null; return $entry && hash_equals( $key, $entry->key() ) ? $entry : null; }
	public function write( CacheEntry $entry ): bool { $ttl = max( 1, (int) $entry->to_array()['stale_until'] - time() ); if ( ! wp_cache_set( $entry->key(), $entry->to_array(), self::GROUP, $ttl ) ) { return false; } $index = $this->index(); $index[ $entry->key() ] = array( 'url' => $entry->url(), 'dependencies' => $entry->dependencies() ); return wp_cache_set( self::INDEX, array_slice( $index, -10000, null, true ), self::GROUP ); }
	public function flush(): bool { foreach ( array_keys( $this->index() ) as $key ) { wp_cache_delete( $key, self::GROUP ); } return wp_cache_delete( self::INDEX, self::GROUP ); }
	public function purge_urls( array $urls ): int { return $this->purge( static function ( array $meta ) use ( $urls ): bool { return in_array( $meta['url'] ?? '', $urls, true ); } ); }
	public function purge_dependencies( array $dependencies ): int { return $this->purge( static function ( array $meta ) use ( $dependencies ): bool { return (bool) array_intersect( (array) ( $meta['dependencies'] ?? array() ), $dependencies ); } ); }
	public function cached_urls(): array { return array_values( array_unique( array_column( $this->index(), 'url' ) ) ); }
	public function statistics(): array { return array( 'entries' => count( $this->index() ), 'size' => 0, 'backend' => $this->get_name() ); }
	private function index(): array { $index = wp_cache_get( self::INDEX, self::GROUP ); return is_array( $index ) ? $index : array(); }
	private function purge( callable $match ): int { $count = 0; foreach ( $this->index() as $key => $meta ) { if ( $match( (array) $meta ) && $this->delete( (string) $key ) ) { ++$count; } } return $count; }
}
