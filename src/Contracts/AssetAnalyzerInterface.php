<?php
/**
 * Generic asset analysis contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface AssetAnalyzerInterface {
	public function analyze( string $document, array $assets = array() ): array;

	public function supports( string $asset_type ): bool;
}
