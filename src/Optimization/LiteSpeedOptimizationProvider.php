<?php
namespace Nakhostin\PerformanceEngine\Optimization;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedDetector;
final class LiteSpeedOptimizationProvider implements OptimizationProviderInterface { private $detector; public function __construct( LiteSpeedDetector $detector ) { $this->detector = $detector; } public function id(): string { return 'litespeed'; } public function owns( string $feature ): bool { $active = ! empty( $this->detector->detect()['active'] ); return $active && (bool) apply_filters( 'npe/litespeed/owns_optimization', false, $feature ); } public function priority( string $feature ): int { return 90; } }
