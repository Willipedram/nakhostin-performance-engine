<?php
/** Explicit debug-only lifecycle profiler; never attributes callback time speculatively. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
final class HookProfiler {
	/** @var Settings */ private $settings; /** @var HookProfileStorage */ private $storage; /** @var array */ private $marks = array();
	public function __construct( Settings $settings, HookProfileStorage $storage ) { $this->settings = $settings; $this->storage = $storage; }
	public function register(): void { if ( ! $this->settings->get( 'performance.hook_profiling', false ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || is_admin() ) { return; } $this->marks['request_start'] = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true ); foreach ( array( 'plugins_loaded', 'after_setup_theme', 'init', 'wp_loaded', 'template_redirect', 'template_include' ) as $hook ) { add_action( $hook, function () use ( $hook ): void { $this->marks[ $hook ] = microtime( true ); }, PHP_INT_MAX ); } register_shutdown_function( array( $this, 'finish' ) ); }
	public function finish(): void { $this->marks['shutdown'] = microtime( true ); $previous = $this->marks['request_start']; $durations = array(); foreach ( $this->marks as $hook => $at ) { if ( 'request_start' === $hook ) { continue; } $durations[ $hook ] = ( $at - $previous ) * 1000; $previous = $at; } $this->storage->save( new HookProfileSample( $durations ) ); }
}
