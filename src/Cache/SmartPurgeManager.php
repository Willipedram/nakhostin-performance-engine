<?php
/**
 * Dependency-aware purge orchestration.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Contracts\FragmentCacheInterface;

final class SmartPurgeManager {
	public const CRON_HOOK = 'npe/cache/process_purge_queue';

	/** @var CacheJobQueue */ private $queue;
	/** @var CacheDependencyGraph */ private $graph;
	/** @var CachePurger */ private $purger;
	/** @var FragmentCacheInterface */ private $fragments;
	/** @var CacheWarmupManager */ private $warmup;
	/** @var BundleIndex */ private $bundles;
	/** @var CacheWarmupTargets */ private $targets;

	public function __construct( CacheJobQueue $queue, CacheDependencyGraph $graph, CachePurger $purger, FragmentCacheInterface $fragments, CacheWarmupManager $warmup, BundleIndex $bundles, CacheWarmupTargets $targets ) {
		$this->queue     = $queue;
		$this->graph     = $graph;
		$this->purger    = $purger;
		$this->fragments = $fragments;
		$this->warmup    = $warmup;
		$this->bundles   = $bundles;
		$this->targets   = $targets;
	}

	public function request( PurgeRequest $request ): bool {
		$queued = $this->queue->enqueue( 'purge', $request->to_array(), $request->reason() );
		if ( $queued && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 1, self::CRON_HOOK );
		}
		return $queued;
	}

	public function execute( PurgeRequest $request ): array {
		if ( 'full' === $request->level() ) {
			$page_ok   = $this->purger->purge_all();
			$fragments = $this->fragments->flush();
			$bundles   = $this->bundles->flush();
			$this->warmup->enqueue( array_merge( $this->targets->urls(), $request->warm_urls() ), $request->reason() );
			return array( 'level' => 'full', 'page_cache' => $page_ok, 'fragments' => $fragments, 'bundles' => $bundles, 'nodes' => 0 );
		}

		if ( 'url' === $request->level() ) {
			$this->purger->purge_urls( array( $request->identifier() ) );
			$this->warmup->enqueue( array_merge( array( $request->identifier() ), $request->warm_urls() ), $request->reason() );
			return array( 'level' => 'url', 'urls' => 1, 'nodes' => 0 );
		}

		$root = $this->node_for( $request );
		$this->graph->register_node( $root, array( 'tags' => $this->default_tags( $request ) ) );
		$resolved = $this->graph->resolve( array( $root ) );
		if ( $resolved['urls'] ) {
			$this->purger->purge_urls( $resolved['urls'] );
		}
		if ( $resolved['tags'] ) {
			$this->purger->purge_tags( $resolved['tags'] );
			$this->fragments->invalidate_dependencies( $resolved['tags'] );
		}
		foreach ( $resolved['nodes'] as $node ) {
			if ( 0 === strpos( $node, 'bundle:' ) ) {
				$this->bundles->invalidate_bundle( substr( $node, 7 ) );
			}
		}
		$this->warmup->enqueue( array_merge( $resolved['warm_urls'], $request->warm_urls() ), $request->reason() );

		return array(
			'level'     => $request->level(),
			'nodes'     => count( $resolved['nodes'] ),
			'tags'      => count( $resolved['tags'] ),
			'urls'      => count( $resolved['urls'] ),
			'truncated' => $resolved['truncated'],
		);
	}

	public function queue(): CacheJobQueue {
		return $this->queue;
	}

	private function node_for( PurgeRequest $request ): string {
		if ( 'taxonomy' === $request->level() ) {
			return 'taxonomy:' . $request->identifier();
		}
		return $request->level() . ':' . $request->identifier();
	}

	private function default_tags( PurgeRequest $request ): array {
		$identifier = $request->identifier();
		switch ( $request->level() ) {
			case 'page':
				return array( 'post-' . $identifier );
			case 'product':
				return array( 'product-' . $identifier, 'post-' . $identifier );
			case 'component':
				return array( 'component-' . $identifier );
			case 'asset':
				return array( 'asset-' . $identifier );
			case 'page_type':
				return array( 'page-type-' . $identifier );
			case 'taxonomy':
				list( $taxonomy, $term_id ) = array_pad( explode( ':', $identifier, 2 ), 2, '' );
				$taxonomy = 'product_cat' === $taxonomy ? 'product-category' : ( 'product_tag' === $taxonomy ? 'product-tag' : $taxonomy );
				return array( $taxonomy . '-' . $term_id );
		}
		return array();
	}
}
