<?php
/** WordPress/WooCommerce dependency builder tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyBuilder;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use PHPUnit\Framework\TestCase;

final class CacheDependencyBuilderTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']    = array();
		$GLOBALS['npe_test_post_types'] = array( 123 => 'product' );
		$GLOBALS['npe_test_post_terms'] = array( 123 => array( 'product_cat' => array( 7, 9 ) ) );
	}

	public function test_product_connects_page_categories_shop_and_related_components(): void {
		$graph   = new CacheDependencyGraph();
		$builder = new CacheDependencyBuilder( $graph );
		$this->assertSame( array( 'product:123' ), $builder->register_post( 123 ) );

		$result = $graph->resolve( array( 'product:123' ) );
		$this->assertContains( 'page:123', $result['nodes'] );
		$this->assertContains( 'taxonomy:product_cat:7', $result['nodes'] );
		$this->assertContains( 'taxonomy:product_cat:9', $result['nodes'] );
		$this->assertContains( 'page_type:shop', $result['nodes'] );
		$this->assertContains( 'component:related-products', $result['nodes'] );
		$this->assertContains( 'product-category-7', $result['tags'] );
	}

	public function test_component_usage_and_assets_connect_to_dependents(): void {
		$graph   = new CacheDependencyGraph();
		$builder = new CacheDependencyBuilder( $graph );
		$builder->register_component( 'CUSTOM_PRODUCT_CARD', array( 'shop', 'product-category' ) );
		$builder->register_asset( 'gallery-js', array( 'npe-product-gallery' ) );

		$this->assertContains( 'page_type:shop', $graph->resolve( array( 'component:custom_product_card' ) )['nodes'] );
		$this->assertContains( 'bundle:npe-product-gallery', $graph->resolve( array( 'asset:gallery-js' ) )['nodes'] );
	}
}
