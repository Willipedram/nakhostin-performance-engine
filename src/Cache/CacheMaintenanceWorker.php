<?php
/**
 * Small WP-Cron workers for purge and warmup queues.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Throwable;

final class CacheMaintenanceWorker {
	/** @var SmartPurgeManager */ private $purges;
	/** @var CacheWarmupManager */ private $warmups;
	/** @var CacheWarmer */ private $warmer;
	/** @var CacheOperationsState */ private $state;
	/** @var bool */ private $running = false;

	public function __construct( SmartPurgeManager $purges, CacheWarmupManager $warmups, CacheWarmer $warmer, CacheOperationsState $state ) {
		$this->purges  = $purges;
		$this->warmups = $warmups;
		$this->warmer  = $warmer;
		$this->state   = $state;
	}

	public function register(): void {
		add_action( SmartPurgeManager::CRON_HOOK, array( $this, 'run_purges' ) );
		add_action( CacheWarmupManager::CRON_HOOK, array( $this, 'run_warmups' ) );
	}

	public function run_purges(): void {
		if ( $this->running ) {
			return;
		}
		$this->running = true;
		foreach ( $this->purges->queue()->claim( 5 ) as $job ) {
			try {
				$payload           = (array) $job['payload'];
				$payload['reason'] = (string) ( $job['reason'] ?? 'unspecified' );
				$request           = PurgeRequest::from_array( $payload );
				$result  = $this->purges->execute( $request );
				$this->purges->queue()->complete( (string) $job['id'] );
				$this->state->record_purge( $request->reason(), $result );
			} catch ( Throwable $exception ) {
				$this->purges->queue()->fail( (string) $job['id'], $exception->getMessage() );
				$this->state->record_purge( (string) ( $job['reason'] ?? 'purge_failed' ), array( 'success' => false, 'error' => $exception->getMessage() ) );
			}
		}
		$this->running = false;
		if ( $this->purges->queue()->counts()['pending'] > 0 && ! wp_next_scheduled( SmartPurgeManager::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, SmartPurgeManager::CRON_HOOK );
		}
	}

	public function run_warmups(): void {
		if ( $this->running ) {
			return;
		}
		$this->running = true;
		foreach ( $this->warmups->queue()->claim( 3 ) as $job ) {
			try {
				$url    = (string) ( $job['payload']['url'] ?? '' );
				$result = $this->warmer->warm( $url );
				if ( empty( $result['success'] ) ) {
					throw new \RuntimeException( (string) ( $result['error'] ?? 'Warmup failed.' ) );
				}
				$this->warmups->queue()->complete( (string) $job['id'] );
				$this->state->record_warmup( (string) $job['reason'], $result );
			} catch ( Throwable $exception ) {
				$this->warmups->queue()->fail( (string) $job['id'], $exception->getMessage() );
				$this->state->record_warmup( (string) ( $job['reason'] ?? 'warmup_failed' ), array( 'success' => false, 'error' => $exception->getMessage() ) );
			}
		}
		$this->running = false;
		if ( $this->warmups->queue()->counts()['pending'] > 0 && ! wp_next_scheduled( CacheWarmupManager::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, CacheWarmupManager::CRON_HOOK );
		}
	}
}
