<?php
/**
 * Stable structural signature generator.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMSignature {
	public function generate( array $structure ): string {
		$stable = array(
			'page_type'    => (string) ( $structure['page_type'] ?? 'unknown' ),
			'template'     => (string) ( $structure['template_identifier'] ?? 'unknown' ),
			'elements'     => $this->normalize_list( $structure['elements'] ?? array() ),
			'classes'      => $this->stable_classes( $structure['classes'] ?? array() ),
			'attributes'   => $this->normalize_list( $structure['attributes'] ?? array() ),
			'components'   => $this->normalize_list( $structure['components'] ?? array() ),
			'integrations' => $this->normalize_list( $structure['integrations'] ?? array() ),
			'widgets'      => $this->normalize_list( $structure['elementor_widgets'] ?? array() ),
		);

		return hash( 'sha256', (string) wp_json_encode( $stable ) );
	}

	private function stable_classes( array $classes ): array {
		$stable = array_filter(
			$classes,
			static function ( $class_name ): bool {
				return 1 !== preg_match(
					'/(?:^|[-_])(?:post|postid|page|page-id|product|term|tag|category|user)[-_]?\d+$'
						. '|elementor-element-[a-f0-9]{6,}|[a-f0-9]{12,}/i',
					(string) $class_name
				);
			}
		);

		return $this->normalize_list( $stable );
	}

	private function normalize_list( array $values ): array {
		$values = array_values( array_unique( array_map( 'strval', $values ) ) );
		sort( $values, SORT_STRING );

		return $values;
	}
}
