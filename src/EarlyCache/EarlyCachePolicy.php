<?php
/** Portable fail-closed early request policy. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCachePolicy {
	private $excluded;
	public function __construct( array $excluded = array() ) { $this->excluded = array_merge( array( '/wp-admin', '/wp-login.php', '/cart', '/checkout', '/my-account', '/wc-api', '/wp-json' ), $excluded ); }
	public function classify( EarlyCacheRequest $request ): string {
		if ( ! in_array( $request->method(), array( 'GET', 'HEAD' ), true ) ) { return 'unsafe_method'; }
		if ( '' !== (string) ( $request->headers()['authorization'] ?? '' ) ) { return 'authorization'; }
		$parts = parse_url( $request->uri() ); $path = strtolower( rawurldecode( (string) ( is_array( $parts ) ? ( $parts['path'] ?? '/' ) : '/' ) ) ); $query = (string) ( is_array( $parts ) ? ( $parts['query'] ?? '' ) : '' );
		if ( false !== strpos( $path, "\0" ) || false !== strpos( $path, '..' ) ) { return 'invalid_path'; }
		if ( false !== stripos( $query, 'wc-ajax=' ) || false !== stripos( $query, 'preview=' ) || false !== stripos( $query, 'rest_route=' ) ) { return 'sensitive_query'; }
		foreach ( $this->excluded as $excluded ) { $excluded = strtolower( '/' . ltrim( trim( (string) $excluded ), '/' ) ); if ( $path === $excluded || ( '/' !== $excluded && 0 === strpos( $path, rtrim( $excluded, '/' ) . '/' ) ) ) { return 'excluded_path'; } }
		foreach ( array_keys( $request->cookies() ) as $name ) { $name = strtolower( (string) $name ); foreach ( array( 'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'comment_author_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'woocommerce_recently_viewed', 'phpsessid', 'session', 'customer', 'member', 'cart' ) as $private ) { if ( false !== strpos( $name, $private ) ) { return 'private_cookie'; } } }
		return 'public';
	}
}
