<?php
/**
 * Storage adapter contract for cache backends.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface CacheStoreInterface extends CacheInterface {
	public function flush(): bool;

	public function get_name(): string;
}
