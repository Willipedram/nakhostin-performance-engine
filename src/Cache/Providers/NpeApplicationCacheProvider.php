<?php
namespace Nakhostin\PerformanceEngine\Cache\Providers;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
final class NpeApplicationCacheProvider implements PageCacheProviderInterface { public function id(): string { return 'npe_application'; } public function is_available(): bool { return true; } public function can_serve_early(): bool { return false; } public function priority(): int { return 10; } }
