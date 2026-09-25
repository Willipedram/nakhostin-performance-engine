<?php
/** Application-level full-page cache coordinator. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;

final class PageCache {
	/** @var PageCacheStoreInterface */ private $store; /** @var CacheKeyGenerator */ private $keys; /** @var CachePolicy */ private $policy; /** @var CacheMetrics */ private $metrics; /** @var int */ private $ttl; /** @var int */ private $stale_ttl;
	public function __construct( PageCacheStoreInterface $store, CacheKeyGenerator $keys, CachePolicy $policy, CacheMetrics $metrics, int $ttl = 300, int $stale_ttl = 60 ) { $this->store = $store; $this->keys = $keys; $this->policy = $policy; $this->metrics = $metrics; $this->ttl = max( 1, $ttl ); $this->stale_ttl = max( 0, $stale_ttl ); }
	public function lookup( CacheRequest $request, ?int $now = null ): CacheLookup {
		$decision = $this->policy->classify_request( $request ); if ( ! $decision->is_cacheable() ) { $this->metrics->record( 'bypasses' ); do_action( 'npe/cache/lookup', 'bypass' ); return new CacheLookup( 'bypass', $decision->reason() ); }
		$key = $this->keys->generate( $request ); if ( ! $key['cacheable'] ) { $this->metrics->record( 'bypasses' ); do_action( 'npe/cache/lookup', 'bypass' ); return new CacheLookup( 'bypass', 'query_parameter', null, $key['key'], $key['url'] ); }
		$entry = $this->store->read( $key['key'] ); if ( ! $entry ) { $this->metrics->record( 'misses' ); do_action( 'npe/cache/lookup', 'miss' ); return new CacheLookup( 'miss', 'not_found', null, $key['key'], $key['url'] ); }
		if ( $entry->is_fresh( $now ) ) { $this->metrics->record( 'hits' ); do_action( 'npe/cache/lookup', 'hit' ); return new CacheLookup( 'hit', 'fresh', $entry, $key['key'], $key['url'] ); }
		if ( $entry->is_stale( $now ) ) { $this->metrics->record( 'stale_hits' ); do_action( 'npe/cache/lookup', 'stale' ); do_action( 'npe/cache/warmup_requested', array( $entry->url() ) ); return new CacheLookup( 'stale', 'expired', $entry, $key['key'], $key['url'] ); }
		$this->store->delete( $key['key'] ); $this->metrics->record( 'misses' ); do_action( 'npe/cache/lookup', 'miss' ); return new CacheLookup( 'miss', 'expired', null, $key['key'], $key['url'] );
	}
	public function store( CacheRequest $request, string $content, array $headers = array(), int $status = 200, array $dependencies = array(), string $signature = '', bool $personalized = false, ?int $now = null ): bool {
		if ( ! $this->policy->classify_request( $request )->is_cacheable() || ! $this->policy->classify_response( $status, $headers, $content, $personalized )->is_cacheable() ) { return false; }
		$key = $this->keys->generate( $request ); if ( ! $key['cacheable'] ) { return false; }
		return $this->store->write( CacheEntry::create( $key['key'], $key['url'], $content, $headers, $this->ttl, $this->stale_ttl, $dependencies, $signature, $now ) );
	}
}
