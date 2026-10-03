<?php
/** Low-overhead, opt-in backend performance monitor. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;

use Nakhostin\PerformanceEngine\Contracts\PerformanceMonitorInterface;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class PerformanceMonitor implements PerformanceMonitorInterface {
	/** @var Settings */ private $settings;
	/** @var PerformanceStorage */ private $storage;
	/** @var PageContextResolver */ private $context;
	/** @var callable */ private $clock;
	/** @var array */ private $timers = array();
	/** @var array */ private $measurements = array();
	/** @var float */ private $request_start;
	/** @var string */ private $cache_state = 'unknown';
	/** @var bool */ private $active = false;
	/** @var array */ private $checkpoints = array();

	public function __construct( Settings $settings, PerformanceStorage $storage, PageContextResolver $context, ?callable $clock = null ) {
		$this->settings      = $settings;
		$this->storage       = $storage;
		$this->context       = $context;
		$this->clock         = $clock ?? 'microtime';
		$this->request_start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : $this->now();
	}

	public function register(): void {
		if ( ! $this->should_measure() ) { return; }
		$this->active = true;
		$this->measurements['plugin_loading_ms'] = defined( 'NPE_BOOTSTRAP_START' ) && defined( 'NPE_BOOTSTRAP_END' ) ? round( max( 0, NPE_BOOTSTRAP_END - NPE_BOOTSTRAP_START ) * 1000, 3 ) : 0.0;
		$this->checkpoint( 'plugins_loaded' );
		foreach ( array( 'setup_theme', 'after_setup_theme', 'init', 'wp_loaded', 'template_redirect', 'template_include' ) as $hook ) {
			add_action( $hook, function () use ( $hook ): void { $this->checkpoint( $hook ); }, PHP_INT_MAX );
		}
		add_action( 'template_redirect', array( $this, 'response_started' ), PHP_INT_MAX );
		add_action( 'npe/cache/lookup', array( $this, 'cache_lookup' ) );
		register_shutdown_function( array( $this, 'finish' ) );
	}

	public function start( string $metric ): void { $this->timers[ sanitize_key( $metric ) ] = $this->now(); }
	public function stop( string $metric ): float {
		$metric = sanitize_key( $metric );
		if ( ! isset( $this->timers[ $metric ] ) ) { return 0.0; }
		$value = $this->elapsed( $this->timers[ $metric ] );
		unset( $this->timers[ $metric ] );
		$this->measurements[ $metric . '_ms' ] = $value;
		return $value;
	}
	public function report(): array { return $this->measurements; }
	public function theme_loaded(): void { $this->stop( 'theme_loading' ); }
	public function checkpoint( string $name ): void { if ( $this->active ) { $this->checkpoints[ sanitize_key( $name ) ] = $this->now(); } }
	public function response_started(): void { $this->start( 'response_generation' ); }
	public function cache_lookup( string $state ): void { $this->cache_state = in_array( $state, array( 'hit', 'stale', 'miss', 'bypass' ), true ) ? $state : 'unknown'; }

	public function finish(): void {
		if ( ! $this->active ) { return; }
		if ( isset( $this->timers['response_generation'] ) ) { $this->stop( 'response_generation' ); }
		$this->checkpoint( 'shutdown' );
		$this->active = false;
		$this->derive_timings();
		global $wpdb;
		$query_count = isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : ( function_exists( 'get_num_queries' ) ? (int) get_num_queries() : 0 );
		$query_time  = null;
		$slow_100 = 0; $slow_250 = 0; $maximum_query = 0.0;
		if ( isset( $wpdb->queries ) && is_array( $wpdb->queries ) ) {
			$query_time = 0.0;
			foreach ( $wpdb->queries as $query ) { $duration = isset( $query[1] ) && is_numeric( $query[1] ) ? (float) $query[1] : 0.0; $query_time += $duration; $maximum_query = max( $maximum_query, $duration ); if ( $duration > 0.1 ) { ++$slow_100; } if ( $duration > 0.25 ) { ++$slow_250; } }
		}
		$context = $this->context->resolve();
		$this->storage->save( new PerformanceSample( array_merge( $context, $this->measurements, array(
			'timestamp' => time(), 'cache_state' => $this->cache_state,
			'backend_generation_ms' => $this->elapsed( $this->request_start ),
			'database_query_count' => $query_count, 'database_query_time_ms' => null === $query_time ? null : $query_time * 1000,
			'database_slow_100_count' => $slow_100, 'database_slow_250_count' => $slow_250, 'database_max_query_ms' => $maximum_query * 1000,
			'memory_peak_bytes' => memory_get_peak_usage( true ),
		) ) ), (int) $this->settings->get( 'performance.retention_days', 7 ), (int) $this->settings->get( 'performance.max_samples', 500 ) );
	}

	private function should_measure(): bool {
		if ( ! $this->settings->get( 'performance.enabled', false ) || is_admin() || is_user_logged_in() || wp_doing_ajax() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) ) { return false; }
		$rate = (int) $this->settings->get( 'performance.sample_rate', 10 );
		return $rate >= 100 || ( $rate > 0 && random_int( 1, 100 ) <= $rate );
	}

	private function now(): float {
		return 'microtime' === $this->clock ? microtime( true ) : (float) call_user_func( $this->clock );
	}
	private function elapsed( float $start ): float { return round( max( 0, $this->now() - $start ) * 1000, 3 ); }

	private function derive_timings(): void {
		$at = function ( string $name, float $fallback ): float { return $this->checkpoints[ $name ] ?? $fallback; };
		$plugins = $at( 'plugins_loaded', $this->request_start );
		$theme = $at( 'after_setup_theme', $plugins );
		$template = $at( 'template_include', $at( 'template_redirect', $theme ) );
		$shutdown = $at( 'shutdown', $this->now() );
		$this->measurements['wordpress_bootstrap_ms'] = round( max( 0, $plugins - $this->request_start ) * 1000, 3 );
		$this->measurements['plugin_bootstrap_ms'] = round( max( 0, $plugins - $this->request_start ) * 1000, 3 );
		$this->measurements['theme_bootstrap_ms'] = round( max( 0, $theme - $plugins ) * 1000, 3 );
		$this->measurements['pre_template_ms'] = round( max( 0, $template - $theme ) * 1000, 3 );
		$this->measurements['response_generation_ms'] = round( max( 0, $shutdown - $template ) * 1000, 3 );
		$this->measurements['total_backend_ms'] = round( max( 0, $shutdown - $this->request_start ) * 1000, 3 );
	}
}
