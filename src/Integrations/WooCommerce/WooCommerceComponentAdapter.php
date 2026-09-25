<?php
/**
 * Public-signal-only WooCommerce component adapter.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\WooCommerce;

use Nakhostin\PerformanceEngine\Components\ComponentAdapterInterface;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;

final class WooCommerceComponentAdapter implements ComponentAdapterInterface {
	/** @var bool|null */
	private $availability;

	/** @var bool */
	private $enabled;

	public function __construct( ?bool $availability = null, bool $enabled = true ) {
		$this->availability = $availability;
		$this->enabled      = $enabled;
	}

	public function get_id(): string {
		return 'woocommerce';
	}

	public function is_available(): bool {
		return null !== $this->availability
			? $this->availability
			: class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
	}

	public function is_enabled(): bool {
		return $this->enabled && $this->is_available();
	}

	public function register(): void {
		// Read-only adapter; no third-party hooks are required.
	}

	public function inspect( array $manifest ): array {
		if ( ! $this->is_enabled() ) {
			return array( 'page_types' => array(), 'components' => array(), 'metadata' => array() );
		}

		$body_classes = $manifest['body_classes'] ?? array();
		$page_types   = array();
		$mapping      = array(
			'woocommerce-shop'        => 'shop',
			'post-type-archive-product' => 'shop',
			'tax-product_cat'          => 'product-category',
			'tax-product_tag'          => 'product-tag',
			'single-product'           => 'single-product',
			'woocommerce-cart'         => 'cart',
			'woocommerce-checkout'     => 'checkout',
			'woocommerce-account'      => 'account',
		);

		foreach ( $mapping as $class_name => $page_type ) {
			if ( in_array( $class_name, $body_classes, true ) ) {
				$page_types[] = $page_type;
			}
		}

		$definitions = array(
			'product-card'       => array(
				'WC_PRODUCT_CARD',
				__( 'Product Card', 'nakhostin-performance-engine' ),
				array( '.products .product' ),
			),
			'product-grid'       => array(
				'WC_PRODUCT_GRID',
				__( 'Product Grid', 'nakhostin-performance-engine' ),
				array( '.products' ),
			),
			'product-gallery'    => array(
				'WC_PRODUCT_GALLERY',
				__( 'Product Gallery', 'nakhostin-performance-engine' ),
				array( '.woocommerce-product-gallery' ),
			),
			'product-filters'    => array(
				'WC_PRODUCT_FILTER',
				__( 'Product Filter', 'nakhostin-performance-engine' ),
				array( '.woocommerce-widget-layered-nav' ),
			),
			'product-sorting'    => array(
				'WC_PRODUCT_SORTING',
				__( 'Product Sorting', 'nakhostin-performance-engine' ),
				array( '.woocommerce-ordering' ),
			),
			'cart-fragments'     => array(
				'WC_MINI_CART',
				__( 'Mini Cart', 'nakhostin-performance-engine' ),
				array( '.widget_shopping_cart', '.mini-cart' ),
			),
			'product-variations' => array(
				'WC_PRODUCT_VARIATION',
				__( 'Product Variation', 'nakhostin-performance-engine' ),
				array( '.variations_form' ),
			),
			'reviews'            => array(
				'WC_REVIEWS',
				__( 'Reviews', 'nakhostin-performance-engine' ),
				array( '#reviews' ),
			),
			'wishlist'           => array(
				'WC_WISHLIST',
				__( 'Wishlist', 'nakhostin-performance-engine' ),
				array( '.wishlist' ),
			),
		);
		$components  = array();

		foreach ( $definitions as $detected => $definition ) {
			if ( ! in_array( $detected, $manifest['components'] ?? array(), true ) ) {
				continue;
			}

			$components[] = new ComponentDefinition(
				array(
					'id'                        => $definition[0],
					'name'                      => $definition[1],
					'selectors'                 => $definition[2],
					'dynamic_states'            => $this->states_for( $detected ),
					'cache_behavior'            => 'cart-fragments' === $detected ? 'private' : 'shared',
					'invalidation_dependencies' => array( 'woocommerce-products' ),
					'integration_owner'         => 'woocommerce',
				)
			);
		}

		return array(
			'page_types' => array_values( array_unique( $page_types ) ),
			'components' => $components,
			'metadata'   => array( 'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '' ),
		);
	}

	private function states_for( string $component ): array {
		if ( 'product-variations' === $component ) {
			return array( 'variation-selected', 'loading' );
		}

		if ( 'product-filters' === $component ) {
			return array( 'filter-open', 'loading', 'ajax-result' );
		}

		return 'cart-fragments' === $component ? array( 'loading', 'active' ) : array();
	}
}
