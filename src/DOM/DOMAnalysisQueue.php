<?php
/**
 * Bounded, deduplicated queue and progress state for deferred page intelligence.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMAnalysisQueue {
	public const OPTION = 'npe_dom_analysis_queue';
	public const STATE_OPTION = 'npe_dom_analysis_state';
	public const LOCK_OPTION = 'npe_dom_analysis_queue_lock';
	public const CRON_HOOK = 'npe/dom/process_analysis_queue';
	private const MAX_JOBS = 100;
	private const MAX_KNOWN_URLS = 100;
	private const MAX_ATTEMPTS = 3;

	public function enqueue( string $url, string $reason = 'frontend-request', int $cooldown = DAY_IN_SECONDS, bool $force = false ): bool {
		$url = $this->normalize_url( $url );
		if ( '' === $url ) {
			return false;
		}
		$cooldown_key = $this->cooldown_key( $url );
		if ( $force ) {
			delete_transient( $cooldown_key );
		} elseif ( false !== get_transient( $cooldown_key ) ) {
			return false;
		}

		$id     = hash( 'sha256', $url );
		$queued = $this->with_lock(
			static function ( array $jobs, array $state ) use ( $id, $url, $reason, $force ): array {
				$jobs = array_filter(
					$jobs,
					static function ( $job ): bool {
						return is_array( $job ) && ( 'failed' !== ( $job['status'] ?? '' ) || (int) ( $job['created_at'] ?? 0 ) > time() - ( 7 * DAY_IN_SECONDS ) );
					}
				);
				if ( $force && isset( $jobs[ $id ] ) && 'failed' === ( $jobs[ $id ]['status'] ?? '' ) ) {
					unset( $jobs[ $id ] );
				}
				if ( isset( $jobs[ $id ] ) || count( $jobs ) >= self::MAX_JOBS ) {
					return array( $jobs, $state, false );
				}
				$active_jobs = array_filter(
					$jobs,
					static function ( array $job ): bool {
						return in_array( $job['status'] ?? '', array( 'pending', 'running' ), true );
					}
				);
				if ( ! $active_jobs ) {
					$state['run_total'] = 0;
					$state['run_completed'] = 0;
					$state['run_failed'] = 0;
					$state['run_started_at'] = time();
				}
				$jobs[ $id ] = array(
					'id'           => $id,
					'url'          => $url,
					'reason'       => sanitize_key( $reason ),
					'status'       => 'pending',
					'attempts'     => 0,
					'available_at' => time(),
					'created_at'   => time(),
					'last_error'   => '',
				);
				$state['run_total'] = (int) ( $state['run_total'] ?? 0 ) + 1;
				$state['discovered'] = (int) ( $state['discovered'] ?? 0 ) + 1;
				$known = is_array( $state['known_urls'] ?? null ) ? $state['known_urls'] : array();
				$known[ $id ] = $url;
				$state['known_urls'] = array_slice( $known, -self::MAX_KNOWN_URLS, null, true );
				return array( $jobs, $state, true );
			}
		);

		if ( $queued ) {
			set_transient( $cooldown_key, 1, max( HOUR_IN_SECONDS, $cooldown ) );
			// Make the event immediately eligible. DOMAutoLearning dispatches WP-Cron
			// non-blockingly at shutdown so low-traffic sites do not remain at 0%.
			$this->schedule( 0 );
		}
		return $queued;
	}

	public function requeue_known( string $reason = 'cache-purged' ): int {
		$state = $this->state();
		$count = 0;
		foreach ( (array) ( $state['known_urls'] ?? array() ) as $url ) {
			if ( $this->enqueue( (string) $url, $reason, HOUR_IN_SECONDS, true ) ) {
				++$count;
			}
		}
		if ( $count > 0 ) {
			$this->increment_invalidation_count();
		}
		return $count;
	}

	public function claim(): ?array {
		$claimed = null;
		$this->with_lock(
			static function ( array $jobs, array $state ) use ( &$claimed ): array {
				foreach ( $jobs as $id => $job ) {
					if ( ! is_array( $job ) ) {
						unset( $jobs[ $id ] );
						continue;
					}
					if ( 'running' === ( $job['status'] ?? '' ) && (int) ( $job['claimed_at'] ?? 0 ) < time() - ( 10 * MINUTE_IN_SECONDS ) ) {
						$jobs[ $id ]['status'] = 'pending';
						$job['status'] = 'pending';
					}
					if ( 'pending' !== ( $job['status'] ?? '' ) || (int) ( $job['available_at'] ?? 0 ) > time() ) {
						continue;
					}
					$jobs[ $id ]['status'] = 'running';
					$jobs[ $id ]['claimed_at'] = time();
					$claimed = $jobs[ $id ];
					break;
				}
				return array( $jobs, $state, null !== $claimed );
			}
		);
		return $claimed;
	}

	public function complete( string $id, float $duration = 0.0 ): void {
		$this->with_lock(
			static function ( array $jobs, array $state ) use ( $id, $duration ): array {
				if ( ! isset( $jobs[ $id ] ) ) {
					return array( $jobs, $state, false );
				}
				unset( $jobs[ $id ] );
				$completed = (int) ( $state['completed'] ?? 0 );
				$average = (float) ( $state['average_seconds'] ?? 0.0 );
				$state['completed'] = $completed + 1;
				$state['run_completed'] = (int) ( $state['run_completed'] ?? 0 ) + 1;
				$state['last_completed_at'] = time();
				$state['average_seconds'] = $duration > 0 ? ( ( $average * $completed ) + $duration ) / ( $completed + 1 ) : $average;
				return array( $jobs, $state, true );
			}
		);
	}

	public function retry( string $id, string $error = 'analysis-failed' ): void {
		$this->with_lock(
			static function ( array $jobs, array $state ) use ( $id, $error ): array {
				if ( ! isset( $jobs[ $id ] ) || ! is_array( $jobs[ $id ] ) ) {
					return array( $jobs, $state, false );
				}
				$attempts = (int) $jobs[ $id ]['attempts'] + 1;
				$jobs[ $id ]['attempts'] = $attempts;
				$jobs[ $id ]['last_error'] = sanitize_key( $error );
				$jobs[ $id ]['status'] = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
				$jobs[ $id ]['available_at'] = time() + min( HOUR_IN_SECONDS, 30 * ( 2 ** $attempts ) );
				if ( $attempts >= self::MAX_ATTEMPTS ) {
					$state['failed'] = (int) ( $state['failed'] ?? 0 ) + 1;
					$state['run_failed'] = (int) ( $state['run_failed'] ?? 0 ) + 1;
				}
				return array( $jobs, $state, true );
			}
		);
	}

	public function retry_failed(): int {
		$retried = (int) $this->with_lock(
			static function ( array $jobs, array $state ): array {
				$count = 0;
				foreach ( $jobs as $id => $job ) {
					if ( ! is_array( $job ) || 'failed' !== ( $job['status'] ?? '' ) ) {
						continue;
					}
					$jobs[ $id ]['status'] = 'pending';
					$jobs[ $id ]['attempts'] = 0;
					$jobs[ $id ]['available_at'] = time();
					$jobs[ $id ]['last_error'] = '';
					++$count;
				}
				$state['run_failed'] = max( 0, (int) ( $state['run_failed'] ?? 0 ) - $count );
				return array( $jobs, $state, $count );
			}
		);
		if ( $retried > 0 ) {
			$this->schedule( 0 );
		}
		return $retried;
	}

	public function all(): array {
		$jobs = get_option( self::OPTION, array() );
		return is_array( $jobs ) ? array_values( array_filter( $jobs, 'is_array' ) ) : array();
	}

	public function state(): array {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	public function report( int $batch_size = 3, int $interval = 10 ): array {
		$state = $this->state();
		$counts = array( 'pending' => 0, 'running' => 0, 'failed' => 0 );
		$last_error = '';
		foreach ( $this->all() as $job ) {
			$status = (string) ( $job['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
			if ( '' !== (string) ( $job['last_error'] ?? '' ) ) {
				$last_error = (string) $job['last_error'];
			}
		}
		$total = max( 0, (int) ( $state['run_total'] ?? 0 ) );
		$processed = min( $total, (int) ( $state['run_completed'] ?? 0 ) + (int) ( $state['run_failed'] ?? 0 ) );
		$remaining = $counts['pending'] + $counts['running'];
		$average = max( 0.1, (float) ( $state['average_seconds'] ?? 1.0 ) );
		$eta = $remaining > 0 ? (int) ceil( $remaining * $average + ceil( $remaining / max( 1, $batch_size ) ) * max( 1, $interval ) ) : 0;
		return array_merge(
			$counts,
			array(
				'wp_cron_disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'next_run_at'       => (int) ( wp_next_scheduled( self::CRON_HOOK ) ?: 0 ),
				'progress'          => $total > 0 ? (int) round( 100 * $processed / $total ) : 0,
				'run_total'         => $total,
				'run_completed'     => (int) ( $state['run_completed'] ?? 0 ),
				'completed'         => (int) ( $state['completed'] ?? 0 ),
				'failed_total'      => (int) ( $state['failed'] ?? 0 ),
				'known_pages'       => count( (array) ( $state['known_urls'] ?? array() ) ),
				'estimated_seconds' => $eta,
				'last_completed_at' => (int) ( $state['last_completed_at'] ?? 0 ),
				'last_error'        => $last_error,
				'invalidations'     => (int) ( $state['invalidations'] ?? 0 ),
			)
		);
	}

	public function has_pending(): bool {
		foreach ( $this->all() as $job ) {
			if ( 'pending' === ( $job['status'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	public function schedule( int $delay = 3 ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + max( 0, $delay ), self::CRON_HOOK );
		}
	}

	private function increment_invalidation_count(): void {
		$this->with_lock(
			static function ( array $jobs, array $state ): array {
				$state['invalidations'] = (int) ( $state['invalidations'] ?? 0 ) + 1;
				$state['last_invalidation_at'] = time();
				return array( $jobs, $state, true );
			}
		);
	}

	private function normalize_url( string $url ): string {
		$url = esc_url_raw( $url );
		$home = wp_parse_url( home_url( '/' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['host'] ) ) {
			return '';
		}
		$home_port = (int) ( $home['port'] ?? ( 'http' === ( $home['scheme'] ?? 'https' ) ? 80 : 443 ) );
		$url_port = (int) ( $parts['port'] ?? ( 'http' === ( $parts['scheme'] ?? 'https' ) ? 80 : 443 ) );
		if ( strtolower( (string) $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) ) || $home_port !== $url_port ) {
			return '';
		}
		$scheme = strtolower( (string) ( $home['scheme'] ?? 'https' ) );
		$path = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$port = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
		return $scheme . '://' . strtolower( (string) $home['host'] ) . $port . $path;
	}

	private function cooldown_key( string $url ): string {
		return 'npe_dom_seen_' . substr( hash( 'sha256', $url ), 0, 32 );
	}

	private function with_lock( callable $callback ) {
		if ( ! add_option( self::LOCK_OPTION, time(), '', 'no' ) ) {
			$locked_at = (int) get_option( self::LOCK_OPTION, 0 );
			if ( $locked_at > time() - 30 ) {
				return false;
			}
			delete_option( self::LOCK_OPTION );
			if ( ! add_option( self::LOCK_OPTION, time(), '', 'no' ) ) {
				return false;
			}
		}
		try {
			$jobs = get_option( self::OPTION, array() );
			$state = get_option( self::STATE_OPTION, array() );
			list( $jobs, $state, $result ) = $callback( is_array( $jobs ) ? $jobs : array(), is_array( $state ) ? $state : array() );
			update_option( self::OPTION, $jobs, false );
			update_option( self::STATE_OPTION, $state, false );
			return $result;
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}
}
