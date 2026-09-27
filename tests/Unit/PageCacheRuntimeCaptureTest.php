<?php
/** Full-page output-buffer regression tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheHeaderManager;
use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\PageCache;
use Nakhostin\PerformanceEngine\Cache\PageCacheRuntime;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PageCacheRuntimeCaptureTest extends TestCase {
	/** @var string */ private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-runtime-' . uniqid( '', true ); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $this->directory ); } }

	public function test_flushed_chunks_are_cached_only_once_as_complete_document(): void {
		$store = new FilesystemCacheStore( $this->directory );
		$cache = new PageCache( $store, new CacheKeyGenerator( new URLNormalizer(), new QueryPolicy() ), new CachePolicy(), new CacheMetrics( $this->directory . '/metrics.json' ) );
		$runtime = new PageCacheRuntime( $cache, new CacheRequestFactory(), new CacheHeaderManager() );
		$request = new CacheRequest( 'GET', 'https://example.test/new-page/' );
		$property = new ReflectionProperty( PageCacheRuntime::class, 'request' );
		$property->setAccessible( true );
		$property->setValue( $runtime, $request );

		$this->assertSame( '<html><body>first', $runtime->capture( '<html><body>first', 0 ) );
		$this->assertSame( 'miss', $cache->lookup( $request )->status() );
		$this->assertSame( ' second</body></html>', $runtime->capture( ' second</body></html>', PHP_OUTPUT_HANDLER_FINAL ) );
		$hit = $cache->lookup( $request );
		$this->assertSame( 'hit', $hit->status() );
		$this->assertSame( '<html><body>first second</body></html>', $hit->entry()->content() );
	}
}
