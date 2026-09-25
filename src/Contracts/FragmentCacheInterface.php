<?php
/**
 * Public fragment-cache API.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

use Nakhostin\PerformanceEngine\Cache\FragmentKey;
use Nakhostin\PerformanceEngine\Cache\FragmentResult;

interface FragmentCacheInterface {
	public function get( FragmentKey $key ): FragmentResult;

	public function put( FragmentKey $key, $value, int $ttl, array $dependencies = array() ): bool;

	public function delete( FragmentKey $key ): bool;

	public function invalidate_dependencies( array $dependencies ): int;

	public function flush(): int;

	public function remember( FragmentKey $key, int $ttl, array $dependencies, callable $generator ): FragmentResult;
}
