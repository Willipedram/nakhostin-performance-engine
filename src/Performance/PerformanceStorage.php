<?php
/** Bounded WordPress-option storage for performance samples. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;

final class PerformanceStorage {
	public const OPTION = 'npe_performance_samples';

	public function save( PerformanceSample $sample, int $retention_days = 7, int $maximum = 500 ): void {
		$now       = time();
		$cutoff    = $now - ( max( 1, min( 90, $retention_days ) ) * ( defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400 ) );
		$maximum   = max( 10, min( 2000, $maximum ) );
		$samples   = $this->all();
		$samples[] = $sample->to_array();
		$samples   = array_values( array_filter( $samples, static function ( array $item ) use ( $cutoff ): bool { return (int) ( $item['timestamp'] ?? 0 ) >= $cutoff; } ) );
		update_option( self::OPTION, array_slice( $samples, -$maximum ), false );
	}

	public function all(): array {
		$samples = get_option( self::OPTION, array() );
		if ( ! is_array( $samples ) ) {
			return array();
		}
		return array_values( array_filter( $samples, 'is_array' ) );
	}

	public function clear(): void {
		delete_option( self::OPTION );
	}
}
