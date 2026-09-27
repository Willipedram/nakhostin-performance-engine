<?php
namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
final class LiteSpeedCacheProvider implements PageCacheProviderInterface { private $detector; private $settings; public function __construct( LiteSpeedDetector $detector, Settings $settings ) { $this->detector = $detector; $this->settings = $settings; } public function id(): string { return 'litespeed'; } public function is_available(): bool { return ! empty( $this->detector->detect()['active'] ) && (bool) $this->settings->get( 'litespeed.enabled', true ) && 'independent' !== $this->settings->get( 'litespeed.mode', 'compatible' ); } public function can_serve_early(): bool { return $this->is_available() && ! empty( $this->detector->detect()['cache_capable'] ); } public function priority(): int { return 400; } }
