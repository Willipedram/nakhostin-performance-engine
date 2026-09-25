<?php
/**
 * DOM administration security tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\DOMAdminPage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use PHPUnit\Framework\TestCase;

final class DOMAdminPageTest extends TestCase {
	public function test_run_form_contains_nonce_and_escaped_ltr_output(): void {
		$GLOBALS['npe_test_can_manage'] = true;
		$GLOBALS['npe_test_is_rtl']     = false;
		$page                            = $this->page();

		ob_start();
		$page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'dir="ltr"', $output );
		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'name="source_url"', $output );
		$this->assertStringContainsString( 'Analyze DOM, CSS, JavaScript, and Components', $output );
		$this->assertStringContainsString( 'No DOM manifest has been generated yet.', $output );
	}

	public function test_unauthorized_user_cannot_view_dom_diagnostics(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );

		$this->page()->render();
	}

	private function page(): DOMAdminPage {
		return new DOMAdminPage(
			new DOMAnalyzer(
				new DOMComponentDetector(),
				new DOMStateRegistry(),
				new DOMSignature()
			),
			new DOMStorage(),
			new Capabilities()
		);
	}
}
