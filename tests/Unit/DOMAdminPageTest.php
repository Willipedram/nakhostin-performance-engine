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
use Nakhostin\PerformanceEngine\DOM\DOMAnalysisQueue;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class DOMAdminPageTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array(); $GLOBALS['npe_test_can_manage'] = true; }
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
		$this->assertStringContainsString( '<progress', $output );
		$this->assertStringContainsString( 'npe-dom-hero', $output );
		$this->assertStringContainsString( 'npe-dom-stat-grid', $output );
		$this->assertStringContainsString( 'Queue is healthy', $output );
		$this->assertStringContainsString( 'Estimated time remaining', $output );
	}


	public function test_failed_legacy_jobs_have_a_clear_state_and_recovery_guidance(): void {
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array( 'dom' => array( 'enabled' => true ) );
		$GLOBALS['npe_test_options'][ DOMAnalysisQueue::OPTION ] = array( 'job' => array( 'status' => 'failed', 'last_error' => '' ) );
		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'has-issues', $output );
		$this->assertStringContainsString( 'Completed with issues', $output );
		$this->assertStringContainsString( 'No error detail was recorded', $output );
		$this->assertStringContainsString( 'Retry failed scans', $output );
	}

	public function test_unauthorized_user_cannot_view_dom_diagnostics(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );

		$this->page()->render();
	}

	public function test_progress_script_only_loads_on_dom_screen(): void {
		$GLOBALS['npe_test_scripts'] = array();
		$page = $this->page();
		$page->enqueue_progress_script( 'nakhostin-performance-engine_page_npe-dom-intelligence' );
		$this->assertArrayHasKey( 'npe-dom-progress', $GLOBALS['npe_test_scripts'] );
		$this->assertArrayHasKey( 'npe-dom-progress', $GLOBALS['npe_test_localized_scripts'] );
		$GLOBALS['npe_test_scripts'] = array();
		$page->enqueue_progress_script( 'plugins.php' );
		$this->assertSame( array(), $GLOBALS['npe_test_scripts'] );
	}

	private function page(): DOMAdminPage {
		return new DOMAdminPage(
			new DOMAnalyzer(
				new DOMComponentDetector(),
				new DOMStateRegistry(),
				new DOMSignature()
			),
			new DOMStorage(),
			new Capabilities(),
			null,
			null,
			new DOMAnalysisQueue(),
			new Settings()
		);
	}
}
