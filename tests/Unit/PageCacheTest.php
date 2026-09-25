<?php
/** Page cache behavior tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\PageCache;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use PHPUnit\Framework\TestCase;

final class PageCacheTest extends TestCase {
	/** @var string */ private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-page-cache-' . uniqid( '', true ); $GLOBALS['npe_test_fired_actions'] = array(); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $this->directory ); } }
	private function cache( array $allowed = array(), int $ttl = 300, int $stale = 60 ): PageCache { return new PageCache( new FilesystemCacheStore( $this->directory ), new CacheKeyGenerator( new URLNormalizer(), new QueryPolicy( $allowed ) ), new CachePolicy(), new CacheMetrics( $this->directory . '/metrics.json' ), $ttl, $stale ); }
	public function test_miss_then_hit_preserves_safe_response(): void { $cache = $this->cache(); $request = new CacheRequest( 'GET', 'https://EXAMPLE.test/products/' ); $this->assertSame( 'miss', $cache->lookup( $request, 100 )->status() ); $this->assertTrue( $cache->store( $request, '<html>public</html>', array( 'Content-Type' => 'text/html; charset=UTF-8' ), 200, array( 'post-12' ), 'template', false, 100 ) ); $hit = $cache->lookup( $request, 101 ); $this->assertSame( 'hit', $hit->status() ); $this->assertSame( '<html>public</html>', $hit->entry()->content() ); $this->assertContains( array( 'miss' ), $GLOBALS['npe_test_fired_actions']['npe/cache/lookup'] ); $this->assertContains( array( 'hit' ), $GLOBALS['npe_test_fired_actions']['npe/cache/lookup'] ); }
	public function test_private_and_woocommerce_requests_are_bypassed(): void { $cache = $this->cache(); $contexts = array( array( 'is_logged_in' => true ), array( 'is_cart' => true ), array( 'is_checkout' => true ), array( 'is_account' => true ) ); foreach ( $contexts as $context ) { $this->assertSame( 'bypass', $cache->lookup( new CacheRequest( 'GET', 'https://example.test/shop', array(), array(), $context ) )->status() ); } $this->assertSame( 'bypass', $cache->lookup( new CacheRequest( 'GET', 'https://example.test/product', array(), array( 'woocommerce_items_in_cart' => '1' ) ) )->status() ); }
	public function test_query_policy_ignores_tracking_and_rejects_unknown_parameters(): void { $cache = $this->cache( array( 'color' ) ); $allowed = new CacheRequest( 'GET', 'https://example.test/shop?utm_source=x&color=blue' ); $same = new CacheRequest( 'GET', 'https://example.test/shop?color=blue&utm_source=y' ); $this->assertTrue( $cache->store( $allowed, '<html>blue</html>', array(), 200, array(), '', false, 50 ) ); $this->assertSame( 'hit', $cache->lookup( $same, 51 )->status() ); $this->assertSame( 'bypass', $cache->lookup( new CacheRequest( 'GET', 'https://example.test/shop?search=private' ) )->status() ); }
	public function test_expiration_exposes_stale_window_then_misses(): void { $cache = $this->cache( array(), 10, 5 ); $request = new CacheRequest( 'GET', 'https://example.test/' ); $cache->store( $request, '<html>x</html>', array(), 200, array(), '', false, 100 ); $this->assertSame( 'stale', $cache->lookup( $request, 111 )->status() ); $this->assertSame( 'miss', $cache->lookup( $request, 116 )->status() ); }
	public function test_sensitive_responses_are_not_stored(): void { $cache = $this->cache(); $request = new CacheRequest( 'GET', 'https://example.test/' ); $this->assertFalse( $cache->store( $request, '<html>x</html>', array( 'Set-Cookie' => 'token=secret' ) ) ); $this->assertFalse( $cache->store( $request, '<html>x</html>', array( 'Cache-Control' => 'private' ) ) ); }
	public function test_sensitive_form_markup_is_not_stored(): void { $cache = $this->cache(); $request = new CacheRequest( 'GET', 'https://example.test/form' ); $this->assertFalse( $cache->store( $request, '<form><input type="password" name="password"></form>' ) ); $this->assertFalse( $cache->store( $request, '<input type="hidden" name="_wpnonce" value="secret">' ) ); }
	public function test_sensitive_query_is_rejected_even_when_configured(): void { $cache = $this->cache( array( '_wpnonce' ) ); $this->assertSame( 'bypass', $cache->lookup( new CacheRequest( 'GET', 'https://example.test/?_wpnonce=secret' ) )->status() ); }
}
