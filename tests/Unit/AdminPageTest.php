<?php
/**
 * Administration output security and localization tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\AdminPage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class AdminPageTest extends TestCase {
	public function test_output_is_nonce_protected_escaped_and_rtl_safe(): void {
		$settings                        = new Settings();
		$GLOBALS['npe_test_can_manage'] = true;
		$GLOBALS['npe_test_is_rtl']     = true;
		$_SERVER['SERVER_SOFTWARE']     = '<script>alert(1)</script>';
		$page                            = new AdminPage(
			$settings,
			new Diagnostics(),
			new Capabilities(),
			new FeatureFlags( $settings )
		);

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'dir="rtl"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( 'Not available', $output );
	}

	public function test_unauthorized_user_cannot_render_screen(): void {
		$settings                        = new Settings();
		$GLOBALS['npe_test_can_manage'] = false;
		$page                            = new AdminPage(
			$settings,
			new Diagnostics(),
			new Capabilities(),
			new FeatureFlags( $settings )
		);

		$this->expectException( \RuntimeException::class );
		$page->render();
	}
}
