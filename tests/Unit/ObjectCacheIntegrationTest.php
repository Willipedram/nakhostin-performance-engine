<?php
/** WordPress object-cache integration tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\FilesystemFragmentStore;
use Nakhostin\PerformanceEngine\Cache\FragmentEntry;
use Nakhostin\PerformanceEngine\Cache\FragmentKey;
use Nakhostin\PerformanceEngine\Cache\FragmentStoreFactory;
use Nakhostin\PerformanceEngine\Cache\ObjectCacheDetector;
use Nakhostin\PerformanceEngine\Cache\WordPressObjectCacheFragmentStore;
use PHPUnit\Framework\TestCase;

final class ObjectCacheIntegrationTest extends TestCase {
	/** @var string */ private $directory;

	protected function setUp(): void {
		$this->directory                         = sys_get_temp_dir() . '/npe-object-fallback-' . uniqid( '', true );
		$GLOBALS['npe_test_external_cache']     = false;
		$GLOBALS['npe_test_object_cache']       = array();
		$GLOBALS['npe_test_options']            = array();
	}

	public function test_detector_reports_runtime_cache_as_non_persistent(): void {
		$result = ( new ObjectCacheDetector() )->detect();
		$this->assertTrue( $result['available'] );
		$this->assertFalse( $result['persistent'] );
		$this->assertSame( 'wordpress-runtime', $result['backend'] );
	}

	public function test_factory_falls_back_to_filesystem_without_persistent_cache(): void {
		$store = ( new FragmentStoreFactory( new ObjectCacheDetector(), $this->directory ) )->create();
		$this->assertInstanceOf( FilesystemFragmentStore::class, $store );
	}

	public function test_factory_uses_public_object_cache_api_when_persistent(): void {
		$GLOBALS['npe_test_external_cache'] = true;
		$store = ( new FragmentStoreFactory( new ObjectCacheDetector(), $this->directory ) )->create();
		$this->assertInstanceOf( WordPressObjectCacheFragmentStore::class, $store );
	}

	public function test_object_store_scopes_invalidation_and_never_flushes_globally(): void {
		$store = new WordPressObjectCacheFragmentStore();
		$one   = FragmentEntry::create( FragmentKey::create( 'product-card', 1, array( 'product' => 4 ) ), 'one', 300, array( 'product-4' ) );
		$two   = FragmentEntry::create( FragmentKey::create( 'navigation' ), 'two', 300, array( 'menu-1' ) );
		$this->assertTrue( $store->set( $one, 300 ) );
		$this->assertTrue( $store->set( $two, 300 ) );
		$this->assertSame( 1, $store->invalidate_dependencies( array( 'product-4' ) ) );
		$this->assertNull( $store->get( $one->identifier() ) );
		$this->assertSame( 'two', $store->get( $two->identifier() )->value() );
	}

	public function test_object_cache_lock_uses_atomic_add(): void {
		$first  = new WordPressObjectCacheFragmentStore();
		$second = new WordPressObjectCacheFragmentStore();
		$this->assertTrue( $first->acquire_lock( 'shared', 10 ) );
		$this->assertFalse( $second->acquire_lock( 'shared', 10 ) );
		$first->release_lock( 'shared' );
		$this->assertTrue( $second->acquire_lock( 'shared', 10 ) );
	}
}
