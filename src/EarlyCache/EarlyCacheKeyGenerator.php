<?php
/** Portable key generation matching the conservative application defaults. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCacheKeyGenerator {
	public function generate( EarlyCacheRequest $request, array $config ): array {
		$host = strtolower( preg_replace( '/[^A-Za-z0-9.\-:\[\]]/', '', $request->host() ) );
		$home_host = strtolower( (string) ( $config['home_host'] ?? '' ) );
		if ( '' === $host || '' === $home_host || $host !== $home_host || ! in_array( $request->scheme(), array( 'http', 'https' ), true ) ) { return array( 'cacheable' => false, 'key' => '', 'url' => '' ); }
		$parts = parse_url( $request->uri() ); if ( false === $parts ) { return array( 'cacheable' => false, 'key' => '', 'url' => '' ); }
		$path = '/' . ltrim( preg_replace( '#/+#', '/', (string) ( $parts['path'] ?? '/' ) ), '/' );
		$raw_query = (string) ( $parts['query'] ?? '' ); if ( strlen( $raw_query ) > 2048 ) { return array( 'cacheable' => false, 'key' => '', 'url' => '' ); }
		parse_str( $raw_query, $parameters ); if ( count( $parameters ) > 32 ) { return array( 'cacheable' => false, 'key' => '', 'url' => '' ); }
		$allowed = array_flip( (array) ( $config['allowed_query_parameters'] ?? array() ) ); $query = array();
		foreach ( $parameters as $name => $value ) { $name = preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $name ) ); if ( 0 === strpos( $name, 'utm_' ) || in_array( $name, array( 'gclid', 'fbclid', 'msclkid' ), true ) ) { continue; } if ( ! isset( $allowed[ $name ] ) || is_array( $value ) || preg_match( '/nonce|token|password|session|auth/', $name ) || in_array( $name, array( 'preview', 'preview_id', 'preview_nonce', 'add-to-cart', 'wc-ajax', 'rest_route', 'redirect_to', 'key', 'order-pay', 'order-received', 'customer_id', 'user_id', 'email' ), true ) || ! preg_match( '/^[A-Za-z0-9_.~\-]{0,128}$/', (string) $value ) ) { return array( 'cacheable' => false, 'key' => '', 'url' => '' ); } $query[ $name ] = substr( (string) $value, 0, 128 ); }
		ksort( $query, SORT_STRING ); $url = $request->scheme() . '://' . $host . $path; if ( $query ) { $url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ); }
		$language = substr( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) ( $config['language'] ?? 'default' ) ) ), 0, 20 );
		$payload = array( 'version' => 1, 'url' => $url, 'dimensions' => array( 'site' => max( 1, (int) ( $config['site_id'] ?? 1 ) ), 'language' => $language ) );
		return array( 'cacheable' => true, 'key' => hash( 'sha256', json_encode( $payload ) ), 'url' => $url );
	}
}
