<?php
/**
 * Activation lifecycle handler.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

final class Activator {
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( array( 'fields' => 'ids' ) );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_site();
				restore_current_blog();
			}

			return;
		}

		self::activate_site();
	}

	private static function activate_site(): void {
		$migrations = new MigrationManager();
		$versions   = new VersionManager( $migrations );

		$versions->maybe_upgrade();
	}
}
