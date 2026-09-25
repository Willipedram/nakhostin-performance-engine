<?php
/**
 * Canonicalizes public URLs without retaining credentials or fragments.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class URLNormalizer {
	/**
	 * @return array{cacheable:bool,url:string,query:array,rejected:array}
	 */
	public function normalize( string $url, QueryPolicy $query_policy ): array {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return array( 'cacheable' => false, 'url' => '', 'query' => array(), 'rejected' => array() );
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return array( 'cacheable' => false, 'url' => '', 'query' => array(), 'rejected' => array() );
		}
		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		$port = isset( $parts['port'] ) ? (int) $parts['port'] : 0;
		$authority = $host;
		if ( $port && ! ( 80 === $port && 'http' === $scheme ) && ! ( 443 === $port && 'https' === $scheme ) ) {
			$authority .= ':' . $port;
		}
		$path = (string) ( $parts['path'] ?? '/' );
		$path = '/' . ltrim( preg_replace( '#/+#', '/', $path ), '/' );
		$query = $query_policy->evaluate( (string) ( $parts['query'] ?? '' ) );
		$url   = $scheme . '://' . $authority . $path;
		if ( ! empty( $query['query'] ) ) {
			$url .= '?' . http_build_query( $query['query'], '', '&', PHP_QUERY_RFC3986 );
		}

		return array( 'cacheable' => $query['cacheable'], 'url' => $url, 'query' => $query['query'], 'rejected' => $query['rejected'] );
	}
}
