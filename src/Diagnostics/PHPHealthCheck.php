<?php
/** PHP runtime diagnostics without configuration mutation. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
final class PHPHealthCheck { public function run(): array { $opcache = function_exists( 'opcache_get_status' ) ? @opcache_get_status( false ) : false; return array( 'version' => PHP_VERSION, 'memory_limit' => (string) ini_get( 'memory_limit' ), 'max_execution_time' => (int) ini_get( 'max_execution_time' ), 'opcache_available' => function_exists( 'opcache_get_status' ), 'opcache_enabled' => is_array( $opcache ) && ! empty( $opcache['opcache_enabled'] ) ); } }
