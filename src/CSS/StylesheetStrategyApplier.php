<?php
/**
 * Applies only stylesheet decisions that cannot delay first paint.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class StylesheetStrategyApplier {
	public function apply( array $decisions, bool $safe_mode = true ): array {
		$policy  = new CSSOptimizationPolicy();
		$applied = array();

		foreach ( $decisions as $handle => $evidence ) {
			$handle = sanitize_key( (string) $handle );
			if ( '' === $handle ) {
				continue;
			}

			$decision = $policy->decide( (array) $evidence, $safe_mode );
			if ( CSSOptimizationPolicy::UNLOAD !== $decision ) {
				continue;
			}

			wp_dequeue_style( $handle );
			$applied[ $handle ] = $decision;
		}

		return $applied;
	}

	/**
	 * Keep compatibility with an old callback registration while restoring the
	 * original blocking link tag without mutation.
	 */
	public function filter_tag( string $html, string $handle ): string {
		return $html;
	}
}
