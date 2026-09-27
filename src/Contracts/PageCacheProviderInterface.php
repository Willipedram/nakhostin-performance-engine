<?php
/** Page-cache provider capability contract. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Contracts;
interface PageCacheProviderInterface {
	public function id(): string;
	public function is_available(): bool;
	public function can_serve_early(): bool;
	public function priority(): int;
}
