<?php
/** Stores a small admin-only history of imported laboratory reports. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Performance;

final class LighthouseHistory {
	public const OPTION = 'npe_lighthouse_history';

	public function all(): array {
		$rows = get_option( self::OPTION, array() );
		return is_array( $rows ) ? array_values( array_filter( $rows, static function ( $row ): bool {
			return is_array( $row ) && 1 === ( $row['schema'] ?? null );
		} ) ) : array();
	}

	public function add( array $row ): bool {
		$rows = $this->all();
		if ( in_array( $row, $rows, true ) ) {
			return true;
		}
		array_unshift( $rows, $row );
		return update_option( self::OPTION, array_slice( $rows, 0, 30 ), false );
	}

	/** Compare only identical laboratory configurations with at least three trials. */
	public function comparisons( array $rows ): array {
		$groups = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['configuration'] ) ) {
				continue;
			}
			$key = hash( 'sha256', (string) wp_json_encode( array( $row['path'], $row['profile'], $row['engine'], $row['configuration'] ) ) );
			$groups[ $key ][ $row['phase'] ][] = $row;
		}
		$comparisons = array();
		foreach ( $groups as $runs ) {
			if ( count( $runs['baseline'] ?? array() ) < 3 || count( $runs['candidate'] ?? array() ) < 3 ) {
				continue;
			}
			$latest = $runs['candidate'][0];
			$comparisons[] = array(
				'path'      => $latest['path'],
				'profile'   => $latest['profile'],
				'engine'    => $latest['engine'],
				'baseline'  => $this->medians( array_slice( $runs['baseline'], 0, 3 ) ),
				'candidate' => $this->medians( array_slice( $runs['candidate'], 0, 3 ) ),
			);
		}
		return $comparisons;
	}

	private function medians( array $runs ): array {
		$metrics = array();
		foreach ( $runs as $run ) {
			foreach ( array_merge( array( 'score' => $run['score'] ), $run['metrics'] ) as $name => $value ) {
				$metrics[ $name ][] = $value;
			}
		}
		foreach ( $metrics as $name => $values ) {
			sort( $values, SORT_NUMERIC );
			$metrics[ $name ] = $values[1];
		}
		return $metrics;
	}
}
