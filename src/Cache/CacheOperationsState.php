<?php
/**
 * Small operational status record for cache maintenance diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheOperationsState {
	public const OPTION = 'npe_cache_operations_state';

	public function record_purge( string $reason, array $result ): void {
		$this->record( 'last_purge', $reason, $result );
	}

	public function record_warmup( string $reason, array $result ): void {
		$this->record( 'last_warmup', $reason, $result );
	}

	public function all(): array {
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	private function record( string $key, string $reason, array $result ): void {
		$state         = $this->all();
		$state[ $key ] = array(
			'reason'     => substr( sanitize_text_field( $reason ), 0, 160 ),
			'result'     => $result,
			'finished_at' => time(),
		);
		update_option( self::OPTION, $state, false );
	}
}
