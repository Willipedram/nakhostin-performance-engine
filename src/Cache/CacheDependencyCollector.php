<?php
/**
 * Collects structural dependency tags for a rendered WordPress response.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheDependencyCollector {
	public function collect(): array {
		$dependencies = array();
		$post_id      = function_exists( 'get_queried_object_id' ) && ( ! function_exists( 'is_singular' ) || is_singular() ) ? absint( get_queried_object_id() ) : 0;
		if ( $post_id ) {
			$dependencies[] = 'post-' . $post_id;
			$post_type      = function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post_id ) ) : '';
			if ( 'product' === $post_type ) {
				$dependencies[] = 'product-' . $post_id;
				if ( function_exists( 'wp_get_post_terms' ) ) {
					$terms = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
					if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
						foreach ( $terms as $term_id ) {
							$dependencies[] = 'product-category-' . absint( $term_id );
						}
					}
				}
			}
		}
		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			$dependencies[] = 'page-type-home';
		}
		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$dependencies[] = 'page-type-shop';
		}
		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$dependencies[] = 'page-type-product-category';
			$term_id        = function_exists( 'get_queried_object_id' ) ? absint( get_queried_object_id() ) : 0;
			if ( $term_id ) {
				$dependencies[] = 'product-category-' . $term_id;
			}
		}
		if ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			$dependencies[] = 'page-type-product-tag';
		}

		/**
		 * Filters dependency tags stored with the current public page.
		 *
		 * Integrations may add component, taxonomy, asset, or bundle tags. They
		 * must not include personal or session-specific values.
		 *
		 * @param array $dependencies Dependency tags.
		 */
		$dependencies = apply_filters( 'npe/cache/response_dependencies', $dependencies );
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $dependencies ) ? $dependencies : array() ) ) ) );
	}
}
