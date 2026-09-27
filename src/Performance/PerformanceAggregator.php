<?php
/** Statistical aggregation of bounded performance samples. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;

final class PerformanceAggregator {
	private const METRICS = array( 'backend_generation_ms', 'wordpress_bootstrap_ms', 'plugin_loading_ms', 'theme_loading_ms', 'response_generation_ms', 'database_query_count', 'database_query_time_ms', 'memory_peak_bytes' );

	public function summarize( array $samples ): array {
		$summary = array( 'samples' => count( $samples ), 'cache_hit_ratio' => null, 'metrics' => array(), 'breakdown' => array() );
		foreach ( self::METRICS as $metric ) {
			$values = array_map( static function ( array $sample ) use ( $metric ) { return $sample[ $metric ] ?? null; }, $samples );
			$summary['metrics'][ $metric ] = $this->statistics( $values );
		}
		$cacheable = array_values( array_filter( $samples, static function ( array $sample ): bool { return in_array( $sample['cache_state'] ?? '', array( 'hit', 'stale', 'miss' ), true ); } ) );
		if ( $cacheable ) {
			$hits = count( array_filter( $cacheable, static function ( array $sample ): bool { return in_array( $sample['cache_state'], array( 'hit', 'stale' ), true ); } ) );
			$summary['cache_hit_ratio'] = round( ( $hits / count( $cacheable ) ) * 100, 2 );
		}
		$summary['breakdown'] = array( 'page_type' => array(), 'template' => array(), 'component' => array(), 'cache_state' => array() );
		foreach ( $samples as $sample ) {
			$this->add_group( $summary['breakdown']['page_type'], (string) ( $sample['page_type'] ?? 'unknown' ), $sample );
			$this->add_group( $summary['breakdown']['template'], (string) ( $sample['template'] ?? 'default' ), $sample );
			$this->add_group( $summary['breakdown']['cache_state'], (string) ( $sample['cache_state'] ?? 'unknown' ), $sample );
			foreach ( $sample['components'] ?? array() as $component ) { $this->add_group( $summary['breakdown']['component'], (string) $component, $sample ); }
		}
		foreach ( $summary['breakdown'] as $dimension => $groups ) {
			foreach ( $groups as $key => $group ) { $summary['breakdown'][ $dimension ][ $key ] = $this->summarize_group( $group ); }
		}
		return $summary;
	}

	private function add_group( array &$groups, string $key, array $sample ): void {
		$key = sanitize_key( $key ) ?: 'unknown';
		$groups[ $key ][] = $sample;
	}

	private function summarize_group( array $samples ): array {
		$cacheable = array_values( array_filter( $samples, static function ( array $sample ): bool { return in_array( $sample['cache_state'] ?? '', array( 'hit', 'stale', 'miss' ), true ); } ) );
		$hits      = count( array_filter( $cacheable, static function ( array $sample ): bool { return in_array( $sample['cache_state'], array( 'hit', 'stale' ), true ); } ) );
		return array(
			'samples'         => count( $samples ),
			'cache_hit_ratio' => $cacheable ? round( ( $hits / count( $cacheable ) ) * 100, 2 ) : null,
			'php_ms'          => $this->statistics( array_column( $samples, 'backend_generation_ms' ) )['average'],
			'database_ms'     => $this->statistics( array_column( $samples, 'database_query_time_ms' ) )['average'],
		);
	}

	private function statistics( array $values ): array {
		$values = array_map( 'floatval', array_values( array_filter( $values, 'is_numeric' ) ) );
		if ( ! $values ) {
			return array( 'average' => null, 'median' => null, 'minimum' => null, 'maximum' => null );
		}
		sort( $values, SORT_NUMERIC );
		$count  = count( $values );
		$middle = intdiv( $count, 2 );
		$median = 0 === $count % 2 ? ( $values[ $middle - 1 ] + $values[ $middle ] ) / 2 : $values[ $middle ];
		return array( 'average' => round( array_sum( $values ) / $count, 3 ), 'median' => round( $median, 3 ), 'minimum' => $values[0], 'maximum' => $values[ $count - 1 ] );
	}
}
