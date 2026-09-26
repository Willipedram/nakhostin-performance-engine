<?php
/**
 * Boundary for future early/server cache adapters; no adapter is enabled here.
 *
 * @package NakhostinPerformanceEngine
 */
namespace Nakhostin\PerformanceEngine\Cache;
interface ServerCacheAdapterInterface {
	public function identifier(): string;
	public function is_supported(): bool;
	public function installation_instructions(): array;
	public function purge_all(): bool;
}
