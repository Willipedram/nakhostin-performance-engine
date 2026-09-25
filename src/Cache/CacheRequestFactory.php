<?php
/** Builds bounded cache requests at the WordPress boundary. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class CacheRequestFactory {
	public function from_globals(): CacheRequest {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? preg_replace( '/[^A-Za-z0-9.\-:\[\]]/', '', wp_unslash( $_SERVER['HTTP_HOST'] ) ) : (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$url = ( is_ssl() ? 'https://' : 'http://' ) . $host . '/' . ltrim( $uri, '/' );
		$headers = array(); if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) { $headers['Authorization'] = 'present'; }
		$home_host    = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$home_port    = (int) wp_parse_url( home_url( '/' ), PHP_URL_PORT );
		$request_host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$request_port = (int) wp_parse_url( $url, PHP_URL_PORT );
		$context   = array(
			'is_admin' => is_admin(), 'is_login' => false !== strpos( (string) wp_parse_url( $url, PHP_URL_PATH ), 'wp-login.php' ),
			'is_preview' => function_exists( 'is_preview' ) && is_preview(), 'is_logged_in' => is_user_logged_in(),
			'is_ajax' => wp_doing_ajax(), 'is_rest' => defined( 'REST_REQUEST' ) && REST_REQUEST,
			'is_cart' => function_exists( 'is_cart' ) && is_cart(), 'is_checkout' => function_exists( 'is_checkout' ) && is_checkout(),
			'is_account' => function_exists( 'is_account_page' ) && is_account_page(), 'site_id' => get_current_blog_id(),
			'language' => determine_locale(), 'is_foreign_host' => $home_host !== $request_host || $home_port !== $request_port,
		);
		/**
		 * Filters bounded public cache dimensions and request flags.
		 *
		 * Integrations may supply device, currency, or configured variation
		 * dimensions, but must never add secrets or personal information.
		 *
		 * @param array $context Cache request context.
		 */
		$context = apply_filters( 'npe/cache/request_context', $context );
		$cookie_names = isset( $_COOKIE ) && is_array( $_COOKIE ) ? array_keys( $_COOKIE ) : array();
		$cookies      = array_fill_keys( array_map( 'sanitize_key', $cookie_names ), '' );
		return new CacheRequest( $method, $url, $headers, $cookies, is_array( $context ) ? $context : array() );
	}
}
