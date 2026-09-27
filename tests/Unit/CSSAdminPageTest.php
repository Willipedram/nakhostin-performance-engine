<?php
/**
 * CSS administration safety and explanation tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\CSSAdminPage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\CSSStorage;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use PHPUnit\Framework\TestCase;

final class CSSAdminPageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']    = array();
		$GLOBALS['npe_test_can_manage'] = true;
	}

	public function test_form_is_nonce_protected_and_explains_non_destructive_analysis(): void {
		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'name="npe_css_source"', $output );
		$this->assertStringContainsString( 'never removes or rewrites CSS automatically', $output );
		$this->assertStringContainsString( 'processed in memory and is not stored', $output );
	}

	public function test_unauthorized_user_cannot_view_css_diagnostics(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );
		$this->page()->render();
	}

	private function page(): CSSAdminPage {
		return new CSSAdminPage( new CSSAnalyzer(), new CSSStorage(), new DOMStorage(), new Capabilities() );
	}
}
