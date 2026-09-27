<?php
/**
 * Builds WordPress and WooCommerce relationships without private APIs.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheDependencyBuilder {
	/** @var CacheDependencyGraph */ private $graph;

	public function __construct( CacheDependencyGraph $graph ) {
		$this->graph = $graph;
	}

	public function register_post( int $post_id ): array {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}
		$post_type = function_exists( 'get_post_type' ) ? sanitize_key( (string) get_post_type( $post_id ) ) : 'post';
		$url       = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
		$page_node = 'page:' . $post_id;
		$this->graph->register_node( $page_node, array( 'tags' => array( 'post-' . $post_id ), 'urls' => array( $url ), 'warm_urls' => array( $url ) ) );

		if ( 'product' !== $post_type ) {
			$root = 'post:' . $post_id;
			$this->graph->register_node( $root, array( 'tags' => array( 'post-' . $post_id ) ) );
			$this->graph->connect( $root, $page_node );
			return array( $root );
		}

		$root = 'product:' . $post_id;
		$this->graph->register_node( $root, array( 'tags' => array( 'product-' . $post_id, 'post-' . $post_id ) ) );
		$this->graph->connect( $root, $page_node );
		$this->register_page_type( 'shop', $this->shop_url() );
		$this->graph->connect( $root, 'page_type:shop' );
		$this->graph->register_node( 'component:related-products', array( 'tags' => array( 'component-related-products' ) ) );
		$this->graph->connect( $root, 'component:related-products' );

		if ( function_exists( 'wp_get_post_terms' ) ) {
			$terms = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term_id ) {
					$this->register_taxonomy( 'product_cat', absint( $term_id ) );
					$this->graph->connect( $root, 'taxonomy:product_cat:' . absint( $term_id ) );
				}
			}
		}

		if ( apply_filters( 'npe/cache/product_affects_homepage', false, $post_id ) ) {
			$this->register_page_type( 'home', home_url( '/' ) );
			$this->graph->connect( $root, 'page_type:home' );
		}

		return array( $root );
	}

	public function register_taxonomy( string $taxonomy, int $term_id ): array {
		$taxonomy = sanitize_key( $taxonomy );
		$term_id  = absint( $term_id );
		if ( '' === $taxonomy || ! $term_id ) {
			return array();
		}
		$node = 'taxonomy:' . $taxonomy . ':' . $term_id;
		$url  = function_exists( 'get_term_link' ) ? get_term_link( $term_id, $taxonomy ) : '';
		$url  = is_wp_error( $url ) ? '' : $url;
		$tag_prefix = 'product_cat' === $taxonomy ? 'product-category' : ( 'product_tag' === $taxonomy ? 'product-tag' : $taxonomy );
		$this->graph->register_node( $node, array( 'tags' => array( $tag_prefix . '-' . $term_id ), 'urls' => array( $url ), 'warm_urls' => array( $url ) ) );
		$this->register_page_type( 'product-listing', '' );
		$this->graph->connect( $node, 'page_type:product-listing' );
		return array( $node );
	}

	public function register_component( string $component_id, array $page_types = array() ): array {
		$component_id = sanitize_key( $component_id );
		if ( '' === $component_id ) {
			return array();
		}
		$node = 'component:' . $component_id;
		$this->graph->register_node( $node, array( 'tags' => array( 'component-' . $component_id ) ) );
		foreach ( $page_types as $page_type ) {
			$page_type = sanitize_key( (string) $page_type );
			if ( '' !== $page_type ) {
				$this->register_page_type( $page_type, '' );
				$this->graph->connect( $node, 'page_type:' . $page_type );
			}
		}
		return array( $node );
	}

	public function register_asset( string $asset_handle, array $bundle_ids ): array {
		$asset_handle = sanitize_key( $asset_handle );
		if ( '' === $asset_handle ) {
			return array();
		}
		$node = 'asset:' . $asset_handle;
		$this->graph->register_node( $node, array( 'tags' => array( 'asset-' . $asset_handle ) ) );
		foreach ( $bundle_ids as $bundle_id ) {
			$bundle_id = sanitize_key( (string) $bundle_id );
			$this->graph->register_node( 'bundle:' . $bundle_id, array( 'tags' => array( 'bundle-' . $bundle_id ) ) );
			$this->graph->connect( $node, 'bundle:' . $bundle_id );
		}
		return array( $node );
	}

	private function register_page_type( string $page_type, string $url ): void {
		$page_type = sanitize_key( $page_type );
		$this->graph->register_node( 'page_type:' . $page_type, array( 'tags' => array( 'page-type-' . $page_type ), 'urls' => array( $url ), 'warm_urls' => array( $url ) ) );
	}

	private function shop_url(): string {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return (string) wc_get_page_permalink( 'shop' );
		}
		return function_exists( 'get_post_type_archive_link' ) ? (string) get_post_type_archive_link( 'product' ) : '';
	}
}
