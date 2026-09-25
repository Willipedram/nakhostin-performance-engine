<?php
/**
 * Plugin uninstall entry point.
 *
 * Phase 0 deliberately stores no persistent plugin data, so there is nothing to
 * remove. Future removal must be explicit, capability-safe, and opt-in.
 *
 * @package NakhostinPerformanceEngine
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$npe_remove_site_data = static function () {
	$settings = get_option( 'npe_settings', array() );

	if ( ! is_array( $settings ) || empty( $settings['general']['remove_data_on_uninstall'] ) ) {
		return;
	}

	delete_option( 'npe_settings' );
	delete_option( 'npe_version' );
	delete_option( 'npe_db_version' );
	delete_option( 'npe_dom_manifest' );
	delete_option( 'npe_custom_components' );
	delete_option( 'npe_detected_components' );
	delete_option( 'npe_component_usage' );
	delete_option( 'npe_component_bundle_index' );
	delete_option( 'npe_javascript_manifest' );
	$fragment_index = get_option( 'npe_fragment_cache_index', array() );
	if ( is_array( $fragment_index ) ) {
		foreach ( array_keys( $fragment_index ) as $fragment_key ) {
			wp_cache_delete( (string) $fragment_key, 'npe_fragments' );
			wp_cache_delete( 'metric:fragment:' . (string) $fragment_key, 'npe_fragments' );
		}
	}
	wp_cache_delete( 'metric:hits', 'npe_fragments' );
	wp_cache_delete( 'metric:misses', 'npe_fragments' );
	wp_cache_delete( 'index-lock', 'npe_fragments' );
	delete_option( 'npe_fragment_cache_index' );
	delete_option( 'npe_cache_dependency_graph' );
	delete_option( 'npe_cache_dependency_graph_lock' );
	delete_option( 'npe_cache_purge_queue' );
	delete_option( 'npe_cache_purge_queue_lock' );
	delete_option( 'npe_cache_warmup_queue' );
	delete_option( 'npe_cache_warmup_queue_lock' );
	delete_option( 'npe_cache_operations_state' );
	delete_option( 'npe_performance_samples' );

	if ( defined( 'WP_CONTENT_DIR' ) ) {
		// phpcs:disable WordPress.WP.AlternativeFunctions -- Uninstall must remove lock-protected generated files without loading plugin services.
		$cache_directory = trailingslashit( WP_CONTENT_DIR ) . 'cache/nakhostin-performance-engine';
		if ( is_dir( $cache_directory ) ) {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $cache_directory, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $iterator as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			}
			rmdir( $cache_directory );
		}
		// phpcs:enable WordPress.WP.AlternativeFunctions
	}
};

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		$npe_remove_site_data();
		restore_current_blog();
	}
} else {
	$npe_remove_site_data();
}
