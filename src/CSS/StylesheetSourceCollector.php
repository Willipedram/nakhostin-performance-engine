<?php
/**
 * Safely fetch same-origin stylesheets referenced by a DOM manifest.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class StylesheetSourceCollector {
	public const MAX_TOTAL_BYTES = 1048576;
	public const MAX_FILE_BYTES  = 524288;

	public function collect( array $manifest ): array {
		$page_url = (string) ( $manifest['source_url'] ?? '' );
		$page_origin = $this->origin( $page_url );
		$contents = array();
		$skipped  = array();
		$total    = 0;

		foreach ( array_slice( (array) ( $manifest['stylesheets'] ?? array() ), 0, 50 ) as $source ) {
			$url = $this->absolute_url( (string) $source, $page_url );
			if ( '' === $url || '' === $page_origin || $page_origin !== $this->origin( $url ) ) {
				$skipped[] = 'cross-origin-or-invalid';
				continue;
			}

			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'             => 8,
					'redirection'         => 2,
					'limit_response_size' => min( self::MAX_FILE_BYTES, self::MAX_TOTAL_BYTES - $total ),
					'user-agent'          => 'NPE CSS Intelligence/' . NPE_VERSION,
				)
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				$skipped[] = 'fetch-failed';
				continue;
			}
			$body = (string) wp_remote_retrieve_body( $response );
			if ( '' === trim( $body ) ) {
				continue;
			}
			$contents[] = $body;
			$total     += strlen( $body );
			if ( $total >= self::MAX_TOTAL_BYTES ) {
				$skipped[] = 'size-limit';
				break;
			}
		}

		return array(
			'css'           => implode( "\n", $contents ),
			'fetched_files' => count( $contents ),
			'skipped_files' => count( $skipped ),
			'bytes'         => $total,
		);
	}

	private function absolute_url( string $source, string $page_url ): string {
		$source = esc_url_raw( $source, array( 'http', 'https' ) );
		if ( '' === $source ) {
			return '';
		}
		if ( wp_parse_url( $source, PHP_URL_HOST ) ) {
			return $source;
		}

		$scheme = (string) wp_parse_url( $page_url, PHP_URL_SCHEME );
		$host   = (string) wp_parse_url( $page_url, PHP_URL_HOST );
		$port   = wp_parse_url( $page_url, PHP_URL_PORT );
		if ( '' === $scheme || '' === $host || '/' !== substr( $source, 0, 1 ) ) {
			return '';
		}
		return $scheme . '://' . $host . ( is_int( $port ) ? ':' . $port : '' ) . $source;
	}

	private function origin( string $url ): string {
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		$host   = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$port   = wp_parse_url( $url, PHP_URL_PORT );
		return '' === $scheme || '' === $host ? '' : $scheme . '://' . $host . ( is_int( $port ) ? ':' . $port : '' );
	}
}
