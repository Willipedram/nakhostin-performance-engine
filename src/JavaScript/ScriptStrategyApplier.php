<?php
/**
 * Explicitly applies approved native WordPress loading strategies.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class ScriptStrategyApplier {
	public function apply_defer( JavaScriptManifest $manifest ): array {
		$applied = array();
		$skipped = array();

		foreach ( $manifest->to_array()['deferred'] ?? array() as $handle ) {
			$handle = sanitize_key( (string) $handle );
			if ( '' !== $handle && wp_script_add_data( $handle, 'strategy', 'defer' ) ) {
				$applied[] = $handle;
			} else {
				$skipped[] = $handle;
			}
		}

		return array( 'applied' => $applied, 'skipped' => $skipped );
	}
}
