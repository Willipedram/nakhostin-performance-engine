<?php
/**
 * Lightweight service registry.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

use InvalidArgumentException;

final class ServiceRegistry {
	/** @var array<string, callable|object> */
	private $definitions = array();

	/** @var array<string, object> */
	private $resolved = array();

	public function set( string $id, $service ): void {
		if ( ! is_object( $service ) && ! is_callable( $service ) ) {
			throw new InvalidArgumentException( 'A service must be an object or a callable factory.' );
		}

		$this->definitions[ $id ] = $service;
		unset( $this->resolved[ $id ] );
	}

	public function has( string $id ): bool {
		return isset( $this->definitions[ $id ] );
	}

	public function get( string $id ): object {
		if ( isset( $this->resolved[ $id ] ) ) {
			return $this->resolved[ $id ];
		}

		if ( ! $this->has( $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Service "%s" is not registered.', $id ) );
		}

		$definition = $this->definitions[ $id ];
		$service    = is_callable( $definition ) ? $definition( $this ) : $definition;

		if ( ! is_object( $service ) ) {
			throw new InvalidArgumentException( sprintf( 'Service factory "%s" must return an object.', $id ) );
		}

		$this->resolved[ $id ] = $service;

		return $service;
	}
}
