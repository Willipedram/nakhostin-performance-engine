<?php
/**
 * Low-overhead observation and asynchronous DOM learning.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class DOMAutoLearning {
	/** @var DOMAnalysisQueue */ private $queue;
	/** @var PageAnalysisCoordinator */ private $coordinator;
	/** @var Settings */ private $settings;
	/** @var bool */ private $dispatch_registered = false;

	public function __construct( DOMAnalysisQueue $queue, PageAnalysisCoordinator $coordinator, Settings $settings ) {
		$this->queue = $queue;
		$this->coordinator = $coordinator;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( DOMAnalysisQueue::CRON_HOOK, array( $this, 'process_queue' ) );
		add_action( 'npe/cache/purged', array( $this, 'cache_purged' ), 20, 2 );
		// Public LiteSpeed purge request hooks. No LiteSpeed classes or internals are used.
		add_action( 'litespeed_purge_all', array( $this, 'litespeed_cache_purged' ), 20 );
		add_action( 'litespeed_purge_url', array( $this, 'litespeed_url_purged' ), 20, 1 );
		if ( $this->settings->get( 'dom.enabled', false ) ) {
			add_action( 'template_redirect', array( $this, 'observe_request' ), 1000 );
			add_action( 'init', array( $this, 'seed_initial_scan' ), 1000 );
			if ( $this->queue->has_pending() ) {
				$this->dispatch_worker();
			}
		}
	}

	/** Ensure automatic learning starts even before a sampled cache miss occurs. */
	public function seed_initial_scan(): void {
		$state = $this->queue->state();
		if ( $this->queue->all() || ! empty( $state['known_urls'] ) ) {
			return;
		}
		if ( $this->queue->enqueue( home_url( '/' ), 'initial-scan', HOUR_IN_SECONDS ) ) {
			$this->dispatch_worker();
		}
	}

	public function observe_request(): void {
		if ( ! $this->is_public_request() ) {
			return;
		}
		$url = $this->current_url();
		if ( '' === $url || ! $this->is_sampled( $url ) ) {
			return;
		}
		$hours = (int) $this->settings->get( 'dom.cooldown_hours', 24 );
		if ( $this->queue->enqueue( $url, 'frontend-request', $hours * HOUR_IN_SECONDS ) ) {
			$this->dispatch_worker();
		}
	}

	public function process_queue(): void {
		if ( ! $this->settings->get( 'dom.enabled', false ) ) {
			return;
		}
		$batch_size = (int) $this->settings->get( 'dom.batch_size', 3 );
		$started = microtime( true );
		for ( $processed = 0; $processed < $batch_size && microtime( true ) - $started < 20; ++$processed ) {
			$job = $this->queue->claim();
			if ( null === $job ) {
				break;
			}
			$job_started = microtime( true );
			if ( $this->coordinator->request( (string) $job['url'] ) ) {
				$this->queue->complete( (string) $job['id'], microtime( true ) - $job_started );
			} else {
				$this->queue->retry( (string) $job['id'] );
			}
		}
		if ( $this->queue->has_pending() ) {
			$this->queue->schedule( (int) $this->settings->get( 'dom.scan_interval', 10 ) );
			$this->dispatch_worker();
		}
	}

	public function cache_purged( string $scope, array $values ): void {
		if ( ! $this->settings->get( 'dom.enabled', false ) ) {
			return;
		}
		if ( 'all' === $scope ) {
			$this->queue->requeue_known( 'npe-cache-purged' );
		} elseif ( 'urls' === $scope ) {
			foreach ( $values as $url ) {
				$this->queue->enqueue( (string) $url, 'npe-url-purged', HOUR_IN_SECONDS, true );
			}
		}
	}

	public function litespeed_cache_purged(): void {
		if ( $this->settings->get( 'dom.enabled', false ) ) {
			$this->queue->requeue_known( 'litespeed-cache-purged' );
		}
	}

	public function litespeed_url_purged( string $url = '' ): void {
		if ( $this->settings->get( 'dom.enabled', false ) ) {
			$this->queue->enqueue( $url, 'litespeed-url-purged', HOUR_IN_SECONDS, true );
		}
	}

	private function is_public_request(): bool {
		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( 'GET' !== $method || is_admin() || is_user_logged_in() || wp_doing_ajax() ) {
			return false;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return false;
		}
		if ( isset( $_GET[ PageAnalysisCoordinator::QUERY_ARG ] ) || ! empty( $_SERVER['QUERY_STRING'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request classification.
			return false;
		}
		$path = strtolower( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ) );
		foreach ( array( '/wp-admin', '/wp-login.php', '/cart', '/checkout', '/my-account', '/wc-api' ) as $excluded ) {
			if ( 0 === strpos( $path, $excluded ) ) {
				return false;
			}
		}
		return true;
	}

	private function current_url(): string {
		$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		return esc_url_raw( home_url( '/' . ltrim( $path, '/' ) ) );
	}

	private function is_sampled( string $url ): bool {
		$rate = (int) $this->settings->get( 'dom.sample_rate', 10 );
		if ( 100 === $rate ) {
			return true;
		}
		// Sample visits rather than permanently excluding 90% of URLs for a day.
		// Cooldown and queue hashes still deduplicate repeated discoveries.
		return random_int( 1, 100 ) <= max( 0, $rate );
	}

	private function dispatch_worker(): void {
		$this->queue->schedule( 0 );
		if ( $this->dispatch_registered || ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			return;
		}
		$this->dispatch_registered = true;
		add_action(
			'shutdown',
			static function (): void {
				if ( function_exists( 'spawn_cron' ) ) {
					spawn_cron( time() );
				}
			},
			PHP_INT_MAX
		);
	}
}
