<?php
namespace Nakhostin\PerformanceEngine\Cache\Providers;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
final class ExternalCacheProvider implements PageCacheProviderInterface { private $environment; public function __construct( EnvironmentReport $environment ) { $this->environment = $environment; } public function id(): string { return 'external'; } public function is_available(): bool { $dropin = (array) $this->environment->get( 'dropin', array() ); if ( ! empty( $dropin['exists'] ) && empty( $dropin['npe_owned'] ) && empty( $dropin['replaceable'] ) ) { return true; } $plugins = array_diff( (array) $this->environment->get( 'cache_plugins', array() ), array( 'litespeed-cache/litespeed-cache.php' ) ); return ! empty( $plugins ); } public function can_serve_early(): bool { return $this->is_available(); } public function priority(): int { return 300; } }
