<?php
/** Dependency closure for precomputed page-asset decisions. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Assets;

final class AssetDependencyGraph {
	/** @var array<string,array<string,mixed>> */ private $assets;

	public function __construct( array $assets ) { $this->assets = $assets; }

	/** Return direct dependents in deterministic registry order. */
	public function dependents( string $handle ): array {
		$result = array();
		foreach ( $this->assets as $candidate => $asset ) {
			if ( in_array( $handle, (array) ( $asset['dependencies'] ?? array() ), true ) ) { $result[] = (string) $candidate; }
		}
		return $result;
	}

	/** Return existing direct and transitive dependencies, terminating safely on cycles. */
	public function dependencies( array $roots ): array {
		$result = array(); $visited = array();
		$visit = function ( string $handle ) use ( &$visit, &$result, &$visited ): void {
			if ( isset( $visited[ $handle ] ) ) { return; }
			$visited[ $handle ] = true;
			if ( ! isset( $this->assets[ $handle ] ) ) { return; }
			foreach ( (array) ( $this->assets[ $handle ]['dependencies'] ?? array() ) as $dependency ) {
				$dependency = sanitize_key( (string) $dependency );
				if ( '' === $dependency || ! isset( $this->assets[ $dependency ] ) ) { continue; }
				if ( ! in_array( $dependency, $result, true ) ) { $result[] = $dependency; }
				$visit( $dependency );
			}
		};
		foreach ( $roots as $root ) { $visit( sanitize_key( (string) $root ) ); }
		return $result;
	}

	/** Return direct and transitive dependents without recursing forever on cycles. */
	public function transitive_dependents( string $handle ): array {
		$result = array(); $queue = $this->dependents( $handle ); $seen = array( $handle => true );
		while ( $queue ) {
			$candidate = array_shift( $queue );
			if ( isset( $seen[ $candidate ] ) ) { continue; }
			$seen[ $candidate ] = true; $result[] = $candidate;
			foreach ( $this->dependents( $candidate ) as $dependent ) { $queue[] = $dependent; }
		}
		return $result;
	}

	/** A retained dependent at any depth prevents unloading. */
	public function can_unload( string $handle, array $kept ): bool {
		return empty( array_intersect( $this->transitive_dependents( $handle ), $kept ) );
	}

	/** Missing nodes in the dependency closure make an unload decision uncertain. */
	public function is_complete_for( array $roots ): bool {
		$queue = array_values( array_unique( array_map( 'sanitize_key', $roots ) ) ); $seen = array();
		while ( $queue ) {
			$handle = array_shift( $queue );
			if ( isset( $seen[ $handle ] ) ) { continue; }
			$seen[ $handle ] = true;
			if ( ! isset( $this->assets[ $handle ] ) ) { return false; }
			foreach ( (array) ( $this->assets[ $handle ]['dependencies'] ?? array() ) as $dependency ) { $queue[] = sanitize_key( (string) $dependency ); }
		}
		return true;
	}

	/** Detect a cycle reachable from the supplied roots. */
	public function has_cycle( array $roots ): bool {
		$visited = array(); $visiting = array();
		$visit = function ( string $handle ) use ( &$visit, &$visited, &$visiting ): bool {
			if ( isset( $visiting[ $handle ] ) ) { return true; }
			if ( isset( $visited[ $handle ] ) || ! isset( $this->assets[ $handle ] ) ) { return false; }
			$visiting[ $handle ] = true;
			foreach ( (array) ( $this->assets[ $handle ]['dependencies'] ?? array() ) as $dependency ) {
				if ( $visit( sanitize_key( (string) $dependency ) ) ) { return true; }
			}
			unset( $visiting[ $handle ] ); $visited[ $handle ] = true;
			return false;
		};
		foreach ( $roots as $root ) { if ( $visit( sanitize_key( (string) $root ) ) ) { return true; } }
		return false;
	}
}
