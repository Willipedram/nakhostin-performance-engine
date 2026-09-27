<?php
/**
 * Conservative semantic component detector.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

use DOMElement;
use DOMNodeList;
use DOMXPath;

final class DOMComponentDetector {
	public function detect( DOMXPath $xpath ): array {
		$components = array();

		$rules = array(
			'header'                   => '//header | //*[@role="banner"] | '
				. '//*[contains(concat(" ", normalize-space(@class), " "), " site-header ")]',
			'footer'                   => '//footer | //*[@role="contentinfo"] | '
				. '//*[contains(concat(" ", normalize-space(@class), " "), " site-footer ")]',
			'navigation'               => '//nav | //*[@role="navigation"]',
			'mobile-menu'              => '//*[contains(@class,"mobile-menu") or contains(@class,"mobile_menu") '
				. 'or contains(@class,"offcanvas-menu")]',
			'product-card'             => '//*[contains(concat(" ",normalize-space(@class)," ")," product ") '
				. 'and (self::li or contains(@class,"product-card"))]',
			'product-grid'             => '//*[contains(concat(" ",normalize-space(@class)," ")," products ") '
				. 'or contains(@class,"product-grid")]',
			'product-gallery'          => '//*[contains(@class,"woocommerce-product-gallery") '
				. 'or contains(@class,"product-gallery")]',
			'product-filters'          => '//*[contains(@class,"product-filter") '
				. 'or contains(@class,"woocommerce-widget-layered-nav")]',
			'product-sorting'          => '//*[contains(@class,"woocommerce-ordering") or contains(@class,"product-sorting")]',
			'cart-fragments'           => '//*[contains(@class,"widget_shopping_cart") or contains(@class,"mini-cart") '
				. 'or contains(@class,"cart-fragment")]',
			'product-variations'       => '//*[contains(concat(" ",normalize-space(@class)," ")," variations ") '
				. 'or @data-product_variations]',
			'reviews'                  => '//*[@id="reviews" or contains(concat(" ",normalize-space(@class)," ")," reviews ")]',
			'wishlist'                 => '//*[contains(@class,"wishlist") or @data-wishlist]',
			'modal'                    => '//*[@role="dialog" or @aria-modal="true" or contains(@class,"modal")]',
			'component-like-structure' => '//*[@data-component or @data-widget or contains(name(),"-")]',
		);

		foreach ( $rules as $name => $query ) {
			if ( $this->has_nodes( $xpath->query( $query ) ) ) {
				$components[] = $name;
			}
		}

		$widgets = $this->elementor_widgets( $xpath );
		if ( ! empty( $widgets ) ) {
			$components[] = 'elementor-widgets';
		}

		$woocommerce = $xpath->query(
			'//*[contains(@class,"woocommerce") or contains(@class,"wc-") or @data-product_id or @data-product-id]'
		);
		if ( $this->has_nodes( $woocommerce ) ) {
			$components[] = 'woocommerce-components';
		}

		sort( $components, SORT_STRING );

		return array(
			'components'        => array_values( array_unique( $components ) ),
			'elementor_widgets' => $widgets,
		);
	}

	private function elementor_widgets( DOMXPath $xpath ): array {
		$widgets = array();
		$nodes   = $xpath->query( '//*[contains(@class,"elementor-widget-")]' );

		if ( ! $nodes instanceof DOMNodeList ) {
			return $widgets;
		}

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof DOMElement ) {
				continue;
			}

			foreach ( preg_split( '/\s+/', $node->getAttribute( 'class' ) ) ?: array() as $class_name ) {
				if ( 1 === preg_match( '/^elementor-widget-([a-z0-9_-]+)$/i', $class_name, $matches ) ) {
					$widgets[] = strtolower( $matches[1] );
				}
			}
		}

		sort( $widgets, SORT_STRING );

		return array_values( array_unique( $widgets ) );
	}

	private function has_nodes( $nodes ): bool {
		return $nodes instanceof DOMNodeList && $nodes->length > 0;
	}
}
