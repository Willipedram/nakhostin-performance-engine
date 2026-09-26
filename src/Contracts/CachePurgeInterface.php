<?php
/**
 * Cache invalidation contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface CachePurgeInterface {
	public function purge_all(): bool;

	public function purge_urls( array $urls ): bool;

	public function purge_tags( array $tags ): bool;
}
