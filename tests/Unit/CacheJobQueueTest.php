<?php
/** Cache maintenance queue tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheJobQueue;
use PHPUnit\Framework\TestCase;

final class CacheJobQueueTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_duplicate_pending_jobs_are_coalesced(): void {
		$queue = new CacheJobQueue( 'npe_test_queue' );
		$this->assertTrue( $queue->enqueue( 'purge', array( 'level' => 'product', 'id' => 12 ), 'first' ) );
		$this->assertTrue( $queue->enqueue( 'purge', array( 'level' => 'product', 'id' => 12 ), 'duplicate' ) );
		$this->assertCount( 1, $queue->all() );
		$this->assertSame( 1, $queue->counts()['pending'] );
	}

	public function test_failed_jobs_stop_after_bounded_retries(): void {
		$queue = new CacheJobQueue( 'npe_test_queue' );
		$queue->enqueue( 'warm_url', array( 'url' => 'https://example.test/' ), 'test' );
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$jobs = $queue->claim();
			$this->assertCount( 1, $jobs );
			$queue->fail( $jobs[0]['id'], 'failure' );
			if ( $attempt < 2 ) {
				$GLOBALS['npe_test_options']['npe_test_queue'][0]['not_before'] = 0;
			}
		}
		$this->assertSame( 1, $queue->counts()['failed'] );
		$this->assertSame( 0, $queue->counts()['pending'] );
	}

	public function test_completed_jobs_are_removed(): void {
		$queue = new CacheJobQueue( 'npe_test_queue' );
		$queue->enqueue( 'purge', array( 'level' => 'page', 'id' => 8 ) );
		$job = $queue->claim()[0];
		$this->assertTrue( $queue->complete( $job['id'] ) );
		$this->assertSame( array(), $queue->all() );
	}
}
