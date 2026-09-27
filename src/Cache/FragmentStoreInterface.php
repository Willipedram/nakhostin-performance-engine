<?php
/**
 * Storage boundary shared by filesystem and object-cache fragment backends.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

interface FragmentStoreInterface {
	public function get( string $key ): ?FragmentEntry;
	public function set( FragmentEntry $entry, int $ttl ): bool;
	public function delete( string $key ): bool;
	public function invalidate_dependencies( array $dependencies ): int;
	public function flush(): int;
	public function acquire_lock( string $key, int $ttl ): bool;
	public function release_lock( string $key ): void;
	public function statistics(): array;
	public function top( int $limit = 10 ): array;
	public function backend_name(): string;
}
