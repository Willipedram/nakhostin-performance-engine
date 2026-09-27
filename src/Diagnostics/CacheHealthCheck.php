<?php
/** Read-only cache stack health report. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
use Nakhostin\PerformanceEngine\Cache\CacheRuntimeMode;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheHealthCheck;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
final class CacheHealthCheck { private $environment; private $mode; private $early; public function __construct( EnvironmentReport $environment, CacheRuntimeMode $mode, EarlyCacheHealthCheck $early ) { $this->environment = $environment; $this->mode = $mode; $this->early = $early; } public function run(): array { $early = $this->early->run(); return array_merge( array( 'server' => $this->environment->server(), 'page_cache_owner' => $this->mode->owner(), 'ownership_reason' => $this->mode->reason(), 'object_cache_persistent' => $this->environment->has_persistent_object_cache(), 'redis_available' => (bool) $this->environment->get( 'redis_available', false ), 'redis_active' => (bool) $this->environment->get( 'redis_active', false ), 'memcached_available' => (bool) $this->environment->get( 'memcached_available', false ), 'litespeed_available' => CacheRuntimeMode::LITESPEED === $this->mode->owner(), 'conflict' => CacheRuntimeMode::CONFLICT === $this->mode->owner() ), $early ); } }
