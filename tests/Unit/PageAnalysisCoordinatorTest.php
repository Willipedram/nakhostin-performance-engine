<?php
/** One-click page intelligence orchestration tests. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\CSSStorage;
use Nakhostin\PerformanceEngine\CSS\StylesheetSourceCollector;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use Nakhostin\PerformanceEngine\DOM\PageAnalysisCoordinator;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptPlanner;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ObservedScriptResolver;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
use Nakhostin\PerformanceEngine\JavaScript\ScriptSafetyPolicy;
use PHPUnit\Framework\TestCase;

final class PageAnalysisCoordinatorTest extends TestCase {
	public function test_single_capture_builds_dom_css_component_and_javascript_manifests(): void {
		$GLOBALS['npe_test_options'] = array();
		$GLOBALS['npe_test_remote_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '.card{color:red}.missing{display:none}' );
		$dom_storage = new DOMStorage();
		$css_storage = new CSSStorage();
		$js_storage  = new JavaScriptStorage();
		$coordinator = new PageAnalysisCoordinator(
			new DOMAnalyzer( new DOMComponentDetector(), new DOMStateRegistry(), new DOMSignature() ),
			$dom_storage,
			new ComponentRegistry( new ComponentStorage(), new BundleIndex() ),
			new CSSAnalyzer(),
			$css_storage,
			new StylesheetSourceCollector(),
			new ScriptDiscovery(),
			new LocalScriptSourceProvider(),
			new ObservedScriptResolver(),
			new JavaScriptAnalyzer( new JavaScriptPlanner( new ScriptSafetyPolicy(), new ConservativeMinifier() ) ),
			$js_storage,
			new Settings()
		);

		$html = '<html><head><link rel="stylesheet" href="https://example.com/site.css"></head><body><header></header><main class="card"></main></body></html>';
		$this->assertSame( $html, $coordinator->capture( $html, '', 'https://example.com/shop/' ) );
		$this->assertNotNull( $dom_storage->latest() );
		$this->assertContains( '.card', $css_storage->latest()->to_array()['used'] );
		$this->assertContains( '.missing', $css_storage->latest()->to_array()['unused_candidates'] );
		$this->assertNotNull( $js_storage->latest() );
	}
}
