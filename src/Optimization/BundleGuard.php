<?php
/** Verifies generated assets and their relationship to the live WordPress queue. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Optimization;

final class BundleGuard {
	public function available( array $bundle, string $type, string $directory, string $base_url ): bool {
		$extension = 'css' === $type ? 'css' : 'js';
		$hash = $bundle['content_hash'] ?? null;
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{20}$/D', $hash ) || ( $bundle['status'] ?? '' ) !== 'generated' ) {
			return false;
		}
		$filename = 'page-' . $hash . '.' . $extension;
		if ( ( $bundle['filename'] ?? '' ) !== $filename || ( $bundle['url'] ?? '' ) !== trailingslashit( $base_url ) . $filename ) {
			return false;
		}

		$file = trailingslashit( $directory ) . $filename;
		$size = $bundle['optimized_size'] ?? null;
		return is_int( $size ) && $size > 0 && is_file( $file ) && ! is_link( $file ) && is_readable( $file ) && filesize( $file ) === $size;
	}

	/**
	 * Replacing a block appends a handle to the WordPress queue. Require it to
	 * be a suffix so the asset keeps its relative order against unbundled files.
	 *
	 * @param object $manager WordPress script or stylesheet dependency manager.
	 * @param string[] $handles Ordered source handles.
	 * @param string $type Asset type.
	 */
	public function can_replace( $manager, array $handles, string $type ): bool {
		if ( ! $handles || ! is_object( $manager ) || ! isset( $manager->queue, $manager->registered, $manager->done ) ) {
			return false;
		}
		if ( isset( $manager->registered['npe-page-bundle'] ) ) {
			return false;
		}
		$queue = array_values( array_diff( $manager->queue, $manager->done ) );
		if ( array_slice( $queue, -count( $handles ) ) !== $handles || count( array_unique( $handles ) ) !== count( $handles ) ) {
			return false;
		}

		foreach ( $handles as $handle ) {
			$asset = $manager->registered[ $handle ] ?? null;
			if ( ! is_object( $asset ) || empty( $asset->src ) || isset( $manager->args[ $handle ] ) || array_diff_key( (array) $asset->extra, array( 'group' => true ) ) || ! empty( $asset->textdomain ) || array_diff( $asset->deps, array_slice( $queue, 0, array_search( $handle, $queue, true ) ), $manager->done ) ) {
				return false;
			}
			if ( 'css' === $type && ! in_array( $asset->args, array( '', null, 'all' ), true ) ) {
				return false;
			}
			if ( 'javascript' === $type && 1 !== (int) $manager->get_data( $handle, 'group' ) ) {
				return false;
			}
		}

		foreach ( $manager->registered as $handle => $asset ) {
			if ( ! in_array( $handle, $handles, true ) && array_intersect( (array) $asset->deps, $handles ) ) {
				return false;
			}
		}
		return true;
	}

	/** Normalizes a registered URL and retains the query for version matching. */
	public function source( string $url, string $base ): string {
		if ( '' === $url ) {
			return '';
		}
		$url = class_exists( '\WP_Http' ) ? \WP_Http::make_absolute_url( $url, $base ) : $url;
		return esc_url_raw( $url );
	}
}
