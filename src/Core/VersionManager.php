<?php
/**
 * Plugin and data version coordinator.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

final class VersionManager {
	public const OPTION = 'npe_version';

	/** @var MigrationManager */
	private $migrations;

	public function __construct( MigrationManager $migrations ) {
		$this->migrations = $migrations;
	}

	public function maybe_upgrade(): void {
		if (
			NPE_VERSION === get_option( self::OPTION, '' )
			&& MigrationManager::SCHEMA_VERSION === (int) get_option( MigrationManager::OPTION, 0 )
		) {
			return;
		}

		$this->migrations->migrate();
		update_option( self::OPTION, NPE_VERSION, false );
	}
}
