<?php
namespace Nakhostin\PerformanceEngine\Optimization;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
final class NpeOptimizationProvider implements OptimizationProviderInterface { private $settings; public function __construct( Settings $settings ) { $this->settings = $settings; } public function id(): string { return 'npe'; } public function owns( string $feature ): bool { return (bool) $this->settings->get( 'optimization.enabled', false ); } public function priority( string $feature ): int { return 10; } }
