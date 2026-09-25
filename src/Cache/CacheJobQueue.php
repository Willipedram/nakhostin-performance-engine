<?php
/**
 * Deduplicated, retry-limited option-backed asynchronous job queue.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheJobQueue {
	private const MAX_JOBS = 500;
	private const MAX_ATTEMPTS = 3;

	/** @var string */ private $option;
	/** @var string */ private $lock_option;

	public function __construct( string $option ) {
		$this->option      = sanitize_key( $option );
		$this->lock_option = $this->option . '_lock';
	}

	public function enqueue( string $type, array $payload, string $reason = '', int $delay = 0 ): bool {
		$type        = sanitize_key( $type );
		$identity = $payload;
		unset( $identity['reason'], $identity['warm_urls'] );
		$fingerprint = hash( 'sha256', $type . '|' . (string) wp_json_encode( $identity ) );
		return $this->mutate(
			static function ( array $jobs ) use ( $type, $payload, $reason, $delay, $fingerprint ): array {
				foreach ( $jobs as $job ) {
					if ( $fingerprint === ( $job['fingerprint'] ?? '' ) && in_array( $job['status'] ?? '', array( 'pending', 'running' ), true ) ) {
						return $jobs;
					}
				}
				$jobs[] = array(
					'id'          => wp_generate_uuid4(),
					'type'        => $type,
					'payload'     => $payload,
					'reason'      => substr( sanitize_text_field( $reason ), 0, 160 ),
					'fingerprint' => $fingerprint,
					'status'      => 'pending',
					'attempts'    => 0,
					'not_before'  => time() + max( 0, $delay ),
					'created_at'  => time(),
					'last_error'  => '',
				);
				return array_slice( $jobs, -self::MAX_JOBS );
			}
		);
	}

	public function claim( int $limit = 5 ): array {
		$claimed = array();
		$this->mutate(
			static function ( array $jobs ) use ( $limit, &$claimed ): array {
				foreach ( $jobs as &$job ) {
					if ( 'running' === ( $job['status'] ?? '' ) && (int) ( $job['started_at'] ?? 0 ) < time() - 300 ) {
						$job['status'] = (int) ( $job['attempts'] ?? 0 ) >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
					}
					if ( count( $claimed ) >= max( 1, $limit ) ) {
						break;
					}
					if ( 'pending' !== ( $job['status'] ?? '' ) || (int) ( $job['not_before'] ?? 0 ) > time() ) {
						continue;
					}
					$job['status']     = 'running';
					$job['attempts']   = (int) $job['attempts'] + 1;
					$job['started_at'] = time();
					$claimed[]         = $job;
				}
				unset( $job );
				return $jobs;
			}
		);
		return $claimed;
	}

	public function complete( string $id ): bool {
		return $this->mutate( static function ( array $jobs ) use ( $id ): array { return array_values( array_filter( $jobs, static function ( array $job ) use ( $id ): bool { return $id !== ( $job['id'] ?? '' ); } ) ); } );
	}

	public function fail( string $id, string $error ): bool {
		return $this->mutate(
			static function ( array $jobs ) use ( $id, $error ): array {
				foreach ( $jobs as &$job ) {
					if ( $id !== ( $job['id'] ?? '' ) ) {
						continue;
					}
					$job['last_error'] = substr( sanitize_text_field( $error ), 0, 240 );
					if ( (int) $job['attempts'] >= self::MAX_ATTEMPTS ) {
						$job['status'] = 'failed';
					} else {
						$job['status']     = 'pending';
						$job['not_before'] = time() + ( 30 * (int) $job['attempts'] );
					}
				}
				unset( $job );
				return $jobs;
			}
		);
	}

	public function all(): array {
		$jobs = get_option( $this->option, array() );
		return is_array( $jobs ) ? $jobs : array();
	}

	public function counts(): array {
		$counts = array( 'pending' => 0, 'running' => 0, 'failed' => 0 );
		foreach ( $this->all() as $job ) {
			$status = $job['status'] ?? '';
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}
		return $counts;
	}

	private function mutate( callable $callback ): bool {
		if ( ! $this->acquire_lock() ) {
			return false;
		}
		try {
			$jobs = $callback( $this->all() );
			return is_array( $jobs ) && ( update_option( $this->option, $jobs, false ) || $jobs === $this->all() );
		} finally {
			delete_option( $this->lock_option );
		}
	}

	private function acquire_lock(): bool {
		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			if ( add_option( $this->lock_option, time(), '', false ) ) {
				return true;
			}
			$created = (int) get_option( $this->lock_option, 0 );
			if ( $created && $created < time() - 10 ) {
				delete_option( $this->lock_option );
				continue;
			}
			usleep( 5000 );
		}
		return false;
	}
}
