<?php
/**
 * Backend-neutral page-cache persistence contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Contracts\CacheStoreInterface;

interface PageCacheStoreInterface extends CacheStoreInterface {
	public function read( string $key ): ?CacheEntry;
	public function write( CacheEntry $entry ): bool;
	public function purge_urls( array $urls ): int;
	public function purge_dependencies( array $dependencies ): int;
	public function statistics(): array;
	public function cached_urls(): array;
}
