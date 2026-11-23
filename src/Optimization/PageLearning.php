<?php
/** Bounded, per-page statistical evidence from repeated anonymous captures. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Optimization;

final class PageLearning {
	public const REQUIRED = 3;
	public const INTERVAL = 30;

	public function observe( string $url, string $identity, array $unused, ?int $now = null ): array {
		$key = 'npe_page_learning_' . hash( 'sha256', $url );
		$stored = get_transient( $key );
		$now = $now ?? time();
		if ( ! is_array( $stored ) || ( $stored['identity'] ?? '' ) !== $identity ) {
			$stored = array( 'identity' => $identity, 'samples' => 0, 'at' => 0, 'unused' => array_values( $unused ) );
		}
		$remaining = array_values( array_intersect( $stored['unused'], $unused ) );
		$changed = $remaining !== $stored['unused'];
		$stored['unused'] = $remaining;
		if ( 0 === $stored['samples'] || $now - $stored['at'] >= self::INTERVAL ) {
			$stored['samples'] = min( self::REQUIRED, $stored['samples'] + 1 );
			$stored['at'] = $now;
			$changed = true;
		}
		if ( $changed ) {
			set_transient( $key, $stored, 7 * DAY_IN_SECONDS );
		}
		return array( 'samples' => $stored['samples'], 'required' => self::REQUIRED, 'unused' => $stored['unused'] );
	}
}
