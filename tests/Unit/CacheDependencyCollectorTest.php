<?php
/** Rendered page dependency collection tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyCollector;
use PHPUnit\Framework\TestCase;

final class CacheDependencyCollectorTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_queried_id']   = 123;
		$GLOBALS['npe_test_post_types']   = array( 123 => 'product' );
		$GLOBALS['npe_test_post_terms']   = array( 123 => array( 'product_cat' => array( 7 ) ) );
		$GLOBALS['npe_test_conditionals'] = array( 'singular' => true );
	}

	protected function tearDown(): void {
		$GLOBALS['npe_test_queried_id']   = 0;
		$GLOBALS['npe_test_conditionals'] = array();
	}

	public function test_product_page_records_product_post_and_category_dependencies(): void {
		$this->assertSame(
			array( 'post-123', 'product-123', 'product-category-7' ),
			( new CacheDependencyCollector() )->collect()
		);
	}

	public function test_category_archive_does_not_mistake_term_for_post(): void {
		$GLOBALS['npe_test_queried_id']   = 7;
		$GLOBALS['npe_test_conditionals'] = array( 'product_category' => true );
		$this->assertSame(
			array( 'page-type-product-category', 'product-category-7' ),
			( new CacheDependencyCollector() )->collect()
		);
	}
}
