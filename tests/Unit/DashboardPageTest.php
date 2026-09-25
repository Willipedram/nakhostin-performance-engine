<?php
/** Professional dashboard output tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\DashboardPage;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CacheOperationsState;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;
use PHPUnit\Framework\TestCase;

final class DashboardPageTest extends TestCase {
	/** @var string */ private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-dashboard-' . uniqid( '', true ); $GLOBALS['npe_test_options'] = array(); $GLOBALS['npe_test_can_manage'] = true; $GLOBALS['npe_test_is_rtl'] = false; }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $this->directory ); } }

	public function test_dashboard_is_localized_accessible_and_ltr_safe(): void {
		$page = $this->page(); ob_start(); $page->render(); $output = (string) ob_get_clean();
		$this->assertStringContainsString( 'dir="ltr"', $output );
		$this->assertStringContainsString( 'role="list"', $output );
		$this->assertStringContainsString( 'Cache hit ratio', $output );
		$this->assertStringContainsString( 'PHP generation; not network TTFB', $output );
		$this->assertStringContainsString( 'dir="ltr"', $output );
	}

	public function test_dashboard_uses_rtl_document_direction(): void { $GLOBALS['npe_test_is_rtl'] = true; ob_start(); $this->page()->render(); $output = (string) ob_get_clean(); $this->assertStringContainsString( 'dir="rtl"', $output ); $this->assertStringContainsString( 'class="npe-metric-value npe-technical" dir="ltr"', $output ); }
	public function test_dashboard_requires_capability(): void { $GLOBALS['npe_test_can_manage'] = false; $this->expectException( \RuntimeException::class ); $this->page()->render(); }

	private function page(): DashboardPage { return new DashboardPage( new Settings(), new FilesystemCacheStore( $this->directory . '/pages' ), new CacheMetrics( $this->directory . '/metrics.json' ), new CacheOperationsState(), new PerformanceStorage(), new PerformanceAggregator(), new JavaScriptStorage(), new Capabilities() ); }
}
