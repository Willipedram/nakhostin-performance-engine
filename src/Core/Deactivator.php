<?php
/**
 * Deactivation lifecycle handler.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

final class Deactivator {
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'npe/run_maintenance' );
		wp_clear_scheduled_hook( 'npe/cache/run_warmup' );
		wp_clear_scheduled_hook( 'npe/cache/process_purge_queue' );
		wp_clear_scheduled_hook( 'npe/cache/process_warmup_queue' );
		wp_clear_scheduled_hook( 'npe/dom/process_analysis_queue' );
	}
}
