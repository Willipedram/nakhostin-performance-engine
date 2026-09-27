<?php
/**
 * Converts stored stylesheet evidence into a fail-open runtime decision.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class CSSOptimizationPolicy {
	public const KEEP            = 'keep';
	public const DEFER           = 'defer';
	public const INLINE_CRITICAL = 'inline_critical';
	public const UNLOAD          = 'unload';
	public const UNKNOWN         = 'unknown';

	/**
	 * Decide whether a precomputed stylesheet decision is safe to apply.
	 *
	 * The media=print/onload defer technique can leave a document unstyled
	 * until JavaScript and the load event run, so legacy defer decisions fail
	 * open to KEEP. Safe mode preserves every original stylesheet.
	 */
	public function decide( array $evidence, bool $safe_mode = true ): string {
		$decision = (string) ( $evidence['decision'] ?? self::UNKNOWN );

		if ( $safe_mode || self::DEFER === $decision ) {
			return self::KEEP;
		}

		if ( self::UNLOAD === $decision && ! $this->can_unload( $evidence ) ) {
			return self::KEEP;
		}

		return in_array( $decision, array( self::KEEP, self::INLINE_CRITICAL, self::UNLOAD ), true )
			? $decision
			: self::KEEP;
	}

	private function can_unload( array $evidence ): bool {
		return ! empty( $evidence['high_confidence'] )
			&& ! empty( $evidence['component_absent'] )
			&& ! empty( $evidence['dependencies_satisfied'] )
			&& 3 <= (int) ( $evidence['observations'] ?? 0 );
	}
}
