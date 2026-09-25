<?php
/**
 * Static runtime-state vocabulary and detection.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMStateRegistry {
	public const STATES = array(
		'menu-open',
		'modal-open',
		'loading',
		'active',
		'selected',
		'variation-selected',
		'ajax-result',
		'filter-open',
	);

	public function detect( array $classes, array $attribute_values ): array {
		$haystack = strtolower( implode( ' ', array_merge( $classes, $attribute_values ) ) );
		$detected = array();

		foreach ( self::STATES as $state ) {
			$alternatives = array( $state, str_replace( '-', '_', $state ), str_replace( '-', ' ', $state ) );

			foreach ( $alternatives as $candidate ) {
				if ( false !== strpos( $haystack, $candidate ) ) {
					$detected[] = $state;
					break;
				}
			}
		}

		return $detected;
	}

	public function supported(): array {
		return self::STATES;
	}
}
