<?php
namespace Nakhostin\PerformanceEngine\Cache\Providers;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
final class NpeEarlyCacheProvider implements PageCacheProviderInterface { private $environment; public function __construct( EnvironmentReport $environment ) { $this->environment = $environment; } public function id(): string { return 'npe_early'; } public function is_available(): bool { return $this->environment->supports_early_cache(); } public function can_serve_early(): bool { return $this->is_available(); } public function priority(): int { return 200; } }
