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
final class OptimizationManifestBuilderTest extends TestCase { protected function setUp(): void { $GLOBALS['npe_test_options'] = array( Settings::OPTION => array( 'optimization' => array( 'enabled' => true, 'safe_mode' => true ) ) ); } public function test_background_builder_stores_page_manifest_without_runtime_parsing(): void { $storage = new OptimizationManifestStorage(); $builder = new OptimizationManifestBuilder( $storage, new CriticalCSSGenerator(), new FontPreloadPlanner(), new AssetUsagePlanner(), new Settings() ); $css = new CSSManifest( array( 'used' => array( '.hero' ), 'selector_count' => 1 ) ); $javascript = new JavaScriptManifest( array( 'signature' => 'js', 'assets' => array(), 'required' => array(), 'unused_candidates' => array(), 'deferred' => array(), 'file_count' => 0 ) ); self::assertTrue( $builder->build( 'https://example.test/page/', array( 'dom_signature' => 'dom' ), array(), '.hero{display:block}', $css, $javascript, null ) ); $manifest = $storage->for_url( 'https://example.test/page/' ); self::assertNotNull( $manifest ); self::assertSame( '.hero{display:block}', $manifest->section( 'css' )['critical'] ); } }
