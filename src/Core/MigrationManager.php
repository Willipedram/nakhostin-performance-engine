<?php
/**
 * Ordered schema migration runner.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Contracts\MigrationInterface;

final class MigrationManager {
	public const OPTION = 'npe_db_version';
	public const SCHEMA_VERSION = 1;

	/** @var array<int, MigrationInterface> */
	private $migrations = array();

	public function add( MigrationInterface $migration ): void {
		$version = $migration->get_version();

		if ( $version < 1 || isset( $this->migrations[ $version ] ) ) {
			throw new InvalidArgumentException( 'Migration versions must be unique positive integers.' );
		}

		$this->migrations[ $version ] = $migration;
	}

	public function migrate(): void {
		$current_version = (int) get_option( self::OPTION, 0 );
		ksort( $this->migrations, SORT_NUMERIC );

		foreach ( $this->migrations as $version => $migration ) {
			if ( $version <= $current_version || $version > self::SCHEMA_VERSION ) {
				continue;
			}

			$migration->up();
			update_option( self::OPTION, $version, false );
			$current_version = $version;
		}

		if ( $current_version < self::SCHEMA_VERSION ) {
			update_option( self::OPTION, self::SCHEMA_VERSION, false );
		}
	}
}
