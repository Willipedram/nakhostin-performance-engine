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

	public function __construct( DOMAnalysisQueue $queue, PageAnalysisCoordinator $coordinator, Settings $settings ) {
		$this->queue = $queue;
		$this->coordinator = $coordinator;
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( DOMAnalysisQueue::CRON_HOOK, array( $this, 'process_queue' ) );
		if ( $this->settings->get( 'dom.enabled', false ) ) {
			add_action( 'template_redirect', array( $this, 'observe_request' ), 1000 );
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
		$this->queue->enqueue( $url, 'frontend-request', $hours * HOUR_IN_SECONDS );
	}

	public function process_queue(): void {
		if ( ! $this->settings->get( 'dom.enabled', false ) ) {
			return;
		}
		$job = $this->queue->claim();
		if ( null === $job ) {
			if ( $this->queue->has_pending() ) {
				$this->queue->schedule( 60 );
			}
			return;
		}
		if ( $this->coordinator->request( (string) $job['url'] ) ) {
			$this->queue->complete( (string) $job['id'] );
		} else {
			$this->queue->retry( (string) $job['id'] );
		}
		if ( $this->queue->has_pending() ) {
			$this->queue->schedule( 60 );
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
		$bucket = (int) sprintf( '%u', crc32( $url . '|' . gmdate( 'Y-m-d' ) ) ) % 100;
		return $bucket < $rate;
	}
}
