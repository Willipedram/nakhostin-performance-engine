<?php
/** Smart purge dependency graph tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use PHPUnit\Framework\TestCase;

final class CacheDependencyGraphTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_transitive_product_relationships_are_resolved_once(): void {
		$graph = new CacheDependencyGraph();
		$graph->register_node( 'product:123', array( 'tags' => array( 'product-123' ) ) );
		$graph->register_node( 'page:123', array( 'tags' => array( 'post-123' ), 'urls' => array( 'https://example.test/product/123/' ), 'warm_urls' => array( 'https://example.test/product/123/' ) ) );
		$graph->register_node( 'taxonomy:product_cat:7', array( 'tags' => array( 'product-category-7' ), 'urls' => array( 'https://example.test/product-category/7/' ) ) );
		$graph->register_node( 'page_type:shop', array( 'tags' => array( 'page-type-shop' ), 'urls' => array( 'https://example.test/product/' ) ) );
		$graph->connect( 'product:123', 'page:123' );
		$graph->connect( 'product:123', 'taxonomy:product_cat:7' );
		$graph->connect( 'taxonomy:product_cat:7', 'page_type:shop' );
		$graph->connect( 'page_type:shop', 'product:123' );

		$result = $graph->resolve( array( 'product:123' ) );
		$this->assertCount( 4, $result['nodes'] );
		$this->assertSame( array( 'product-123', 'post-123', 'product-category-7', 'page-type-shop' ), $result['tags'] );
		$this->assertFalse( $result['truncated'] );
	}

	public function test_component_and_asset_relationships_are_supported(): void {
		$graph = new CacheDependencyGraph();
		$graph->register_node( 'component:product-card', array( 'tags' => array( 'component-product-card' ) ) );
		$graph->register_node( 'page_type:shop', array( 'tags' => array( 'page-type-shop' ) ) );
		$graph->register_node( 'asset:gallery-js', array( 'tags' => array( 'asset-gallery-js' ) ) );
		$graph->register_node( 'bundle:npe-gallery', array( 'tags' => array( 'bundle-npe-gallery' ) ) );
		$graph->connect( 'component:product-card', 'page_type:shop' );
		$graph->connect( 'asset:gallery-js', 'bundle:npe-gallery' );

		$this->assertContains( 'page-type-shop', $graph->resolve( array( 'component:product-card' ) )['tags'] );
		$this->assertContains( 'bundle:npe-gallery', $graph->resolve( array( 'asset:gallery-js' ) )['nodes'] );
	}
}
