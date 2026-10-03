<?php
/**
 * Deactivation lifecycle handler.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheInstaller;
use Nakhostin\PerformanceEngine\Environment\DropInDetector;

final class Deactivator {
	public static function deactivate(): void {
		if ( defined( 'WP_CONTENT_DIR' ) && defined( 'NPE_PATH' ) ) {
			$installer = new EarlyCacheInstaller( new DropInDetector( WP_CONTENT_DIR ), NPE_PATH . 'dropins/advanced-cache.php', NPE_PATH );
			$installer->remove();
			$config = trailingslashit( WP_CONTENT_DIR ) . 'cache/nakhostin-performance-engine/early-config.json';
			if ( is_file( $config ) ) { unlink( $config ); }
		}
		wp_clear_scheduled_hook( 'npe/run_maintenance' );
		wp_clear_scheduled_hook( 'npe/cache/run_warmup' );
		wp_clear_scheduled_hook( 'npe/cache/process_purge_queue' );
		wp_clear_scheduled_hook( 'npe/cache/process_warmup_queue' );
		wp_clear_scheduled_hook( 'npe/dom/process_analysis_queue' );
	}
}
