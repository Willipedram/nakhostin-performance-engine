<?php
/**
 * CSS analysis contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface CSSAnalyzerInterface extends AssetAnalyzerInterface {
	public function extract_selectors( string $css ): array;
}
