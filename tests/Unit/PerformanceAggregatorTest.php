<?php
/** Performance aggregation tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use PHPUnit\Framework\TestCase;

final class PerformanceAggregatorTest extends TestCase {
	public function test_statistics_cache_ratio_and_all_breakdowns_are_accurate(): void {
		$samples = array(
			array( 'page_type' => 'product', 'template' => 'single-product', 'components' => array( 'gallery' ), 'cache_state' => 'hit', 'backend_generation_ms' => 10, 'database_query_time_ms' => 2 ),
			array( 'page_type' => 'product', 'template' => 'single-product', 'components' => array( 'gallery' ), 'cache_state' => 'miss', 'backend_generation_ms' => 30, 'database_query_time_ms' => 6 ),
			array( 'page_type' => 'shop', 'template' => 'archive-product', 'components' => array( 'grid' ), 'cache_state' => 'hit', 'backend_generation_ms' => 20, 'database_query_time_ms' => null ),
		);
		$result = ( new PerformanceAggregator() )->summarize( $samples );
		$this->assertSame( 20.0, $result['metrics']['backend_generation_ms']['average'] );
		$this->assertSame( 20.0, $result['metrics']['backend_generation_ms']['median'] );
		$this->assertSame( 66.67, $result['cache_hit_ratio'] );
		$this->assertSame( 20.0, $result['breakdown']['page_type']['product']['php_ms'] );
		$this->assertArrayHasKey( 'single-product', $result['breakdown']['template'] );
		$this->assertArrayHasKey( 'gallery', $result['breakdown']['component'] );
		$this->assertArrayHasKey( 'miss', $result['breakdown']['cache_state'] );
		$this->assertSame( 4.0, $result['metrics']['database_query_time_ms']['average'] );
	}
}
