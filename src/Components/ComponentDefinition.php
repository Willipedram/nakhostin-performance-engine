<?php
/**
 * Immutable reusable component definition.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

use InvalidArgumentException;

final class ComponentDefinition {
	/** @var array<string, mixed> */
	private $data;

	public function __construct( array $data ) {
		$id   = strtoupper( sanitize_key( str_replace( '-', '_', (string) ( $data['id'] ?? '' ) ) ) );
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $id || '' === $name ) {
			throw new InvalidArgumentException( 'Components require an ID and name.' );
		}

		$cache_behavior = sanitize_key( (string) ( $data['cache_behavior'] ?? 'shared' ) );
		if ( ! in_array( $cache_behavior, array( 'shared', 'private', 'uncacheable' ), true ) ) {
			$cache_behavior = 'shared';
		}

		$this->data = array(
			'id'                        => $id,
			'name'                      => $name,
			'selectors'                 => $this->sanitize_selectors( $data['selectors'] ?? array() ),
			'css_dependencies'          => $this->sanitize_dependencies( $data['css_dependencies'] ?? array() ),
			'javascript_dependencies'   => $this->sanitize_dependencies( $data['javascript_dependencies'] ?? array() ),
			'dynamic_states'            => $this->sanitize_tokens( $data['dynamic_states'] ?? array() ),
			'cache_behavior'            => $cache_behavior,
			'invalidation_dependencies' => $this->sanitize_tokens( $data['invalidation_dependencies'] ?? array() ),
			'integration_owner'         => sanitize_key( (string) ( $data['integration_owner'] ?? 'core' ) ),
		);
		$this->data['structural_signature'] = ComponentSignature::generate( $this->data );
	}

	public function id(): string {
		return $this->data['id'];
	}

	public function signature(): string {
		return $this->data['structural_signature'];
	}

	public function to_array(): array {
		return $this->data;
	}

	private function sanitize_selectors( $selectors ): array {
		$selectors = is_array( $selectors ) ? $selectors : array();
		$result    = array();

		foreach ( $selectors as $selector ) {
			$selector = trim( sanitize_text_field( (string) $selector ) );
			if (
				'' !== $selector
				&& strlen( $selector ) <= 200
				&& 1 === preg_match( '/^[a-zA-Z0-9_#.\[\]="~|^$*:+>\-()\s]+$/', $selector )
			) {
				$result[] = $selector;
			}
		}

		return $this->unique( $result );
	}

	private function sanitize_dependencies( $dependencies ): array {
		$dependencies = is_array( $dependencies ) ? $dependencies : array();
		$result       = array();

		foreach ( $dependencies as $dependency ) {
			$dependency = trim( (string) $dependency );
			if ( '' === $dependency || strlen( $dependency ) > 255 || false !== strpos( $dependency, '@' ) ) {
				continue;
			}

			if ( preg_match( '#^(?:https?:)?//#i', $dependency ) || 0 === strpos( $dependency, '/' ) ) {
				$dependency = $this->safe_asset_source( $dependency );
			} else {
				$dependency = sanitize_key( $dependency );
			}

			if ( '' !== $dependency ) {
				$result[] = $dependency;
			}
		}

		return $this->unique( $result );
	}

	private function safe_asset_source( string $source ): string {
		$parts = wp_parse_url( esc_url_raw( $source ) );
		if ( false === $parts || ! is_array( $parts ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? '/' . ltrim( $parts['path'], '/' ) : '';

		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		return ( '' !== $host ? ( '' !== $scheme ? $scheme . ':' : '' ) . '//' . $host . $port : '' ) . $path;
	}

	private function sanitize_tokens( $tokens ): array {
		$tokens = is_array( $tokens ) ? $tokens : array();
		return $this->unique( array_filter( array_map( 'sanitize_key', array_map( 'strval', $tokens ) ) ) );
	}

	private function unique( array $values ): array {
		$values = array_values( array_unique( $values ) );
		sort( $values, SORT_STRING );

		return $values;
	}
}
