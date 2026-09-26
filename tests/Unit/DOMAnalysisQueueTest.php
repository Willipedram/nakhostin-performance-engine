<?php
/** Deferred DOM analysis queue tests. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\DOM\DOMAnalysisQueue;
use PHPUnit\Framework\TestCase;

final class DOMAnalysisQueueTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
		$GLOBALS['npe_test_transients'] = array();
		unset( $GLOBALS['npe_test_scheduled_event'] );
	}

	public function test_enqueue_normalizes_deduplicates_and_schedules_same_origin_url(): void {
		$queue = new DOMAnalysisQueue();
		$this->assertTrue( $queue->enqueue( 'https://example.test/products/watch/?utm_source=test#details' ) );
		$this->assertFalse( $queue->enqueue( 'https://example.test/products/watch/' ) );
		$this->assertFalse( $queue->enqueue( 'https://attacker.test/products/watch/' ) );
		$this->assertCount( 1, $queue->all() );
		$this->assertSame( 'https://example.test/products/watch/', $queue->all()[0]['url'] );
		$this->assertSame( DOMAnalysisQueue::CRON_HOOK, $GLOBALS['npe_test_scheduled_event']['hook'] );
	}

	public function test_job_is_claimed_retried_and_eventually_marked_failed(): void {
		$queue = new DOMAnalysisQueue();
		$this->assertTrue( $queue->enqueue( 'https://example.test/shop/' ) );
		$job = $queue->claim();
		$this->assertSame( 'running', $job['status'] );
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$queue->retry( $job['id'] );
			if ( $attempt < 2 ) {
				$stored = $queue->all()[0];
				$stored['available_at'] = time() - 1;
				$GLOBALS['npe_test_options'][ DOMAnalysisQueue::OPTION ][ $job['id'] ] = $stored;
				$job = $queue->claim();
			}
		}
		$this->assertSame( 'failed', $queue->all()[0]['status'] );
		$this->assertSame( 3, $queue->all()[0]['attempts'] );
	}

	public function test_complete_removes_claimed_job(): void {
		$queue = new DOMAnalysisQueue();
		$queue->enqueue( 'https://example.test/' );
		$job = $queue->claim();
		$queue->complete( $job['id'] );
		$this->assertSame( array(), $queue->all() );
	}
}
