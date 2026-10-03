<?php
/**
 * CSS runtime fail-open safety tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\CSSOptimizationPolicy;
use Nakhostin\PerformanceEngine\CSS\CSSRuntimeOptimizer;
use Nakhostin\PerformanceEngine\CSS\StylesheetStrategyApplier;
use PHPUnit\Framework\TestCase;

final class CSSRuntimeSafetyTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_dequeued_styles'] = array();
	}

	public function test_legacy_defer_decision_preserves_blocking_stylesheet(): void {
		$applier = new StylesheetStrategyApplier();
		$html    = '<link rel="stylesheet" href="/theme.css">';

		self::assertSame(
			array(),
			$applier->apply( array( 'theme' => array( 'decision' => CSSOptimizationPolicy::DEFER ) ), false )
		);
		self::assertSame( $html, $applier->filter_tag( $html, 'theme' ) );
	}

	public function test_safe_mode_never_unloads_a_stylesheet(): void {
		$evidence = array(
			'decision'               => CSSOptimizationPolicy::UNLOAD,
			'high_confidence'        => true,
			'component_absent'       => true,
			'dependencies_satisfied' => true,
			'observations'           => 10,
		);

		self::assertSame( array(), ( new StylesheetStrategyApplier() )->apply( array( 'theme' => $evidence ), true ) );
		self::assertSame( array(), $GLOBALS['npe_test_dequeued_styles'] );
	}

	public function test_unload_requires_repeated_dependency_safe_evidence(): void {
		$applier = new StylesheetStrategyApplier();
		$weak    = array( 'decision' => CSSOptimizationPolicy::UNLOAD, 'high_confidence' => true );
		$strong  = array(
			'decision'               => CSSOptimizationPolicy::UNLOAD,
			'high_confidence'        => true,
			'component_absent'       => true,
			'dependencies_satisfied' => true,
			'observations'           => 3,
		);

		self::assertSame( array(), $applier->apply( array( 'weak' => $weak ), false ) );
		self::assertSame( array( 'theme' => CSSOptimizationPolicy::UNLOAD ), $applier->apply( array( 'theme' => $strong ), false ) );
		self::assertSame( array( 'theme' ), $GLOBALS['npe_test_dequeued_styles'] );
	}

	public function test_critical_css_rejects_unsafe_or_oversized_payloads(): void {
		$optimizer = new CSSRuntimeOptimizer();

		ob_start();
		$optimizer->inline( '@import url("slow.css");body{display:block}' );
		$optimizer->inline( str_repeat( 'a', 32769 ) );
		self::assertSame( '', ob_get_clean() );

		ob_start();
		$optimizer->inline( '.hero{display:block}' );
		self::assertSame( '<style id="npe-critical-css">.hero{display:block}</style>', ob_get_clean() );
	}
}
