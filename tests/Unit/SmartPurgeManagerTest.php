<?php
/** Dependency-aware purge orchestration tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use Nakhostin\PerformanceEngine\Cache\CacheEntry;
use Nakhostin\PerformanceEngine\Cache\CacheJobQueue;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupManager;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupTargets;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\FilesystemFragmentStore;
use Nakhostin\PerformanceEngine\Cache\FragmentCache;
use Nakhostin\PerformanceEngine\Cache\FragmentKey;
use Nakhostin\PerformanceEngine\Cache\PurgeRequest;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\SmartPurgeManager;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class SmartPurgeManagerTest extends TestCase {
	/** @var string */ private $directory;

	protected function setUp(): void {
		$this->directory                     = sys_get_temp_dir() . '/npe-smart-purge-' . uniqid( '', true );
		$GLOBALS['npe_test_options']         = array();
		$GLOBALS['npe_test_scheduled_event'] = null;
	}

	protected function tearDown(): void {
		if ( ! is_dir( $this->directory ) ) { return; }
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
		rmdir( $this->directory );
	}

	public function test_product_purge_only_invalidates_transitive_dependencies(): void {
		$page_store = new FilesystemCacheStore( $this->directory . '/pages' );
		$fragments  = new FragmentCache( new FilesystemFragmentStore( $this->directory . '/fragments' ) );
		$graph      = new CacheDependencyGraph();
		$graph->register_node( 'product:123', array( 'tags' => array( 'product-123' ) ) );
		$graph->register_node( 'page:123', array( 'tags' => array( 'post-123' ), 'urls' => array( 'https://example.test/product/123/' ), 'warm_urls' => array( 'https://example.test/product/123/' ) ) );
		$graph->register_node( 'taxonomy:product_cat:7', array( 'tags' => array( 'product-category-7' ), 'urls' => array( 'https://example.test/category/7/' ), 'warm_urls' => array( 'https://example.test/category/7/' ) ) );
		$graph->register_node( 'page_type:shop', array( 'tags' => array( 'page-type-shop' ), 'urls' => array( 'https://example.test/product/' ), 'warm_urls' => array( 'https://example.test/product/' ) ) );
		$graph->connect( 'product:123', 'page:123' );
		$graph->connect( 'product:123', 'taxonomy:product_cat:7' );
		$graph->connect( 'product:123', 'page_type:shop' );

		$page_store->write( CacheEntry::create( 'product', 'https://example.test/product/123/', 'product', array(), 300, 0, array( 'product-123' ) ) );
		$page_store->write( CacheEntry::create( 'category', 'https://example.test/category/7/', 'category', array(), 300, 0, array( 'product-category-7' ) ) );
		$page_store->write( CacheEntry::create( 'shop', 'https://example.test/product/', 'shop', array(), 300, 0, array( 'page-type-shop' ) ) );
		$page_store->write( CacheEntry::create( 'unrelated', 'https://example.test/about/', 'about', array(), 300, 0, array( 'post-99' ) ) );
		$fragment = FragmentKey::create( 'product-card', 1, array( 'product' => 123 ) );
		$fragments->put( $fragment, 'card', 300, array( 'product-123' ) );

		$warm_queue = new CacheJobQueue( 'npe_test_warm_queue' );
		$manager    = new SmartPurgeManager(
			new CacheJobQueue( 'npe_test_purge_queue' ),
			$graph,
			new CachePurger( $page_store, new URLNormalizer(), new CacheMetrics( $this->directory . '/metrics.json' ), new QueryPolicy() ),
			$fragments,
			new CacheWarmupManager( $warm_queue, new URLNormalizer() ),
			new BundleIndex(),
			new CacheWarmupTargets( new Settings() )
		);

		$result = $manager->execute( PurgeRequest::create( 'product', 123, 'product_saved' ) );
		$this->assertSame( 4, $result['nodes'] );
		$this->assertNull( $page_store->read( 'product' ) );
		$this->assertNull( $page_store->read( 'category' ) );
		$this->assertNull( $page_store->read( 'shop' ) );
		$this->assertNotNull( $page_store->read( 'unrelated' ) );
		$this->assertSame( 'miss', $fragments->get( $fragment )->status() );
		$this->assertCount( 3, $warm_queue->all() );
	}

	public function test_requests_are_queued_and_not_executed_in_publishing_request(): void {
		$page_store = new FilesystemCacheStore( $this->directory . '/pages' );
		$queue      = new CacheJobQueue( 'npe_test_purge_queue' );
		$manager    = new SmartPurgeManager( $queue, new CacheDependencyGraph(), new CachePurger( $page_store, new URLNormalizer(), new CacheMetrics( $this->directory . '/metrics.json' ) ), new FragmentCache( new FilesystemFragmentStore( $this->directory . '/fragments' ) ), new CacheWarmupManager( new CacheJobQueue( 'npe_test_warm_queue' ), new URLNormalizer() ), new BundleIndex(), new CacheWarmupTargets( new Settings() ) );
		$page_store->write( CacheEntry::create( 'page', 'https://example.test/post/4/', 'page', array(), 300, 0, array( 'post-4' ) ) );

		$this->assertTrue( $manager->request( PurgeRequest::create( 'page', 4, 'post_saved' ) ) );
		$this->assertNotNull( $page_store->read( 'page' ) );
		$this->assertSame( 1, $queue->counts()['pending'] );
	}

	public function test_asset_purge_invalidates_only_dependent_bundle_index(): void {
		$graph   = new CacheDependencyGraph();
		$bundles = new BundleIndex();
		$bundles->remember( array( 'bundle_id' => 'gallery-bundle', 'component_ids' => array(), 'signature' => 'one', 'css_dependencies' => array(), 'js_dependencies' => array( 'gallery-js' ) ) );
		$bundles->remember( array( 'bundle_id' => 'checkout-bundle', 'component_ids' => array(), 'signature' => 'two', 'css_dependencies' => array(), 'js_dependencies' => array( 'checkout-js' ) ) );
		$graph->register_node( 'asset:gallery-js', array( 'tags' => array( 'asset-gallery-js' ) ) );
		$graph->register_node( 'bundle:gallery-bundle', array( 'tags' => array( 'bundle-gallery-bundle' ) ) );
		$graph->connect( 'asset:gallery-js', 'bundle:gallery-bundle' );
		$manager = new SmartPurgeManager( new CacheJobQueue( 'npe_test_purge_queue' ), $graph, new CachePurger( new FilesystemCacheStore( $this->directory . '/pages' ), new URLNormalizer(), new CacheMetrics( $this->directory . '/metrics.json' ) ), new FragmentCache( new FilesystemFragmentStore( $this->directory . '/fragments' ) ), new CacheWarmupManager( new CacheJobQueue( 'npe_test_warm_queue' ), new URLNormalizer() ), $bundles, new CacheWarmupTargets( new Settings() ) );

		$manager->execute( PurgeRequest::create( 'asset', 'gallery-js', 'asset_changed' ) );
		$this->assertArrayNotHasKey( 'gallery-bundle', $bundles->all() );
		$this->assertArrayHasKey( 'checkout-bundle', $bundles->all() );
	}
}
