<?php
/**
 * Privacy-safe performance measurement value object.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Performance;

final class PerformanceSample {
	/** @var array */
	private $data;

	public function __construct( array $data ) {
		$this->data = array(
			'timestamp'                => max( 0, (int) ( $data['timestamp'] ?? time() ) ),
			'page_type'                => $this->dimension( (string) ( $data['page_type'] ?? 'unknown' ), 'unknown' ),
			'template'                 => $this->dimension( (string) ( $data['template'] ?? 'default' ), 'default' ),
			'components'               => $this->sanitize_keys( $data['components'] ?? array() ),
			'cache_state'              => $this->cache_state( (string) ( $data['cache_state'] ?? 'unknown' ) ),
			'backend_generation_ms'    => $this->number( $data['backend_generation_ms'] ?? 0 ),
			'wordpress_bootstrap_ms'   => $this->number( $data['wordpress_bootstrap_ms'] ?? 0 ),
			'plugin_loading_ms'        => $this->number( $data['plugin_loading_ms'] ?? 0 ),
			'theme_loading_ms'         => $this->number( $data['theme_loading_ms'] ?? 0 ),
			'response_generation_ms'   => $this->number( $data['response_generation_ms'] ?? 0 ),
			'database_query_count'     => max( 0, (int) ( $data['database_query_count'] ?? 0 ) ),
			'database_query_time_ms'   => isset( $data['database_query_time_ms'] ) ? $this->number( $data['database_query_time_ms'] ) : null,
			'memory_peak_bytes'        => max( 0, (int) ( $data['memory_peak_bytes'] ?? 0 ) ),
		);
	}

	public function to_array(): array {
		return $this->data;
	}

	private function number( $value ): float {
		return round( max( 0.0, (float) $value ), 3 );
	}

	private function sanitize_keys( $values ): array {
		$values = is_array( $values ) ? $values : array();
		$values = array_map( function ( $value ): string { return $this->dimension( (string) $value, '' ); }, $values );
		return array_slice( array_values( array_unique( array_filter( $values ) ) ), 0, 20 );
	}

	private function dimension( string $value, string $fallback ): string {
		$value = sanitize_key( $value );
		if ( '' === $value || preg_match( '/(?:password|passwd|token|secret|nonce|authorization|cookie|session)/i', $value ) ) { return $fallback; }
		return substr( $value, 0, 80 );
	}

	private function cache_state( string $state ): string {
		return in_array( $state, array( 'hit', 'stale', 'miss', 'bypass', 'unknown' ), true ) ? $state : 'unknown';
	}
}
