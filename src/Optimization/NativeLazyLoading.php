<?php
/** Opt-in WordPress-native media deferral. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Optimization;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class NativeLazyLoading {
	public static function register( Settings $settings ): void {
		if ( ! is_admin() && $settings->get( 'media.lazy_load', false ) ) {
			add_filter( 'wp_lazy_loading_enabled', array( self::class, 'enable' ), 99, 3 );
		}
	}

	public static function enable( bool $enabled, string $tag, string $context ): bool {
		if ( ! in_array( $tag, array( 'img', 'iframe' ), true ) || ! in_array( $context, array( 'the_content', 'widget_block_content', 'wp_get_attachment_image' ), true ) ) {
			return $enabled;
		}
		if ( is_user_logged_in() || is_feed() || is_preview() || ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_checkout' ) && is_checkout() ) || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
			return $enabled;
		}
		// Core still decides which elements to omit, and respects explicit
		// loading, dimensions, and high fetch priority.
		return true;
	}
}
