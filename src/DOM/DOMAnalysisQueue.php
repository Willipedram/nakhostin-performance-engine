<?php
/**
 * Bounded, deduplicated queue for deferred page intelligence jobs.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMAnalysisQueue {
	public const OPTION = 'npe_dom_analysis_queue';
	public const LOCK_OPTION = 'npe_dom_analysis_queue_lock';
	public const CRON_HOOK = 'npe/dom/process_analysis_queue';
	private const MAX_JOBS = 100;
	private const MAX_ATTEMPTS = 3;

	public function enqueue( string $url, string $reason = 'frontend-request', int $cooldown = DAY_IN_SECONDS ): bool {
		$url = $this->normalize_url( $url );
		if ( '' === $url || false !== get_transient( $this->cooldown_key( $url ) ) ) {
			return false;
		}

		$id     = hash( 'sha256', $url );
		$queued = $this->with_lock(
			static function ( array $jobs ) use ( $id, $url, $reason ): array {
				$jobs = array_filter(
					$jobs,
					static function ( $job ): bool {
						return is_array( $job ) && ( 'failed' !== ( $job['status'] ?? '' ) || (int) ( $job['created_at'] ?? 0 ) > time() - ( 7 * DAY_IN_SECONDS ) );
					}
				);
				if ( isset( $jobs[ $id ] ) || count( $jobs ) >= self::MAX_JOBS ) {
					return array( $jobs, false );
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
				return array( $jobs, true );
			}
		);

		if ( $queued ) {
			set_transient( $this->cooldown_key( $url ), 1, max( HOUR_IN_SECONDS, $cooldown ) );
			$this->schedule();
		}
		return $queued;
	}

	public function claim(): ?array {
		$claimed = null;
		$this->with_lock(
			static function ( array $jobs ) use ( &$claimed ): array {
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
				return array( $jobs, null !== $claimed );
			}
		);
		return $claimed;
	}

	public function complete( string $id ): void {
		$this->with_lock(
			static function ( array $jobs ) use ( $id ): array {
				unset( $jobs[ $id ] );
				return array( $jobs, true );
			}
		);
	}

	public function retry( string $id, string $error = 'analysis-failed' ): void {
		$this->with_lock(
			static function ( array $jobs ) use ( $id, $error ): array {
				if ( ! isset( $jobs[ $id ] ) ) {
					return array( $jobs, false );
				}
				$attempts = (int) $jobs[ $id ]['attempts'] + 1;
				$jobs[ $id ]['attempts'] = $attempts;
				$jobs[ $id ]['last_error'] = sanitize_key( $error );
				$jobs[ $id ]['status'] = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
				$jobs[ $id ]['available_at'] = time() + min( HOUR_IN_SECONDS, 60 * ( 2 ** $attempts ) );
				return array( $jobs, true );
			}
		);
	}

	public function all(): array {
		$jobs = get_option( self::OPTION, array() );
		return is_array( $jobs ) ? array_values( array_filter( $jobs, 'is_array' ) ) : array();
	}

	public function has_pending(): bool {
		foreach ( $this->all() as $job ) {
			if ( 'pending' === ( $job['status'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	public function schedule( int $delay = 10 ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + max( 1, $delay ), self::CRON_HOOK );
		}
	}

	private function normalize_url( string $url ): string {
		$url  = esc_url_raw( $url );
		$home = wp_parse_url( home_url( '/' ) );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! is_array( $home ) || empty( $parts['host'] ) ) {
			return '';
		}
		$home_port = (int) ( $home['port'] ?? ( 'http' === ( $home['scheme'] ?? 'https' ) ? 80 : 443 ) );
		$url_port  = (int) ( $parts['port'] ?? ( 'http' === ( $parts['scheme'] ?? 'https' ) ? 80 : 443 ) );
		if ( strtolower( (string) $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) ) || $home_port !== $url_port ) {
			return '';
		}
		$scheme = strtolower( (string) ( $home['scheme'] ?? 'https' ) );
		$path   = '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
		$port   = isset( $home['port'] ) ? ':' . (int) $home['port'] : '';
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
			list( $jobs, $result ) = $callback( is_array( $jobs ) ? $jobs : array() );
			update_option( self::OPTION, $jobs, false );
			return $result;
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}
}
