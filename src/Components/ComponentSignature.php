<?php
/**
 * Stable component definition signature.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

final class ComponentSignature {
	public static function generate( array $definition ): string {
		$stable = array(
			'id'                        => (string) ( $definition['id'] ?? '' ),
			'selectors'                 => self::normalize( $definition['selectors'] ?? array() ),
			'css_dependencies'          => self::normalize( $definition['css_dependencies'] ?? array() ),
			'javascript_dependencies'   => self::normalize( $definition['javascript_dependencies'] ?? array() ),
			'dynamic_states'            => self::normalize( $definition['dynamic_states'] ?? array() ),
			'cache_behavior'            => (string) ( $definition['cache_behavior'] ?? 'shared' ),
			'invalidation_dependencies' => self::normalize( $definition['invalidation_dependencies'] ?? array() ),
			'integration_owner'         => (string) ( $definition['integration_owner'] ?? 'core' ),
		);

		return hash( 'sha256', (string) wp_json_encode( $stable ) );
	}

	private static function normalize( array $values ): array {
		$values = array_values( array_unique( array_map( 'strval', $values ) ) );
		sort( $values, SORT_STRING );

		return $values;
	}
}
