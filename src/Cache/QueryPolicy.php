<?php
/**
 * Controls query-string cache cardinality.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class QueryPolicy {
	/** @var array */ private $allowed;

	public function __construct( array $allowed = array() ) {
		$this->allowed = array_values( array_unique( array_filter( array_map( 'sanitize_key', $allowed ) ) ) );
	}

	/**
	 * @return array{cacheable:bool,query:array,rejected:array}
	 */
	public function evaluate( string $query ): array {
		if ( '' === $query ) {
			return array( 'cacheable' => true, 'query' => array(), 'rejected' => array() );
		}
		if ( strlen( $query ) > 2048 ) {
			return array( 'cacheable' => false, 'query' => array(), 'rejected' => array( 'query_too_long' ) );
		}

		parse_str( $query, $parameters );
		if ( count( $parameters ) > 32 ) {
			return array( 'cacheable' => false, 'query' => array(), 'rejected' => array( 'too_many_parameters' ) );
		}
		$accepted = array();
		$rejected = array();
		foreach ( $parameters as $name => $value ) {
			$key = sanitize_key( (string) $name );
			if ( $this->is_tracking_parameter( $key ) ) {
				continue;
			}
			if ( $this->is_sensitive_parameter( $key ) ) {
				$rejected[] = $key;
				continue;
			}
			if ( ! in_array( $key, $this->allowed, true ) || is_array( $value ) ) {
				$rejected[] = $key;
				continue;
			}
			$value = substr( sanitize_text_field( (string) $value ), 0, 128 );
			$accepted[ $key ] = $value;
		}
		ksort( $accepted, SORT_STRING );

		return array( 'cacheable' => empty( $rejected ), 'query' => $accepted, 'rejected' => array_values( array_unique( $rejected ) ) );
	}

	private function is_tracking_parameter( string $key ): bool {
		return 0 === strpos( $key, 'utm_' ) || in_array( $key, array( 'gclid', 'fbclid', 'msclkid' ), true );
	}

	private function is_sensitive_parameter( string $key ): bool {
		return false !== strpos( $key, 'nonce' )
			|| false !== strpos( $key, 'token' )
			|| false !== strpos( $key, 'password' )
			|| false !== strpos( $key, 'session' )
			|| false !== strpos( $key, 'auth' )
			|| in_array( $key, array( 'preview', 'preview_id', 'preview_nonce', 'add-to-cart', 'wc-ajax', 'rest_route', 'redirect_to', 'key', 'order-pay', 'order-received', 'customer_id', 'user_id', 'email' ), true );
	}
}
