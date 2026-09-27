<?php
/**
 * Performance monitoring contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface PerformanceMonitorInterface {
	public function start( string $metric ): void;

	public function stop( string $metric ): float;

	public function report(): array;
}
