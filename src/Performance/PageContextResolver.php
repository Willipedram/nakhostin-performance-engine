<?php
/** Resolves low-cardinality page dimensions without retaining URLs or identifiers. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;

final class PageContextResolver {
	public function resolve(): array {
		$page_type = 'other';
		if ( function_exists( 'is_front_page' ) && is_front_page() ) { $page_type = 'home';
		} elseif ( function_exists( 'is_shop' ) && is_shop() ) { $page_type = 'shop';
		} elseif ( function_exists( 'is_product' ) && is_product() ) { $page_type = 'product';
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) { $page_type = 'product_category';
		} elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) { $page_type = 'product_tag';
		} elseif ( function_exists( 'is_cart' ) && is_cart() ) { $page_type = 'cart';
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) { $page_type = 'checkout';
		} elseif ( function_exists( 'is_account_page' ) && is_account_page() ) { $page_type = 'account';
		} elseif ( function_exists( 'is_singular' ) && is_singular() ) { $page_type = 'singular'; }

		$template = function_exists( 'get_page_template_slug' ) ? (string) get_page_template_slug() : '';
		$template = $template ? sanitize_key( pathinfo( $template, PATHINFO_FILENAME ) ) : 'default';
		$components = apply_filters( 'npe/performance/components', array() );
		return array( 'page_type' => sanitize_key( $page_type ), 'template' => $template, 'components' => is_array( $components ) ? $components : array() );
	}
}
