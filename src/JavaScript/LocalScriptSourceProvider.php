<?php
/**
 * Safe local WordPress JavaScript source reader.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class LocalScriptSourceProvider {
	public const MAX_SOURCE_BYTES = 5242880;

	public function load( array $assets ): array {
		$contents = array();
		$hashes   = array();

		foreach ( $assets as $asset ) {
			if ( ! $asset instanceof ScriptAsset || $asset->has_runtime_data() ) {
				continue;
			}

			$file = $this->resolve( $asset->source() );
			if ( '' === $file || ! is_readable( $file ) || filesize( $file ) > self::MAX_SOURCE_BYTES ) {
				continue;
			}

			// Read is bounded and restricted to a registered local JavaScript file.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$content = file_get_contents( $file );
			if ( false === $content ) {
				continue;
			}

			$contents[ $asset->handle() ] = $content;
			$hashes[ $asset->handle() ]   = hash( 'sha256', $content );
		}

		return array( 'contents' => $contents, 'hashes' => $hashes );
	}

	private function resolve( string $source ): string {
		$path = wp_parse_url( $source, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path || '.js' !== strtolower( substr( $path, -3 ) ) ) {
			return '';
		}

		$site_host   = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$source_host = wp_parse_url( $source, PHP_URL_HOST );
		$is_external = is_string( $source_host )
			&& is_string( $site_host )
			&& strtolower( $source_host ) !== strtolower( $site_host );
		if ( $is_external ) {
			return '';
		}

		$home_path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( is_string( $home_path ) && '/' !== $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		$root      = realpath( ABSPATH );
		$candidate = realpath( trailingslashit( ABSPATH ) . ltrim( rawurldecode( $path ), '/' ) );
		if ( false === $root || false === $candidate || 0 !== strpos( $candidate, trailingslashit( $root ) ) ) {
			return '';
		}

		return $candidate;
	}
}
