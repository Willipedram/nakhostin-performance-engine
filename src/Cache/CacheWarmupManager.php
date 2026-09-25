<?php
/**
 * Same-origin asynchronous warmup queue.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheWarmupManager {
	public const CRON_HOOK = 'npe/cache/process_warmup_queue';

	/** @var CacheJobQueue */ private $queue;
	/** @var URLNormalizer */ private $normalizer;

	public function __construct( CacheJobQueue $queue, URLNormalizer $normalizer ) {
		$this->queue      = $queue;
		$this->normalizer = $normalizer;
	}

	public function register(): void {
		add_action( 'npe/cache/warmup_requested', array( $this, 'enqueue_requested' ) );
	}

	public function enqueue_requested( array $urls ): void {
		$this->enqueue( $urls, 'stale_cache_hit' );
	}

	public function enqueue( array $urls, string $reason ): int {
		$count     = 0;
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		foreach ( array_slice( array_values( array_unique( $urls ) ), 0, 100 ) as $url ) {
			$result = $this->normalizer->normalize( (string) $url, new QueryPolicy() );
			if ( ! $result['cacheable'] || $home_host !== strtolower( (string) wp_parse_url( $result['url'], PHP_URL_HOST ) ) ) {
				continue;
			}
			if ( $this->queue->enqueue( 'warm_url', array( 'url' => $result['url'] ), $reason ) ) {
				++$count;
			}
		}
		if ( $count > 0 && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
		return $count;
	}

	public function queue(): CacheJobQueue {
		return $this->queue;
	}
}
