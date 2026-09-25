<?php
/**
 * Cache warmup contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface CacheWarmupInterface {
	public function schedule( array $urls ): bool;

	public function cancel(): bool;
}
