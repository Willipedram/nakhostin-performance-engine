<?php
/**
 * Component integration adapter tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Integrations\Elementor\ElementorComponentAdapter;
use Nakhostin\PerformanceEngine\Integrations\WooCommerce\WooCommerceComponentAdapter;
use Nakhostin\PerformanceEngine\Integrations\WoodMart\WoodMartComponentAdapter;
use PHPUnit\Framework\TestCase;

final class ComponentIntegrationsTest extends TestCase {
	public function test_woocommerce_page_type_and_components_are_detected(): void {
		$adapter = new WooCommerceComponentAdapter( true );
		$result  = $adapter->inspect(
			array(
				'body_classes' => array( 'single-product' ),
				'components'   => array( 'product-gallery', 'product-variations', 'cart-fragments' ),
			)
		);

		$this->assertContains( 'single-product', $result['page_types'] );
		$this->assertSame(
			array( 'WC_PRODUCT_GALLERY', 'WC_MINI_CART', 'WC_PRODUCT_VARIATION' ),
			array_map( static function ( $component ) { return $component->id(); }, $result['components'] )
		);
		$this->assertSame( 'private', $result['components'][1]->to_array()['cache_behavior'] );
	}

	public function test_elementor_widgets_templates_and_generated_styles_are_detected(): void {
		$adapter = new ElementorComponentAdapter( true );
		$result  = $adapter->inspect(
			array(
				'body_classes'      => array( 'elementor-page', 'elementor-template-canvas' ),
				'elementor_widgets' => array( 'heading', 'button' ),
				'stylesheets'       => array( '/wp-content/uploads/elementor/css/post-10.css', '/theme.css' ),
			)
		);

		$this->assertSame( array( 'elementor-page' ), $result['page_types'] );
		$this->assertTrue( $result['metadata']['is_template'] );
		$this->assertSame(
			array( '/wp-content/uploads/elementor/css/post-10.css' ),
			$result['metadata']['generated_styles']
		);
		$this->assertCount( 2, $result['components'] );

		$other_page = $adapter->inspect(
			array(
				'body_classes'      => array( 'elementor-page' ),
				'elementor_widgets' => array( 'heading' ),
				'stylesheets'       => array( '/wp-content/uploads/elementor/css/post-99.css' ),
			)
		);
		$this->assertSame( $result['components'][0]->signature(), $other_page['components'][0]->signature() );
	}

	public function test_all_woocommerce_page_types_are_mapped(): void {
		$mapping = array(
			'woocommerce-shop'         => 'shop',
			'tax-product_cat'           => 'product-category',
			'tax-product_tag'           => 'product-tag',
			'single-product'            => 'single-product',
			'woocommerce-cart'          => 'cart',
			'woocommerce-checkout'      => 'checkout',
			'woocommerce-account'       => 'account',
		);
		$adapter = new WooCommerceComponentAdapter( true );

		foreach ( $mapping as $body_class => $page_type ) {
			$result = $adapter->inspect( array( 'body_classes' => array( $body_class ) ) );
			$this->assertContains( $page_type, $result['page_types'] );
		}
	}

	public function test_missing_integrations_are_safe_no_ops(): void {
		$manifest = array( 'classes' => array( 'wd-product' ), 'body_classes' => array(), 'components' => array() );
		$adapters = array(
			new WooCommerceComponentAdapter( false ),
			new ElementorComponentAdapter( false ),
			new WoodMartComponentAdapter( false ),
		);

		foreach ( $adapters as $adapter ) {
			$this->assertFalse( $adapter->is_available() );
			$this->assertSame( array(), $adapter->inspect( $manifest )['components'] );
		}
	}

	public function test_available_adapter_can_be_disabled_independently(): void {
		$adapter = new WooCommerceComponentAdapter( true, false );

		$this->assertTrue( $adapter->is_available() );
		$this->assertFalse( $adapter->is_enabled() );
		$this->assertSame( array(), $adapter->inspect( array( 'components' => array( 'product-grid' ) ) )['components'] );
	}

	public function test_woodmart_uses_markup_without_private_classes(): void {
		$result = ( new WoodMartComponentAdapter( true ) )->inspect( array( 'classes' => array( 'wd-product' ) ) );

		$this->assertSame( 'WOODMART_STRUCTURE', $result['components'][0]->id() );
	}
}
