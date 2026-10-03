<?php
/** WordPress and WooCommerce purge-event tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyBuilder;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use Nakhostin\PerformanceEngine\Cache\CacheInvalidationSubscriber;
use Nakhostin\PerformanceEngine\Cache\CacheJobQueue;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupManager;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupTargets;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\FilesystemFragmentStore;
use Nakhostin\PerformanceEngine\Cache\FragmentCache;
use Nakhostin\PerformanceEngine\Cache\SmartPurgeManager;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class CacheInvalidationSubscriberTest extends TestCase {
	/** @var string */ private $directory;
	/** @var CacheJobQueue */ private $queue;
	/** @var CacheInvalidationSubscriber */ private $subscriber;

	protected function setUp(): void {
		$this->directory                     = sys_get_temp_dir() . '/npe-events-' . uniqid( '', true );
		$GLOBALS['npe_test_options']         = array();
		$GLOBALS['npe_test_post_types']      = array( 55 => 'product' );
		$GLOBALS['npe_test_post_terms']      = array( 55 => array( 'product_cat' => array( 7 ) ) );
		$GLOBALS['npe_test_scheduled_event'] = null;
		$graph                               = new CacheDependencyGraph();
		$this->queue                         = new CacheJobQueue( 'npe_event_purge_queue' );
		$manager                            = new SmartPurgeManager( $this->queue, $graph, new CachePurger( new FilesystemCacheStore( $this->directory . '/pages' ), new URLNormalizer(), new CacheMetrics( $this->directory . '/metrics.json' ) ), new FragmentCache( new FilesystemFragmentStore( $this->directory . '/fragments' ) ), new CacheWarmupManager( new CacheJobQueue( 'npe_event_warm_queue' ), new URLNormalizer() ), new BundleIndex(), new CacheWarmupTargets( new Settings() ) );
		$this->subscriber                    = new CacheInvalidationSubscriber( $manager, new CacheDependencyBuilder( $graph ), new ComponentStorage() );
	}

	public function test_product_price_and_stock_events_are_deduplicated(): void {
		$product = new class() { public function get_id(): int { return 55; } };
		$this->subscriber->product_properties_changed( $product, array( 'price', 'stock_status' ) );
		$this->subscriber->product_object_changed( $product );
		$this->assertCount( 1, $this->queue->all() );
		$this->assertSame( 'product_changed', $this->queue->all()[0]['reason'] );
	}

	public function test_irrelevant_product_properties_do_not_purge(): void {
		$product = new class() { public function get_id(): int { return 55; } };
		$this->subscriber->product_properties_changed( $product, array( 'description' ) );
		$this->assertSame( array(), $this->queue->all() );
	}

	public function test_term_component_and_asset_events_enqueue_scoped_jobs(): void {
		$this->subscriber->term_changed( 7, 70, 'product_cat' );
		$this->subscriber->components_changed( array(), array( 'CUSTOM_CARD' => array() ) );
		$this->subscriber->asset_changed( 'gallery-js', array( 'npe-gallery' ) );
		$this->assertCount( 3, $this->queue->all() );
		$this->assertSame( array( 'taxonomy', 'component', 'asset' ), array_column( array_column( $this->queue->all(), 'payload' ), 'level' ) );
	}
}
