<?php
/**
 * Option-backed component definitions and usage metadata.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

final class ComponentStorage {
	public const DEFINITIONS_OPTION = 'npe_custom_components';
	public const DETECTED_OPTION = 'npe_detected_components';
	public const USAGE_OPTION = 'npe_component_usage';

	public function definitions(): array {
		$value = get_option( self::DEFINITIONS_OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	public function save_definition( ComponentDefinition $definition ): bool {
		$definitions                      = $this->definitions();
		$definitions[ $definition->id() ] = $definition->to_array();

		return update_option( self::DEFINITIONS_OPTION, $definitions, false );
	}

	public function detected_definitions(): array {
		$value = get_option( self::DETECTED_OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	public function save_detected( ComponentDefinition $definition ): bool {
		$definitions                      = $this->detected_definitions();
		$definitions[ $definition->id() ] = $definition->to_array();

		return update_option( self::DETECTED_OPTION, $definitions, false );
	}

	public function usage(): array {
		$value = get_option( self::USAGE_OPTION, array() );

		return is_array( $value ) ? $value : array();
	}

	public function record_usage( string $component_id, array $page_types ): void {
		$usage = $this->usage();
		if ( ! isset( $usage[ $component_id ] ) || ! is_array( $usage[ $component_id ] ) ) {
			$usage[ $component_id ] = array( 'count' => 0, 'page_types' => array() );
		}
		if ( ! isset( $usage[ $component_id ]['page_types'] ) || ! is_array( $usage[ $component_id ]['page_types'] ) ) {
			$usage[ $component_id ]['page_types'] = array();
		}

		$usage[ $component_id ]['count'] = (int) ( $usage[ $component_id ]['count'] ?? 0 ) + 1;
		foreach ( $page_types as $page_type ) {
			$usage[ $component_id ]['page_types'][] = sanitize_key( (string) $page_type ) ?: 'unknown';
		}
		$usage[ $component_id ]['page_types'] = array_values(
			array_unique( $usage[ $component_id ]['page_types'] )
		);
		sort( $usage[ $component_id ]['page_types'], SORT_STRING );
		update_option( self::USAGE_OPTION, $usage, false );
	}
}
