<?php
/** Shared by full and early cache readers. No WordPress bootstrap is required. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;

final class GeneratedAssetReferences {
	public static function available( array $assets, ?string $content_dir = null ): bool {
		if ( ! $assets ) {
			return true;
		}
		$content_dir = $content_dir ?? ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '' );
		if ( '' === $content_dir || count( $assets ) > 2 ) {
			return false;
		}
		$base = rtrim( $content_dir, '/\\' ) . '/cache/nakhostin-performance-engine-assets/';
		foreach ( $assets as $relative => $size ) {
			if ( ! is_string( $relative ) || ! preg_match( '~^(?:css/page-[a-f0-9]{20}\.css|javascript/page-[a-f0-9]{20}\.js)$~D', $relative ) || ! is_int( $size ) || $size <= 0 ) {
				return false;
			}
			$path = $base . $relative;
			if ( ! is_file( $path ) || is_link( $path ) || ! is_readable( $path ) || filesize( $path ) !== $size ) {
				return false;
			}
		}
		return true;
	}
}
