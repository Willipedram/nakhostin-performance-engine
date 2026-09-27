<?php
/** Cache administration output tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\CacheAdminPage;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheWarmer;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class CacheAdminPageTest extends TestCase {
	protected function tearDown(): void {
		$GLOBALS['npe_test_can_manage'] = true;
		$GLOBALS['npe_test_is_rtl']     = false;
	}

	public function test_overview_is_capability_checked_nonce_protected_and_rtl_safe(): void {
		$directory                       = sys_get_temp_dir() . '/npe-admin-' . uniqid( '', true );
		$store                           = new FilesystemCacheStore( $directory );
		$metrics                         = new CacheMetrics( $directory . '/metrics.json' );
		$GLOBALS['npe_test_can_manage'] = true;
		$GLOBALS['npe_test_is_rtl']     = true;
		$page                            = new CacheAdminPage( $store, $metrics, new CachePurger( $store, new URLNormalizer(), $metrics ), new CacheWarmer( new URLNormalizer() ), new Settings(), new Capabilities() );

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'dir="rtl"', $output );
		$this->assertSame( 2, substr_count( $output, 'name="_wpnonce"' ) );
		$this->assertStringContainsString( 'application-level cache', $output );
		$this->assertStringContainsString( 'Purge cache', $output );
		$this->assertStringContainsString( 'Fragment and Object Cache', $output );
		$this->assertStringContainsString( 'Top fragments', $output );
		$this->assertStringContainsString( 'Smart Purge and Warmup', $output );
		$this->assertStringContainsString( 'Dependency graph', $output );
		$this->assertStringContainsString( 'Failed jobs', $output );
	}

	public function test_unauthorized_user_cannot_render_overview(): void {
		$directory                       = sys_get_temp_dir() . '/npe-admin-' . uniqid( '', true );
		$store                           = new FilesystemCacheStore( $directory );
		$metrics                         = new CacheMetrics( $directory . '/metrics.json' );
		$GLOBALS['npe_test_can_manage'] = false;
		$page                            = new CacheAdminPage( $store, $metrics, new CachePurger( $store, new URLNormalizer(), $metrics ), new CacheWarmer( new URLNormalizer() ), new Settings(), new Capabilities() );
		$this->expectException( \RuntimeException::class );
		$page->render();
	}
}
