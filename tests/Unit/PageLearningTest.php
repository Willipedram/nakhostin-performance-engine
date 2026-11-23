<?php
/** Regression tests for page-specific learning. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Optimization\PageLearning;
use PHPUnit\Framework\TestCase;

final class PageLearningTest extends TestCase {
	public function test_stable_evidence_requires_independent_observations_and_keeps_seen_classes(): void {
		$learning = new PageLearning();
		$url = 'https://example.test/learning/';
		$at = 1000;
		self::assertSame( 1, $learning->observe( $url, 'v1', array( '.hidden', '.sometimes' ), $at )['samples'] );
		self::assertSame( 1, $learning->observe( $url, 'v1', array( '.hidden' ), $at + 1 )['samples'] );
		self::assertSame( 2, $learning->observe( $url, 'v1', array( '.hidden' ), $at + 30 )['samples'] );
		$ready = $learning->observe( $url, 'v1', array( '.hidden', '.sometimes' ), $at + 60 );
		self::assertSame( 3, $ready['samples'] );
		self::assertSame( array( '.hidden' ), $ready['unused'] );
		self::assertSame( 1, $learning->observe( $url, 'v2', array( '.hidden' ), $at + 90 )['samples'] );
		self::assertSame( 1, $learning->observe( $url . 'other/', 'v1', array( '.hidden' ), $at + 90 )['samples'] );
	}
}
