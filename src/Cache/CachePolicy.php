<?php
/**
 * Conservative request and response cache policy.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CachePolicy {
	/** @var array */ private $excluded_paths;

	public function __construct( array $excluded_paths = array() ) {
		$this->excluded_paths = array_merge(
			array( '/wp-admin', '/wp-login.php', '/cart', '/checkout', '/my-account', '/wc-api', '/wp-json' ),
			array_values( array_filter( array_map( 'strval', $excluded_paths ) ) )
		);
	}

	public function classify_request( CacheRequest $request ): CacheDecision {
		if ( ! in_array( $request->method(), array( 'GET', 'HEAD' ), true ) ) {
			return new CacheDecision( false, 'unsafe_method' );
		}
		foreach ( array( 'is_admin', 'is_login', 'is_preview', 'is_logged_in', 'is_ajax', 'is_rest', 'is_cart', 'is_checkout', 'is_account', 'is_foreign_host' ) as $flag ) {
			if ( $request->context_value( $flag, false ) ) {
				return new CacheDecision( false, $flag );
			}
		}
		$path = (string) wp_parse_url( $request->url(), PHP_URL_PATH );
		foreach ( $this->excluded_paths as $excluded ) {
			$excluded = '/' . ltrim( trim( $excluded ), '/' );
			if ( '/' !== $excluded && ( $path === $excluded || 0 === strpos( $path, trailingslashit( $excluded ) ) ) ) {
				return new CacheDecision( false, 'excluded_path' );
			}
		}
		foreach ( array_keys( $request->cookies() ) as $cookie ) {
			$cookie = strtolower( (string) $cookie );
			foreach ( array( 'wordpress_logged_in_', 'wordpress_sec_', 'wp-postpass_', 'comment_author_', 'woocommerce_items_in_cart', 'woocommerce_cart_hash', 'wp_woocommerce_session_', 'woocommerce_recently_viewed', 'phpsessid', 'session', 'customer', 'member', 'cart' ) as $private_cookie ) {
				if ( false !== strpos( $cookie, $private_cookie ) ) {
					return new CacheDecision( false, 'private_cookie' );
				}
			}
		}
		foreach ( $request->headers() as $name => $value ) {
			if ( 'authorization' === strtolower( (string) $name ) && '' !== trim( (string) $value ) ) {
				return new CacheDecision( false, 'authorization' );
			}
		}

		return new CacheDecision( true, 'public' );
	}

	public function classify_response( int $status, array $headers, string $content, bool $personalized = false ): CacheDecision {
		if ( $personalized || 200 !== $status || '' === trim( $content ) ) {
			return new CacheDecision( false, $personalized ? 'personalized' : 'response_status' );
		}
		if ( preg_match( '/<input[^>]+type=["\']?password|name=["\'](?:_wpnonce|_ajax_nonce|woocommerce-login-nonce|woocommerce-register-nonce|woocommerce-reset-password-nonce)["\']|id=["\']wpadminbar["\']/i', $content ) ) {
			return new CacheDecision( false, 'sensitive_markup' );
		}
		foreach ( $headers as $name => $value ) {
			$name  = strtolower( (string) $name );
			$value = strtolower( is_array( $value ) ? implode( ',', $value ) : (string) $value );
			if ( 'set-cookie' === $name ) {
				return new CacheDecision( false, 'sets_cookie' );
			}
			if ( 'cache-control' === $name && ( false !== strpos( $value, 'private' ) || false !== strpos( $value, 'no-store' ) ) ) {
				return new CacheDecision( false, 'private_response' );
			}
			if ( 'content-type' === $name && false === strpos( $value, 'text/html' ) && false === strpos( $value, 'application/xhtml+xml' ) ) {
				return new CacheDecision( false, 'non_html' );
			}
		}

		return new CacheDecision( true, 'public' );
	}
}
