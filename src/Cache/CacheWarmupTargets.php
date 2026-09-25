<?php
/**
 * Configured important URLs for non-blocking warmup.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class CacheWarmupTargets {
	/** @var Settings */ private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function urls(): array {
		$urls = array();
		if ( $this->settings->get( 'cache.warm_homepage', true ) ) {
			$urls[] = home_url( '/' );
		}
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$urls[] = wc_get_page_permalink( 'shop' );
		}
		foreach ( (array) $this->settings->get( 'cache.important_product_ids', array() ) as $post_id ) {
			if ( function_exists( 'get_permalink' ) ) {
				$urls[] = get_permalink( absint( $post_id ) );
			}
		}
		foreach ( (array) $this->settings->get( 'cache.important_category_ids', array() ) as $term_id ) {
			if ( function_exists( 'get_term_link' ) ) {
				$url = get_term_link( absint( $term_id ), 'product_cat' );
				if ( ! is_wp_error( $url ) ) {
					$urls[] = $url;
				}
			}
		}
		return array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
	}
}
