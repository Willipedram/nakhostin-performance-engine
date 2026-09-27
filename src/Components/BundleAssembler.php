<?php
/**
 * Deduplicated future bundle plan assembler.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

final class BundleAssembler {
	/** @var BundleIndex */
	private $index;

	public function __construct( BundleIndex $index ) {
		$this->index = $index;
	}

	public function assemble(
		array $components,
		string $page_type,
		string $template_identifier,
		string $core_version
	): array {
		$component_ids = array();
		$signatures    = array();
		$css           = array();
		$javascript    = array();

		foreach ( $components as $component ) {
			if ( ! $component instanceof ComponentDefinition ) {
				continue;
			}

			$data            = $component->to_array();
			$component_ids[] = $component->id();
			$signatures[]    = $component->signature();
			$css             = array_merge( $css, $data['css_dependencies'] );
			$javascript      = array_merge( $javascript, $data['javascript_dependencies'] );
		}

		$component_ids = $this->normalize( $component_ids );
		$signatures    = $this->normalize( $signatures );
		$stable        = array(
			'core'       => sanitize_text_field( $core_version ),
			'components' => $signatures,
			'page_type'  => sanitize_key( $page_type ) ?: 'unknown',
			'template'   => sanitize_key( $template_identifier ) ?: 'unknown',
		);
		$signature = hash( 'sha256', (string) wp_json_encode( $stable ) );
		$bundle    = array(
			'bundle_id'       => 'npe-' . substr( $signature, 0, 20 ),
			'signature'       => $signature,
			'layers'          => array( 'core', 'components', 'page_type', 'template' ),
			'component_ids'   => $component_ids,
			'css_dependencies' => $this->normalize( $css ),
			'js_dependencies'  => $this->normalize( $javascript ),
			'page_type'        => $stable['page_type'],
			'template'         => $stable['template'],
		);

		$this->index->remember( $bundle );

		return $bundle;
	}

	private function normalize( array $values ): array {
		$values = array_values( array_unique( array_map( 'strval', $values ) ) );
		sort( $values, SORT_STRING );

		return $values;
	}
}
