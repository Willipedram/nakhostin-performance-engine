<?php
/** Cache invalidation facade. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
use Nakhostin\PerformanceEngine\Contracts\CachePurgeInterface;
final class CachePurger implements CachePurgeInterface {
	/** @var PageCacheStoreInterface */ private $store; /** @var URLNormalizer */ private $normalizer; /** @var CacheMetrics */ private $metrics; /** @var QueryPolicy */ private $query_policy;
	public function __construct( PageCacheStoreInterface $store, URLNormalizer $normalizer, CacheMetrics $metrics, ?QueryPolicy $query_policy = null ) { $this->store = $store; $this->normalizer = $normalizer; $this->metrics = $metrics; $this->query_policy = $query_policy ?? new QueryPolicy(); }
	public function purge_all(): bool { $ok = $this->store->flush(); if ( $ok ) { $this->metrics->reset(); } do_action( 'npe/cache/purged', 'all', array() ); return $ok; }
	public function purge_urls( array $urls ): bool { $normalized = array(); foreach ( $urls as $url ) { $result = $this->normalizer->normalize( (string) $url, $this->query_policy ); if ( $result['cacheable'] ) { $normalized[] = $result['url']; } } $this->store->purge_urls( array_values( array_unique( $normalized ) ) ); do_action( 'npe/cache/purged', 'urls', $normalized ); return true; }
	public function purge_tags( array $tags ): bool { $tags = array_values( array_unique( array_filter( array_map( 'sanitize_key', $tags ) ) ) ); $this->store->purge_dependencies( $tags ); do_action( 'npe/cache/purged', 'dependencies', $tags ); return true; }
}
