<?php
/** Read-only compression capability detection. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;
final class CompressionDetector {
	public function detect( array $server = array() ): array {
		$encoding = strtolower( (string) ( $server['HTTP_ACCEPT_ENCODING'] ?? '' ) );
		$output = strtolower( (string) ini_get( 'zlib.output_compression' ) );
		return array(
			'gzip_capable' => extension_loaded( 'zlib' ),
			'gzip_active' => in_array( $output, array( '1', 'on' ), true ),
			'brotli_capable' => false !== strpos( $encoding, 'br' ),
			'brotli_active' => isset( $server['HTTP_CONTENT_ENCODING'] ) && 'br' === strtolower( (string) $server['HTTP_CONTENT_ENCODING'] ),
		);
	}
}
