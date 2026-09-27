<?php
/** Opt-in runtime replacement of analyzed font-only stylesheets. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class FontLoadingOptimizer {
	/** @var FontStorage */ private $storage;
	/** @var Settings */ private $settings;

	public function __construct( FontStorage $storage, Settings $settings ) { $this->storage = $storage; $this->settings = $settings; }

	public function register(): void {
		add_action( 'npe/cache/purged', array( $this, 'cache_purged' ), 15, 2 );
		add_action( 'litespeed_purge_all', array( $this, 'litespeed_purge_all' ), 15 );
		add_action( 'litespeed_purge_url', array( $this, 'litespeed_purge_url' ), 15, 1 );
		if ( ! $this->settings->get( 'fonts.enabled', false ) ) { return; }
		add_action( 'wp_enqueue_scripts', array( $this, 'optimize' ), PHP_INT_MAX );
		add_filter( 'wp_preload_resources', array( $this, 'filter_preloads' ), 20 );
	}

	public function cache_purged( string $scope, array $values ): void {
		if ( 'all' === $scope ) { $this->storage->flush(); return; }
		if ( 'urls' === $scope ) { foreach ( $values as $url ) { $this->storage->invalidate_url( (string) $url ); } }
	}

	public function litespeed_purge_all(): void { $this->storage->flush(); }
	public function litespeed_purge_url( string $url = '' ): void { if ( '' !== $url ) { $this->storage->invalidate_url( $url ); } }

	public function optimize(): void {
		if ( is_admin() || is_user_logged_in() || ! function_exists( 'wp_dequeue_style' ) || ! apply_filters( 'npe/fonts/optimization_enabled', true ) ) { return; }
		$manifest = $this->current_manifest();
		if ( null === $manifest ) { return; }
		$data = $manifest->to_array();
		global $wp_styles;
		if ( ! is_object( $wp_styles ) || ! isset( $wp_styles->registered ) ) { return; }
		$inline = '';
		foreach ( (array) ( $data['stylesheets'] ?? array() ) as $stylesheet ) {
			if ( empty( $stylesheet['font_only'] ) || empty( $stylesheet['url'] ) ) { continue; }
			foreach ( $wp_styles->registered as $handle => $registered ) {
				if ( $this->normalize_asset_url( (string) ( $registered->src ?? '' ) ) !== $this->normalize_asset_url( (string) $stylesheet['url'] ) ) { continue; }
				if ( ! empty( $registered->extra['after'] ) || ! empty( $registered->extra['before'] ) || ! empty( $registered->extra['conditional'] ) ) { continue; }
				foreach ( (array) ( $stylesheet['faces'] ?? array() ) as $face ) {
					if ( ! empty( $face['required'] ) ) { $inline .= $this->face_css( $face ); }
				}
				wp_dequeue_style( (string) $handle );
			}
		}
		if ( '' !== $inline && function_exists( 'wp_register_style' ) && function_exists( 'wp_add_inline_style' ) ) {
			wp_register_style( 'npe-required-fonts', false, array(), defined( 'NPE_VERSION' ) ? NPE_VERSION : null );
			wp_enqueue_style( 'npe-required-fonts' );
			wp_add_inline_style( 'npe-required-fonts', $inline );
		}
	}

	public function filter_preloads( array $resources ): array {
		$manifest = $this->current_manifest();
		if ( null === $manifest ) { return $resources; }
		$data = $manifest->to_array();
		$unused = array_fill_keys( array_map( array( $this, 'normalize_asset_url' ), (array) ( $data['unused_sources'] ?? array() ) ), true );
		$required = array_fill_keys( array_map( array( $this, 'normalize_asset_url' ), (array) ( $data['required_sources'] ?? array() ) ), true );
		return array_values( array_filter( $resources, function ( $resource ) use ( $unused, $required ): bool {
			$url = is_array( $resource ) ? (string) ( $resource['href'] ?? '' ) : (string) $resource;
			$key = $this->normalize_asset_url( $url );
			return ! isset( $unused[ $key ] ) || isset( $required[ $key ] );
		} ) );
	}

	private function current_manifest(): ?FontManifest {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		return $this->storage->for_url( home_url( '/' . ltrim( $path, '/' ) ) );
	}

	private function face_css( array $face ): string {
		$family = preg_replace( '/[^\p{L}\p{N} _-]/u', '', (string) ( $face['family'] ?? '' ) ) ?? '';
		if ( '' === $family ) { return ''; }
		$sources = array();
		foreach ( (array) ( $face['sources'] ?? array() ) as $source ) {
			$url = esc_url_raw( (string) ( $source['url'] ?? '' ), array( 'http', 'https' ) );
			$format = sanitize_key( (string) ( $source['format'] ?? '' ) );
			if ( '' !== $url && ! preg_match( '/[\x00-\x20\x7f\"\'()\\\\]/', $url ) && in_array( $format, array( 'woff2', 'woff', 'woff2-variations', 'woff-variations', 'truetype', 'opentype' ), true ) ) { $sources[] = 'url("' . $url . '") format("' . $format . '")'; }
		}
		if ( ! $sources ) { return ''; }
		$weight = preg_replace( '/[^0-9 ]/', '', (string) ( $face['weight'] ?? '400' ) ) ?: '400';
		$style = in_array( $face['style'] ?? '', array( 'normal', 'italic', 'oblique' ), true ) ? $face['style'] : 'normal';
		$display = in_array( $face['display'] ?? '', array( 'auto', 'block', 'swap', 'fallback', 'optional' ), true ) ? $face['display'] : 'swap';
		$unicode_range = preg_replace( '/[^uU+0-9a-fA-F?\-, ]/', '', (string) ( $face['unicode_range'] ?? '' ) ) ?? '';
		return '@font-face{font-family:"' . $family . '";font-style:' . $style . ';font-weight:' . $weight . ';font-display:' . $display . ';src:' . implode( ',', $sources ) . ';' . ( '' !== $unicode_range ? 'unicode-range:' . $unicode_range . ';' : '' ) . '}';
	}

	private function normalize_asset_url( string $url ): string {
		if ( '' === $url ) { return ''; }
		if ( ! wp_parse_url( $url, PHP_URL_HOST ) ) { $url = home_url( '/' . ltrim( $url, '/' ) ); }
		return preg_replace( '/[?#].*$/', '', esc_url_raw( $url ) ) ?? '';
	}
}
