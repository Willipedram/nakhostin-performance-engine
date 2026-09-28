<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Assets\AssetUsagePlanner;
use Nakhostin\PerformanceEngine\CSS\CriticalCSSGenerator;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use Nakhostin\PerformanceEngine\CSS\FontPreloadPlanner;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\Optimization\OptimizationManifestBuilder;
use Nakhostin\PerformanceEngine\Optimization\OptimizationManifestStorage;
use PHPUnit\Framework\TestCase;

final class OptimizationManifestBuilderTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array( Settings::OPTION => array( 'optimization' => array( 'enabled' => true, 'safe_mode' => true ), 'assets' => array( 'bundle_enabled' => true ) ) ); }

	public function test_background_builder_stores_page_manifest_without_runtime_parsing(): void {
		$storage = new OptimizationManifestStorage(); $builder = $this->builder( $storage );
		$css = new CSSManifest( array( 'used' => array( '.hero' ), 'selector_count' => 1 ) );
		$javascript = new JavaScriptManifest( array( 'signature' => 'js', 'assets' => array(), 'required' => array(), 'observed' => array(), 'capture' => array( 'complete' => true ), 'dependency_complete' => true, 'deferred' => array(), 'file_count' => 0 ) );
		self::assertTrue( $builder->build( 'https://example.test/page/', array( 'dom_signature' => 'dom' ), array(), '.hero{display:block}', $css, $javascript, null, array( 'capture_complete' => true ) ) );
		$manifest = $storage->for_url( 'https://example.test/page/' );
		self::assertNotNull( $manifest );
		self::assertSame( '.hero{display:block}', $manifest->section( 'css' )['critical'] );
		self::assertSame( 'safe-mode', $manifest->to_array()['summary']['css']['bundle_status'] );
	}

	public function test_global_registrations_are_diagnostics_not_actionable_decisions(): void {
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array( 'optimization' => array( 'enabled' => true, 'safe_mode' => false ), 'assets' => array( 'bundle_enabled' => false ) );
		$storage = new OptimizationManifestStorage(); $builder = $this->builder( $storage );
		$assets = array(
			'foundation' => array( 'dependencies' => array() ),
			'page-app' => array( 'dependencies' => array( 'foundation' ) ),
			'global-unused' => array( 'dependencies' => array() ),
			'optional' => array( 'dependencies' => array() ),
		);
		$javascript = new JavaScriptManifest( array( 'signature' => 'js', 'assets' => $assets, 'required' => array( 'foundation', 'page-app' ), 'required_by_page' => array(), 'observed' => array( 'page-app' ), 'enqueued' => array(), 'component_required' => array(), 'protected' => array(), 'explicitly_absent' => array( 'optional' ), 'capture' => array( 'complete' => true ), 'dependency_complete' => true, 'deferred' => array(), 'file_count' => 4, 'registration_diagnostics' => array( 'registered' => 4 ) ) );
		self::assertTrue( $builder->build( 'https://example.test/scoped/', array( 'dom_signature' => 'dom' ), array(), '', new CSSManifest( array( 'selector_count' => 0 ) ), $javascript, null, array( 'capture_complete' => true ) ) );
		$data = $storage->for_url( 'https://example.test/scoped/' )->to_array();
		$decisions = array_column( $data['assets']['decisions'], null, 'handle' );
		self::assertArrayHasKey( 'page-app', $decisions );
		self::assertArrayHasKey( 'foundation', $decisions );
		self::assertArrayHasKey( 'optional', $decisions );
		self::assertArrayNotHasKey( 'global-unused', $decisions );
		self::assertSame( 'unload', $decisions['optional']['decision'] );
		self::assertSame( 'disabled', $data['summary']['javascript']['bundle_status'] );
	}

	public function test_incomplete_capture_explains_zero_bundle_bytes(): void {
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array( 'optimization' => array( 'enabled' => true, 'safe_mode' => false ), 'assets' => array( 'bundle_enabled' => true ) );
		$storage = new OptimizationManifestStorage();
		$javascript = new JavaScriptManifest( array( 'signature' => 'js', 'assets' => array(), 'required' => array(), 'observed' => array(), 'capture' => array( 'complete' => false ), 'dependency_complete' => false, 'deferred' => array() ) );
		self::assertTrue( $this->builder( $storage )->build( 'https://example.test/incomplete/', array( 'dom_signature' => 'dom' ), array(), '', new CSSManifest( array( 'selector_count' => 0 ) ), $javascript, null, array( 'capture_complete' => false, 'capture_status' => 'incomplete-document' ) ) );
		$summary = $storage->for_url( 'https://example.test/incomplete/' )->to_array()['summary'];
		self::assertSame( 0, $summary['css']['bundle_bytes'] );
		self::assertSame( 'incomplete-source-capture', $summary['css']['bundle_status'] );
		self::assertSame( 'incomplete-source-capture', $summary['javascript']['bundle_status'] );
	}

	private function builder( OptimizationManifestStorage $storage ): OptimizationManifestBuilder { return new OptimizationManifestBuilder( $storage, new CriticalCSSGenerator(), new FontPreloadPlanner(), new AssetUsagePlanner(), new Settings() ); }
}
