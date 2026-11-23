<?php
/** Builds content-addressed, page-specific CSS bundles during background analysis. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

use RuntimeException;

final class CSSBundleWriter {
	/** @var string */ private $directory;
	/** @var string */ private $base_url;
	public function __construct( string $directory, string $base_url ) { $this->directory = rtrim( $directory, '/\\' ); $this->base_url = trailingslashit( $base_url ); }

	public function build( array $files ): array {
		$parts = array(); $urls = array(); $original = 0;
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['url'] ) || ! isset( $file['css'] ) ) { continue; }
			$css = (string) $file['css']; $original += strlen( $css );
			// These directives depend on the original stylesheet boundary or URL.
			if ( preg_match( '/@(?:charset|import|namespace)\b|(?:image-set|src)\s*\(/i', $css ) || false !== strpos( $css, '\\' ) ) {
				throw new RuntimeException( 'Stylesheet uses syntax that requires its original URL or document boundary.' );
			}
			$parts[] = $this->rewrite_urls( $css, (string) $file['url'] );
			$urls[] = esc_url_raw( (string) $file['url'] );
		}
		if ( ! $parts ) { throw new RuntimeException( 'No safe CSS source was available for bundling.' ); }
		$content = implode( "\n", $parts ); $hash = substr( hash( 'sha256', $content ), 0, 20 ); $filename = 'page-' . $hash . '.css';
		$this->publish( $filename, $content );
		return array( 'url' => $this->base_url . $filename, 'filename' => $filename, 'content_hash' => $hash, 'source_urls' => $urls, 'original_size' => $original, 'optimized_size' => strlen( $content ) );
	}

	private function rewrite_urls( string $css, string $source_url ): string {
		$source = wp_parse_url( $source_url );
		if ( empty( $source['scheme'] ) || empty( $source['host'] ) ) {
			return $css;
		}
		$origin = $source['scheme'] . '://' . $source['host'];
		if ( isset( $source['port'] ) ) {
			$origin .= ':' . $source['port'];
		}
		$source_path = $source['path'] ?? '/';
		$base = substr( $source_path, 0, strrpos( $source_path, '/' ) + 1 );

		return preg_replace_callback(
			'/url\(\s*(["\']?)([^)"\']+)\1\s*\)/i',
			static function ( array $match ) use ( $origin, $source_path, $base ): string {
				$reference = trim( $match[2] );
				if ( '' === $reference || preg_match( '#^(?:[a-z][a-z0-9+.-]*:|//|\#)#i', $reference ) ) {
					return $match[0];
				}
				list( $path, $suffix ) = array_pad( preg_split( '/(?=[?#])/', $reference, 2 ), 2, '' );
				if ( '' === $path ) {
					$path = $source_path;
				} elseif ( '/' !== $path[0] ) {
					$path = $base . $path;
				}

				$segments = array();
				foreach ( explode( '/', substr( $path, 1 ) ) as $segment ) {
					if ( '..' === $segment ) {
						array_pop( $segments );
					} elseif ( '.' !== $segment ) {
						$segments[] = $segment;
					}
				}
				return 'url("' . esc_url_raw( $origin . '/' . implode( '/', $segments ) . $suffix ) . '")';
			},
			$css
		) ?? $css;
	}

	private function publish( string $filename, string $content ): void {
		if ( ! wp_mkdir_p( $this->directory ) || ! is_writable( $this->directory ) ) { throw new RuntimeException( 'CSS bundle directory is not writable.' ); }
		$temp = $this->directory . '/.' . $filename . '.' . wp_generate_password( 8, false, false ); $target = $this->directory . '/' . $filename;
		if ( strlen( $content ) !== file_put_contents( $temp, $content, LOCK_EX ) || ! rename( $temp, $target ) ) { if ( file_exists( $temp ) ) { unlink( $temp ); } throw new RuntimeException( 'Unable to publish CSS bundle.' ); }
	}
}
