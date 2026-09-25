<?php
/**
 * Compact dependency index for future generated bundles.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

final class BundleIndex {
	public const OPTION = 'npe_component_bundle_index';

	public function remember( array $bundle ): void {
		$index = $this->all();
		$index[ $bundle['bundle_id'] ] = array(
			'components' => array_values( $bundle['component_ids'] ),
			'assets'     => array_values( array_unique( array_merge( $bundle['css_dependencies'] ?? array(), $bundle['js_dependencies'] ?? array() ) ) ),
			'signature'  => $bundle['signature'],
		);
		update_option( self::OPTION, $index, false );
		do_action( 'npe/cache/bundle_indexed', $bundle );
	}

	public function invalidate_component( string $component_id ): array {
		$index       = $this->all();
		$invalidated = array();

		foreach ( $index as $bundle_id => $bundle ) {
			if ( is_array( $bundle ) && in_array( $component_id, $bundle['components'] ?? array(), true ) ) {
				$invalidated[] = $bundle_id;
				unset( $index[ $bundle_id ] );
			}
		}

		update_option( self::OPTION, $index, false );

		return $invalidated;
	}

	public function all(): array {
		$index = get_option( self::OPTION, array() );

		return is_array( $index ) ? $index : array();
	}

	public function invalidate_asset( string $asset ): array {
		$index       = $this->all();
		$invalidated = array();
		foreach ( $index as $bundle_id => $bundle ) {
			if ( in_array( $asset, (array) ( $bundle['assets'] ?? array() ), true ) ) {
				$invalidated[] = $bundle_id;
				unset( $index[ $bundle_id ] );
			}
		}
		update_option( self::OPTION, $index, false );
		return $invalidated;
	}

	public function invalidate_bundle( string $bundle_id ): bool {
		$index = $this->all();
		if ( ! isset( $index[ $bundle_id ] ) ) {
			return false;
		}
		unset( $index[ $bundle_id ] );
		return update_option( self::OPTION, $index, false );
	}

	public function flush(): int {
		$count = count( $this->all() );
		delete_option( self::OPTION );
		return $count;
	}
}
