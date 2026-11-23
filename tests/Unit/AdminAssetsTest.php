<?php
/** Scoped administration asset and render tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\AdminPage;
use Nakhostin\PerformanceEngine\Admin\AdminShell;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class AdminAssetsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_styles'] = array();
		$GLOBALS['npe_test_scripts'] = array();
		$GLOBALS['npe_test_admin_pages'] = array();
		$GLOBALS['npe_test_is_rtl'] = false;
		$GLOBALS['npe_test_can_manage'] = true;
	}

	public function test_shell_assets_are_scoped_to_registered_pages(): void {
		$this->register_fixture();
		AdminShell::enqueue( 'dashboard_page_unrelated' );
		self::assertSame( array(), $GLOBALS['npe_test_styles'] );
		self::assertSame( array(), $GLOBALS['npe_test_scripts'] );
		AdminShell::enqueue( AdminPage::SLUG . '_page_npe-design-fixture' );
		self::assertArrayHasKey( 'npe-admin-system', $GLOBALS['npe_test_styles'] );
		self::assertArrayHasKey( 'npe-admin-shell', $GLOBALS['npe_test_scripts'] );
		self::assertArrayNotHasKey( 'npe-admin-rtl', $GLOBALS['npe_test_styles'] );
	}

	public function test_registered_page_inherits_rtl_shell_and_navigation(): void {
		$GLOBALS['npe_test_is_rtl'] = true;
		$this->register_fixture();
		AdminShell::enqueue( AdminPage::SLUG . '_page_npe-design-fixture' );
		self::assertArrayHasKey( 'npe-admin-rtl', $GLOBALS['npe_test_styles'] );
		$callback = $GLOBALS['npe_test_admin_pages'][0]['callback'];
		ob_start();
		$callback();
		$output = (string) ob_get_clean();
		self::assertStringContainsString( 'class="wrap npe-admin npe-shell" dir="rtl"', $output );
		self::assertStringContainsString( '<main id="npe-main"', $output );
		self::assertStringContainsString( 'aria-current="page"', $output );
		self::assertStringContainsString( 'Fixture content', $output );
	}

	public function test_settings_script_only_loads_on_registered_settings_screen(): void {
		$settings = new Settings();
		$page = new AdminPage( $settings, new Diagnostics(), new Capabilities(), new FeatureFlags( $settings ) );
		$page->add_menu();
		$page->enqueue_assets( AdminPage::SLUG . '_page_' . AdminPage::SETTINGS_SLUG );
		self::assertArrayHasKey( 'npe-settings', $GLOBALS['npe_test_scripts'] );
		$GLOBALS['npe_test_scripts'] = array();
		$page->enqueue_assets( AdminPage::SLUG . '_page_npe-cache-overview' );
		self::assertArrayNotHasKey( 'npe-settings', $GLOBALS['npe_test_scripts'] );
	}

	public function test_views_use_the_shell_registration_layer(): void {
		foreach ( glob( NPE_PATH . 'src/Admin/*Page.php' ) as $file ) {
			$source = file_get_contents( $file );
			self::assertDoesNotMatchRegularExpression( '/\badd_(?:sub)?menu_page\s*\(/', $source, basename( $file ) );
		}
	}

	private function register_fixture(): void {
		AdminShell::submenu( AdminPage::SLUG, 'Fixture', 'Fixture', Capabilities::MANAGE, 'npe-design-fixture', static function (): void {
			echo '<p>Fixture content</p>';
		} );
	}
}
