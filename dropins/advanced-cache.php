<?php
/** NPE server-agnostic early page-cache drop-in. */
defined( 'ABSPATH' ) || exit;
define( 'NPE_ADVANCED_CACHE_DROPIN', true );
$npe_plugin_path = __NPE_PLUGIN_PATH__;
$npe_classes = array( 'EarlyCacheRequest', 'EarlyCachePolicy', 'EarlyCacheKeyGenerator', 'EarlyCacheResponse', 'EarlyCacheReader', 'EarlyCacheRuntime' );
foreach ( $npe_classes as $npe_class ) { $npe_file = $npe_plugin_path . 'src/EarlyCache/' . $npe_class . '.php'; if ( ! is_readable( $npe_file ) ) { return; } require_once $npe_file; }
$npe_config_file = WP_CONTENT_DIR . '/cache/nakhostin-performance-engine/early-config.json';
if ( ! is_readable( $npe_config_file ) ) { return; }
$npe_config = json_decode( (string) file_get_contents( $npe_config_file ), true );
if ( ! is_array( $npe_config ) ) { return; }
$npe_runtime = new \Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheRuntime( $npe_config );
if ( $npe_runtime->serve( \Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheRequest::from_globals( $_SERVER, $_COOKIE ) ) ) { exit; }
