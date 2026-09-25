<?php
/**
 * DOM analysis contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface DOMAnalyzerInterface {
	public function analyze( string $html, array $context = array() ): array;
}
