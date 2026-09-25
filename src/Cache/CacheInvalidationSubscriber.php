<?php
/**
 * Maps WordPress and WooCommerce changes to asynchronous smart-purge requests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Components\ComponentStorage;

final class CacheInvalidationSubscriber {
	/** @var SmartPurgeManager */ private $purges;
	/** @var CacheDependencyBuilder */ private $builder;
	/** @var ComponentStorage */ private $components;

	public function __construct( SmartPurgeManager $purges, CacheDependencyBuilder $builder, ComponentStorage $components ) {
		$this->purges     = $purges;
		$this->builder    = $builder;
		$this->components = $components;
	}

	public function register(): void {
		add_action( 'save_post', array( $this, 'post_changed' ) );
		add_action( 'before_delete_post', array( $this, 'post_deleted' ) );
		add_action( 'trashed_post', array( $this, 'post_deleted' ) );
		add_action( 'edited_term', array( $this, 'term_changed' ), 10, 3 );
		add_action( 'wp_update_nav_menu', array( $this, 'menu_changed' ) );
		add_action( 'woocommerce_update_product', array( $this, 'product_changed' ) );
		add_action( 'woocommerce_product_set_stock', array( $this, 'product_object_changed' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'product_object_changed' ) );
		add_action( 'woocommerce_product_object_updated_props', array( $this, 'product_properties_changed' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'theme_changed' ) );
		add_action( 'upgrader_process_complete', array( $this, 'code_changed' ), 10, 2 );
		add_action( 'update_option_npe_settings', array( $this, 'settings_changed' ), 10, 2 );
		add_action( 'update_option_npe_custom_components', array( $this, 'components_changed' ), 10, 2 );
		add_action( 'npe/cache/asset_changed', array( $this, 'asset_changed' ), 10, 2 );
		add_action( 'npe/cache/bundle_indexed', array( $this, 'bundle_indexed' ) );
	}

	public function post_changed( int $post_id ): void {
		if ( $post_id < 1 || ( function_exists( 'wp_is_post_revision' ) && wp_is_post_revision( $post_id ) ) || ( function_exists( 'wp_is_post_autosave' ) && wp_is_post_autosave( $post_id ) ) ) {
			return;
		}
		$post_type = function_exists( 'get_post_type' ) ? get_post_type( $post_id ) : '';
		if ( 'product' === $post_type ) {
			$this->product_changed( $post_id );
			return;
		}
		$this->builder->register_post( $post_id );
		$this->purges->request( PurgeRequest::create( 'page', $post_id, 'post_saved' ) );
	}

	public function post_deleted( int $post_id ): void {
		if ( $post_id > 0 ) {
			$this->builder->register_post( $post_id );
			$this->purges->request( PurgeRequest::create( 'page', $post_id, 'post_removed' ) );
		}
	}

	public function product_changed( int $product_id ): void {
		if ( $product_id > 0 ) {
			$this->builder->register_post( $product_id );
			$this->purges->request( PurgeRequest::create( 'product', $product_id, 'product_changed' ) );
		}
	}

	public function product_object_changed( $product ): void {
		if ( is_object( $product ) && is_callable( array( $product, 'get_id' ) ) ) {
			$this->product_changed( absint( $product->get_id() ) );
		}
	}

	public function product_properties_changed( $product, array $properties ): void {
		$relevant = array( 'price', 'regular_price', 'sale_price', 'stock_quantity', 'stock_status' );
		if ( array_intersect( $relevant, $properties ) ) {
			$this->product_object_changed( $product );
		}
	}

	public function term_changed( int $term_id, int $term_taxonomy_id, string $taxonomy ): void {
		unset( $term_taxonomy_id );
		$this->builder->register_taxonomy( $taxonomy, $term_id );
		$this->purges->request( PurgeRequest::create( 'taxonomy', $taxonomy . ':' . $term_id, 'term_edited' ) );
	}

	public function menu_changed( int $menu_id ): void {
		$this->builder->register_component( 'navigation', array( 'home' ) );
		$this->purges->request( PurgeRequest::create( 'component', 'navigation', 'navigation_changed' ) );
	}

	public function components_changed( $old_value, $new_value ): void {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();
		$usage     = $this->components->usage();
		foreach ( array_unique( array_merge( array_keys( $old_value ), array_keys( $new_value ) ) ) as $component_id ) {
			$page_types = (array) ( $usage[ $component_id ]['page_types'] ?? array() );
			$this->builder->register_component( (string) $component_id, $page_types );
			$this->purges->request( PurgeRequest::create( 'component', $component_id, 'component_definition_changed' ) );
		}
	}

	public function asset_changed( string $asset_handle, array $bundle_ids = array() ): void {
		$this->builder->register_asset( $asset_handle, $bundle_ids );
		$this->purges->request( PurgeRequest::create( 'asset', $asset_handle, 'asset_changed' ) );
	}

	public function bundle_indexed( array $bundle ): void {
		$bundle_id = sanitize_key( (string) ( $bundle['bundle_id'] ?? '' ) );
		if ( '' === $bundle_id ) {
			return;
		}
		$assets = array_merge( (array) ( $bundle['css_dependencies'] ?? array() ), (array) ( $bundle['js_dependencies'] ?? array() ) );
		foreach ( $assets as $asset ) {
			$this->builder->register_asset( (string) $asset, array( $bundle_id ) );
		}
	}

	public function settings_changed( $old_value, $new_value ): void {
		$old_cache = is_array( $old_value ) ? (array) ( $old_value['cache'] ?? array() ) : array();
		$new_cache = is_array( $new_value ) ? (array) ( $new_value['cache'] ?? array() ) : array();
		if ( $old_cache !== $new_cache ) {
			$this->purges->request( PurgeRequest::create( 'full', 'all', 'cache_settings_changed' ) );
		}
	}

	public function theme_changed(): void {
		$this->purges->request( PurgeRequest::create( 'full', 'all', 'theme_changed' ) );
	}

	public function code_changed( $upgrader = null, array $options = array() ): void {
		unset( $upgrader );
		if ( 'update' !== ( $options['action'] ?? '' ) || ! in_array( $options['type'] ?? '', array( 'plugin', 'theme' ), true ) ) {
			return;
		}
		$this->purges->request( PurgeRequest::create( 'full', 'all', 'plugin_or_theme_updated' ) );
	}
}
