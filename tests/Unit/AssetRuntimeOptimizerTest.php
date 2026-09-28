<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Assets\AssetRuntimeOptimizer;
use PHPUnit\Framework\TestCase;

final class AssetRuntimeOptimizerTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_dequeued_scripts'] = array(); $GLOBALS['npe_test_dequeued_styles'] = array(); }

	public function test_runtime_only_applies_precomputed_high_confidence_unloads(): void {
		$optimizer = new AssetRuntimeOptimizer();
		$result = $optimizer->apply(
			array(
				array( 'handle' => 'approved', 'type' => 'script', 'decision' => 'unload', 'confidence' => 100 ),
				array( 'handle' => 'unknown', 'type' => 'script', 'decision' => 'keep', 'confidence' => 0 ),
				array( 'handle' => 'weak', 'type' => 'style', 'decision' => 'unload', 'confidence' => 50 ),
			)
		);
		self::assertSame( array( 'approved' ), $result );
		self::assertSame( array( 'approved' ), $GLOBALS['npe_test_dequeued_scripts'] );
		self::assertSame( array(), $GLOBALS['npe_test_dequeued_styles'] );
	}
}
