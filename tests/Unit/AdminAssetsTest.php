<?php
/** Scoped LTR and RTL administration asset tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Admin\AdminPage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;
final class AdminAssetsTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_styles'] = array(); $GLOBALS['npe_test_is_rtl'] = false; }
	public function test_assets_are_not_loaded_outside_npe(): void { $this->page()->enqueue_assets( 'dashboard_page_unrelated' ); $this->assertSame( array(), $GLOBALS['npe_test_styles'] ); }
	public function test_ltr_loads_only_base_styles(): void { $this->page()->enqueue_assets( 'toplevel_page_' . AdminPage::SLUG ); $this->assertArrayHasKey( 'npe-admin', $GLOBALS['npe_test_styles'] ); $this->assertArrayNotHasKey( 'npe-admin-rtl', $GLOBALS['npe_test_styles'] ); }
	public function test_rtl_loads_scoped_override(): void { $GLOBALS['npe_test_is_rtl'] = true; $this->page()->enqueue_assets( AdminPage::SLUG . '_page_npe-cache-overview' ); $this->assertArrayHasKey( 'npe-admin', $GLOBALS['npe_test_styles'] ); $this->assertArrayHasKey( 'npe-admin-rtl', $GLOBALS['npe_test_styles'] ); }
	private function page(): AdminPage { $settings = new Settings(); return new AdminPage( $settings, new Diagnostics(), new Capabilities(), new FeatureFlags( $settings ) ); }
}
