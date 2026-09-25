<?php
/**
 * JavaScript analysis contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface JavaScriptAnalyzerInterface extends AssetAnalyzerInterface {
	public function discover_dependencies( string $script ): array;
}
