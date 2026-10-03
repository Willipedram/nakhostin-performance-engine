<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Assets\AssetRegistry;
use Nakhostin\PerformanceEngine\Assets\AssetUsagePlanner;
use PHPUnit\Framework\TestCase;

final class PageAssetEvidenceTest extends TestCase {
	public function test_complete_page_fixture_is_scoped_and_dependency_safe(): void {
		$registry = $this->registry();
		$decisions = ( new AssetUsagePlanner() )->plan(
			$registry,
			array(
				'capture_complete' => true,
				'dependency_complete' => true,
				'required' => array( 'direct-page' ),
				'observed' => array( 'page-app' ),
				'component_required' => array( 'gallery' ),
				'protected' => array( 'wc-checkout' ),
				'explicitly_absent' => array( 'optional-widget' ),
			),
			false
		);

		self::assertArrayNotHasKey( 'global-unused-one', $decisions );
		self::assertArrayNotHasKey( 'global-unused-two', $decisions );
		self::assertSame( 'required-by-page', $decisions['direct-page']['reason'] );
		self::assertSame( 'observed-on-rendered-page', $decisions['page-app']['reason'] );
		self::assertSame( 'required-by-component', $decisions['gallery']['reason'] );
		self::assertSame( 'required-by-dependency', $decisions['gallery-core']['reason'] );
		self::assertSame( 'required-by-dependency', $decisions['foundation']['reason'] );
		self::assertSame( 'protected-by-safety-policy', $decisions['wc-checkout']['reason'] );
		self::assertSame( 'unload', $decisions['optional-widget']['decision'] );
	}

	public function test_incomplete_capture_keeps_explicit_absence_uncertain(): void {
		$decisions = ( new AssetUsagePlanner() )->plan( $this->registry(), array( 'capture_complete' => false, 'explicitly_absent' => array( 'optional-widget' ) ), false );
		self::assertSame( 'keep', $decisions['optional-widget']['decision'] );
		self::assertSame( 'analysis-incomplete-falls-back-to-keep', $decisions['optional-widget']['reason'] );
	}

	public function test_safe_mode_unknown_and_missing_dependencies_fail_open(): void {
		$registry = $this->registry();
		$registry->register( 'unknown-page-candidate', 'script' );
		$registry->register( 'broken-candidate', 'script', array( 'missing-library' ) );
		$planner = new AssetUsagePlanner();
		$safe = $planner->plan( $registry, array( 'capture_complete' => true, 'explicitly_absent' => array( 'optional-widget' ) ), true );
		$unknown = $planner->plan( $registry, array( 'capture_complete' => true, 'candidates' => array( 'unknown-page-candidate' ) ), false );
		$broken = $planner->plan( $registry, array( 'capture_complete' => true, 'explicitly_absent' => array( 'broken-candidate' ) ), false );
		self::assertSame( 'keep', $safe['optional-widget']['decision'] );
		self::assertSame( 'unknown-state-falls-back-to-keep', $unknown['unknown-page-candidate']['reason'] );
		self::assertSame( 'dependency-state-uncertain', $broken['broken-candidate']['reason'] );
	}

	public function test_absent_cycle_terminates_and_stays_keep(): void {
		$registry = new AssetRegistry();
		$registry->register( 'cycle-a', 'script', array( 'cycle-b' ) );
		$registry->register( 'cycle-b', 'script', array( 'cycle-a' ) );
		$decisions = ( new AssetUsagePlanner() )->plan( $registry, array( 'capture_complete' => true, 'explicitly_absent' => array( 'cycle-a' ) ), false );
		self::assertSame( 'keep', $decisions['cycle-a']['decision'] );
		self::assertSame( 'dependency-state-uncertain', $decisions['cycle-a']['reason'] );
	}

	private function registry(): AssetRegistry {
		$registry = new AssetRegistry();
		$registry->register( 'foundation', 'script' );
		$registry->register( 'gallery-core', 'script', array( 'foundation' ) );
		$registry->register( 'gallery', 'script', array( 'gallery-core' ) );
		$registry->register( 'page-app', 'script', array( 'foundation' ) );
		$registry->register( 'direct-page', 'script' );
		$registry->register( 'wc-checkout', 'script' );
		$registry->register( 'optional-widget', 'script' );
		$registry->register( 'global-unused-one', 'script' );
		$registry->register( 'global-unused-two', 'script' );
		return $registry;
	}
}
