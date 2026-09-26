<?php
/** Performance dashboard security and terminology tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\PerformanceAdminPage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceSample;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;
use PHPUnit\Framework\TestCase;

final class PerformanceAdminPageTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array(); $GLOBALS['npe_test_can_manage'] = true; }

	public function test_dashboard_labels_backend_timing_without_claiming_ttfb(): void {
		$storage = new PerformanceStorage();
		$storage->save( new PerformanceSample( array( 'page_type' => 'product', 'backend_generation_ms' => 38, 'cache_state' => 'hit' ) ) );
		$page = new PerformanceAdminPage( $storage, new PerformanceAggregator(), new Capabilities() );
		ob_start();
		$page->render();
		$output = (string) ob_get_clean();
		$this->assertStringContainsString( 'backend PHP generation measurements', $output );
		$this->assertStringContainsString( 'not browser-observed TTFB', $output );
		$this->assertStringContainsString( 'Page-type breakdown', $output );
		$this->assertStringNotContainsString( 'token=', $output );
	}

	public function test_dashboard_requires_management_capability(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );
		( new PerformanceAdminPage( new PerformanceStorage(), new PerformanceAggregator(), new Capabilities() ) )->render();
	}
}
