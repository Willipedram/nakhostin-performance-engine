<?php
/**
 * JavaScript administration security tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Admin\JavaScriptAdminPage;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptPlanner;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
use Nakhostin\PerformanceEngine\JavaScript\ScriptSafetyPolicy;
use PHPUnit\Framework\TestCase;

final class JavaScriptAdminPageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']    = array();
		$GLOBALS['npe_test_can_manage'] = true;
	}

	public function test_analysis_form_has_nonce_and_safe_empty_state(): void {
		ob_start();
		$this->page()->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="_wpnonce"', $output );
		$this->assertStringContainsString( 'Analyze Registered Scripts', $output );
		$this->assertStringContainsString( 'No JavaScript analysis has been recorded.', $output );
	}

	public function test_unauthorized_user_cannot_view_javascript_diagnostics(): void {
		$GLOBALS['npe_test_can_manage'] = false;
		$this->expectException( \RuntimeException::class );

		$this->page()->render();
	}

	private function page(): JavaScriptAdminPage {
		$minifier = new ConservativeMinifier();

		return new JavaScriptAdminPage(
			new ScriptDiscovery(),
			new JavaScriptAnalyzer( new JavaScriptPlanner( new ScriptSafetyPolicy(), $minifier ) ),
			new JavaScriptStorage(),
			new LocalScriptSourceProvider(),
			new ComponentRegistry( new ComponentStorage(), new BundleIndex() ),
			new Settings(),
			new Capabilities(),
			new DOMStorage()
		);
	}
}
