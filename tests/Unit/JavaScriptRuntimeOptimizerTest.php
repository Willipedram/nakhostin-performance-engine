<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptRuntimeOptimizer;
use Nakhostin\PerformanceEngine\JavaScript\ScriptSafetyPolicy;
use Nakhostin\PerformanceEngine\JavaScript\ScriptStrategyApplier;
use PHPUnit\Framework\TestCase;
final class JavaScriptRuntimeOptimizerTest extends TestCase { protected function setUp(): void { $GLOBALS['npe_test_script_data'] = array(); $GLOBALS['npe_test_dequeued_scripts'] = array(); } public function test_applies_saved_defer_and_preserves_checkout(): void { $optimizer = new JavaScriptRuntimeOptimizer( new ScriptStrategyApplier(), new ScriptSafetyPolicy() ); $manifest = new JavaScriptManifest( array( 'signature' => 'valid', 'deferred' => array( 'safe-app' ), 'required' => array( 'safe-app' ), 'approved_unloads' => array( 'unused-widget' ) ) ); $result = $optimizer->apply( $manifest, array( 'page_type' => 'page' ), true, true ); self::assertSame( 'defer', $GLOBALS['npe_test_script_data']['safe-app']['strategy'] ); self::assertContains( 'unused-widget', $result['unloaded'] ); $GLOBALS['npe_test_script_data'] = array(); $protected = $optimizer->apply( $manifest, array( 'page_type' => 'checkout' ), true, true ); self::assertSame( 'protected-commerce-context', $protected['reason'] ); self::assertSame( array(), $GLOBALS['npe_test_script_data'] ); } }
