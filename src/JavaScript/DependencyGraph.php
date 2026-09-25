<?php
/**
 * Dependency-safe script graph.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class DependencyGraph {
	/** @var array<string, ScriptAsset> */
	private $assets = array();

	/** @var string[] */
	private $registration_order = array();

	public function __construct( array $assets = array() ) {
		foreach ( $assets as $asset ) {
			if ( $asset instanceof ScriptAsset ) {
				$this->assets[ $asset->handle() ] = $asset;
				$this->registration_order[]       = $asset->handle();
			}
		}
	}

	public function asset( string $handle ): ?ScriptAsset {
		return $this->assets[ $handle ] ?? null;
	}

	public function assets(): array {
		return $this->assets;
	}

	public function closure( array $handles ): array {
		$ordered  = array();
		$visiting = array();
		$visited  = array();

		foreach ( $this->ordered_handles( $handles ) as $handle ) {
			$this->visit( $handle, $ordered, $visiting, $visited );
		}

		return $ordered;
	}

	public function diagnostics(): array {
		$missing = array();
		$cycles  = array();

		foreach ( $this->assets as $asset ) {
			foreach ( $asset->dependencies() as $dependency ) {
				if ( ! isset( $this->assets[ $dependency ] ) ) {
					$missing[ $asset->handle() ][] = $dependency;
				}
			}
		}

		try {
			$this->closure( array_keys( $this->assets ) );
		} catch ( DependencyCycleException $exception ) {
			$cycles[] = $exception->getMessage();
		}

		return array( 'missing' => $missing, 'cycles' => $cycles );
	}

	private function visit( string $handle, array &$ordered, array &$visiting, array &$visited ): void {
		if ( isset( $visited[ $handle ] ) || ! isset( $this->assets[ $handle ] ) ) {
			return;
		}

		if ( isset( $visiting[ $handle ] ) ) {
			throw new DependencyCycleException( 'Dependency cycle detected at script: ' . $handle );
		}

		$visiting[ $handle ] = true;
		foreach ( $this->assets[ $handle ]->dependencies() as $dependency ) {
			$this->visit( $dependency, $ordered, $visiting, $visited );
		}
		unset( $visiting[ $handle ] );
		$visited[ $handle ] = true;
		$ordered[]          = $handle;
	}

	private function ordered_handles( array $handles ): array {
		$requested = array_fill_keys( array_map( 'sanitize_key', $handles ), true );
		$ordered   = array();

		foreach ( $this->registration_order as $handle ) {
			if ( isset( $requested[ $handle ] ) ) {
				$ordered[] = $handle;
				unset( $requested[ $handle ] );
			}
		}

		return array_merge( $ordered, array_keys( $requested ) );
	}
}
