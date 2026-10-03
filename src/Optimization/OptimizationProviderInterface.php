<?php
namespace Nakhostin\PerformanceEngine\Optimization;
interface OptimizationProviderInterface { public function id(): string; public function owns( string $feature ): bool; public function priority( string $feature ): int; }
