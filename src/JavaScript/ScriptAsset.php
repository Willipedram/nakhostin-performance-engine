<?php
/**
 * Immutable WordPress script registration snapshot.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

use InvalidArgumentException;

final class ScriptAsset {
	/** @var array<string, mixed> */
	private $data;

	public function __construct( array $data ) {
		$handle = sanitize_key( (string) ( $data['handle'] ?? '' ) );
		if ( '' === $handle ) {
			throw new InvalidArgumentException( 'Script assets require a valid handle.' );
		}

		$this->data = array(
			'handle'        => $handle,
			'src'           => esc_url_raw( (string) ( $data['src'] ?? '' ) ),
			'dependencies'  => $this->handles( $data['dependencies'] ?? array() ),
			'version'       => sanitize_text_field( (string) ( $data['version'] ?? '' ) ),
			'group'         => 1 === (int) ( $data['group'] ?? 0 ) ? 1 : 0,
			'strategy'      => $this->strategy( (string) ( $data['strategy'] ?? '' ) ),
			'module'        => true === ( $data['module'] ?? false ),
			'inline_before' => $this->inline( $data['inline_before'] ?? array() ),
			'inline_after'  => $this->inline( $data['inline_after'] ?? array() ),
			'localized'     => true === ( $data['localized'] ?? false ),
			'translations'  => true === ( $data['translations'] ?? false ),
			'enqueued'      => true === ( $data['enqueued'] ?? false ),
		);
	}

	public function handle(): string {
		return $this->data['handle'];
	}

	public function dependencies(): array {
		return $this->data['dependencies'];
	}

	public function source(): string {
		return $this->data['src'];
	}

	public function has_runtime_data(): bool {
		return $this->data['localized']
			|| $this->data['translations']
			|| ! empty( $this->data['inline_before'] )
			|| ! empty( $this->data['inline_after'] );
	}

	private function runtime_data(): array {
		return array(
			'before' => $this->data['inline_before'],
			'after'  => $this->data['inline_after'],
		);
	}

	public function to_array(): array {
		$data = $this->data;
		$data['src'] = $this->public_source( $data['src'] );
		$data['inline_before_count'] = count( $data['inline_before'] );
		$data['inline_after_count']  = count( $data['inline_after'] );
		unset( $data['inline_before'], $data['inline_after'] );

		return $data;
	}

	public function fingerprint( string $source_hash = '' ): string {
		$data = $this->to_array();
		unset( $data['enqueued'] );
		$data['source_hash']  = sanitize_key( $source_hash );
		$data['runtime_hash'] = $this->has_runtime_data()
			? hash( 'sha256', (string) wp_json_encode( $this->runtime_data() ) )
			: '';

		return hash( 'sha256', (string) wp_json_encode( $data ) );
	}

	private function handles( $handles ): array {
		$handles = is_array( $handles ) ? $handles : array();
		$handles = array_values( array_unique( array_filter( array_map( 'sanitize_key', $handles ) ) ) );

		return $handles;
	}

	private function inline( $scripts ): array {
		if ( is_string( $scripts ) ) {
			$scripts = array( $scripts );
		}

		return is_array( $scripts ) ? array_values( array_filter( $scripts, 'is_string' ) ) : array();
	}

	private function strategy( string $strategy ): string {
		$strategy = sanitize_key( $strategy );

		return in_array( $strategy, array( 'defer', 'async' ), true ) ? $strategy : '';
	}

	private function public_source( string $source ): string {
		$parts = wp_parse_url( $source );
		if ( false === $parts || ! is_array( $parts ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '';

		return ( '' !== $host ? ( '' !== $scheme ? $scheme . ':' : '' ) . '//' . $host : '' ) . $path;
	}
}
