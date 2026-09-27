<?php
/**
 * Reusable built-in, detected, and custom component registry.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

use InvalidArgumentException;

final class ComponentRegistry {
	/** @var ComponentStorage */
	private $storage;

	/** @var BundleIndex */
	private $bundles;

	/** @var ComponentAdapterInterface[] */
	private $adapters;

	public function __construct( ComponentStorage $storage, BundleIndex $bundles, array $adapters = array() ) {
		$this->storage  = $storage;
		$this->bundles  = $bundles;
		$this->adapters = array_filter(
			$adapters,
			static function ( $adapter ): bool {
				return $adapter instanceof ComponentAdapterInterface;
			}
		);
	}

	public function all(): array {
		return array_merge( $this->built_in(), $this->hydrate( $this->storage->detected_definitions() ), $this->custom() );
	}

	public function custom(): array {
		return $this->hydrate( $this->storage->definitions() );
	}

	public function register_custom( ComponentDefinition $definition ): array {
		if ( 1 === preg_match( '/^(?:CORE|WC|ELEMENTOR|WOODMART)_/', $definition->id() ) ) {
			throw new InvalidArgumentException( 'Custom component IDs cannot use a reserved integration prefix.' );
		}
		if ( empty( $definition->to_array()['selectors'] ) ) {
			throw new InvalidArgumentException( 'Custom components require at least one valid selector.' );
		}

		$existing    = $this->custom();
		$invalidated = array();

		$has_changed = isset( $existing[ $definition->id() ] )
			&& $existing[ $definition->id() ]->signature() !== $definition->signature();
		if ( $has_changed ) {
			$invalidated = $this->bundles->invalidate_component( $definition->id() );
		}

		$this->storage->save_definition( $definition );

		return $invalidated;
	}

	public function ingest_manifest( array $manifest ): array {
		$page_types = array( sanitize_key( (string) ( $manifest['page_type'] ?? 'unknown' ) ) ?: 'unknown' );
		$detected   = $this->detect_built_in( $manifest );

		foreach ( $this->adapters as $adapter ) {
			$result = $adapter->inspect( $manifest );
			$page_types = array_merge( $page_types, $result['page_types'] ?? array() );

			foreach ( $result['components'] ?? array() as $component ) {
				if ( $component instanceof ComponentDefinition ) {
					$detected[ $component->id() ] = $component;
					$this->storage->save_detected( $component );
				}
			}
		}

		foreach ( $this->custom() as $component ) {
			if ( $this->matches_manifest( $component, $manifest ) ) {
				$detected[ $component->id() ] = $component;
			}
		}

		$page_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $page_types ) ) ) );
		foreach ( $detected as $component ) {
			$this->storage->record_usage( $component->id(), $page_types );
		}

		return array_values( $detected );
	}

	public function usage(): array {
		return $this->storage->usage();
	}

	private function built_in(): array {
		$definitions = array(
			array(
				'CORE_HEADER',
				__( 'Header', 'nakhostin-performance-engine' ),
				array( 'header', '[role="banner"]' ),
				array( 'menu-open' ),
			),
			array(
				'CORE_FOOTER',
				__( 'Footer', 'nakhostin-performance-engine' ),
				array( 'footer', '[role="contentinfo"]' ),
				array(),
			),
			array(
				'CORE_NAVIGATION',
				__( 'Navigation', 'nakhostin-performance-engine' ),
				array( 'nav', '[role="navigation"]' ),
				array( 'menu-open', 'active' ),
			),
			array(
				'CORE_MOBILE_MENU',
				__( 'Mobile Menu', 'nakhostin-performance-engine' ),
				array( '.mobile-menu', '.offcanvas-menu' ),
				array( 'menu-open' ),
			),
		);
		$result = array();

		foreach ( $definitions as $definition ) {
			$component = new ComponentDefinition(
				array(
					'id'                => $definition[0],
					'name'              => $definition[1],
					'selectors'         => $definition[2],
					'dynamic_states'    => $definition[3],
					'integration_owner' => 'core',
				)
			);
			$result[ $component->id() ] = $component;
		}

		return $result;
	}

	private function detect_built_in( array $manifest ): array {
		$mapping = array(
			'header'      => 'CORE_HEADER',
			'footer'      => 'CORE_FOOTER',
			'navigation'  => 'CORE_NAVIGATION',
			'mobile-menu' => 'CORE_MOBILE_MENU',
		);
		$built_in = $this->built_in();
		$detected = array();

		foreach ( $mapping as $manifest_name => $component_id ) {
			if ( in_array( $manifest_name, $manifest['components'] ?? array(), true ) ) {
				$detected[ $component_id ] = $built_in[ $component_id ];
			}
		}

		return $detected;
	}

	private function hydrate( array $definitions ): array {
		$result = array();

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			try {
				$component = new ComponentDefinition( $definition );
			} catch ( InvalidArgumentException $exception ) {
				continue;
			}

			$result[ $component->id() ] = $component;
		}

		return $result;
	}

	private function matches_manifest( ComponentDefinition $component, array $manifest ): bool {
		$data = $component->to_array();

		foreach ( $data['selectors'] as $selector ) {
			if ( 1 === preg_match( '/\.([a-zA-Z0-9_-]+)/', $selector, $match ) ) {
				if ( in_array( $match[1], $manifest['classes'] ?? array(), true ) ) {
					return true;
				}
			}

			if ( 1 === preg_match( '/#([a-zA-Z0-9_-]+)/', $selector, $match ) ) {
				if ( in_array( $match[1], $manifest['ids'] ?? array(), true ) ) {
					return true;
				}
			}

			if ( 1 === preg_match( '/^[a-zA-Z][a-zA-Z0-9-]*$/', $selector ) ) {
				if ( in_array( strtolower( $selector ), $manifest['elements'] ?? array(), true ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
