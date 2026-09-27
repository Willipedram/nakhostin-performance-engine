<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\ObjectCacheDetector;
use Nakhostin\PerformanceEngine\Cache\ObjectCachePageCacheStore;
use Nakhostin\PerformanceEngine\Cache\PageCacheStoreFactory;
use PHPUnit\Framework\TestCase;

final class PageCacheStoreFactoryTest extends TestCase {
	protected function tearDown(): void { $GLOBALS['npe_test_external_cache'] = false; }
	public function test_early_mode_always_uses_deterministic_filesystem(): void { $GLOBALS['npe_test_external_cache'] = true; $store = ( new PageCacheStoreFactory( new ObjectCacheDetector(), sys_get_temp_dir() . '/npe-pages' ) )->create( true ); self::assertInstanceOf( FilesystemCacheStore::class, $store ); }
	public function test_application_mode_uses_healthy_persistent_object_cache(): void { $GLOBALS['npe_test_external_cache'] = true; $store = ( new PageCacheStoreFactory( new ObjectCacheDetector(), sys_get_temp_dir() . '/npe-pages' ) )->create( false ); self::assertInstanceOf( ObjectCachePageCacheStore::class, $store ); }
}
