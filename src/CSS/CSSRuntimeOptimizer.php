<?php
/**
 * Applies precomputed CSS runtime decisions without parsing CSS on requests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class CSSRuntimeOptimizer {
	private const MAX_CRITICAL_CSS_BYTES = 32768;

	/** @var StylesheetStrategyApplier */
	private $applier;

	public function __construct( ?StylesheetStrategyApplier $applier = null ) {
		$this->applier = $applier ?: new StylesheetStrategyApplier();
	}

	public function apply( array $manifest, bool $safe_mode ): array {
		return $this->applier->apply( (array) ( $manifest['stylesheets'] ?? array() ), $safe_mode );
	}

	public function inline( string $css ): void {
		$css = trim( $css );
		if (
			'' === $css
			|| self::MAX_CRITICAL_CSS_BYTES < strlen( $css )
			|| false !== stripos( $css, '</style' )
			|| false !== stripos( $css, '@import' )
		) {
			return;
		}

		echo '<style id="npe-critical-css">' . $css . '</style>';
	}
}
