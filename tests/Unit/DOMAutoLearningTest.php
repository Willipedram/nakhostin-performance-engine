<?php
/** Automatic DOM learning invalidation tests. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\DOM\DOMAnalysisQueue;
use Nakhostin\PerformanceEngine\DOM\DOMAutoLearning;
use Nakhostin\PerformanceEngine\DOM\PageAnalysisCoordinator;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DOMAutoLearningTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array(
			Settings::OPTION => array( 'dom' => array( 'enabled' => true ) ),
		);
		$GLOBALS['npe_test_transients'] = array();
	}

	public function test_npe_and_litespeed_purges_requeue_known_pages_without_duplicates(): void {
		$queue = new DOMAnalysisQueue();
		$queue->enqueue( 'https://example.test/product/watch/' );
		$job = $queue->claim();
		$queue->complete( $job['id'], 0.5 );
		$coordinator = ( new ReflectionClass( PageAnalysisCoordinator::class ) )->newInstanceWithoutConstructor();
		$learning = new DOMAutoLearning( $queue, $coordinator, new Settings() );

		$learning->cache_purged( 'all', array() );
		$this->assertCount( 1, $queue->all() );
		$learning->litespeed_cache_purged();
		$this->assertCount( 1, $queue->all() );
		$learning->litespeed_url_purged( 'https://example.test/product/watch/' );
		$this->assertCount( 1, $queue->all() );
		$this->assertSame( 'npe-cache-purged', $queue->all()[0]['reason'] );
	}

	public function test_scoped_external_purge_url_is_rejected(): void {
		$queue = new DOMAnalysisQueue();
		$coordinator = ( new ReflectionClass( PageAnalysisCoordinator::class ) )->newInstanceWithoutConstructor();
		$learning = new DOMAutoLearning( $queue, $coordinator, new Settings() );
		$learning->cache_purged( 'urls', array( 'https://attacker.test/private/' ) );
		$this->assertSame( array(), $queue->all() );
	}
}
