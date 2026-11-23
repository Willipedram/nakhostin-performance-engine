<?php
/** Public delivery path for generated assets, separate from private cache records. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Optimization;

final class BundleLocation {
	private const FOLDER = '/cache/nakhostin-performance-engine-assets';

	public static function directory(): string {
		return rtrim( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : NPE_PATH . 'cache-data', '/' ) . self::FOLDER;
	}

	public static function url(): string {
		return rtrim( defined( 'WP_CONTENT_URL' ) ? WP_CONTENT_URL : NPE_URL . 'cache-data', '/' ) . self::FOLDER;
	}
}
