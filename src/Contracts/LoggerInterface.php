<?php
/**
 * Debug logging boundary.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface LoggerInterface {
	public function debug( string $message, array $context = array() ): void;

	public function warning( string $message, array $context = array() ): void;

	public function error( string $message, array $context = array() ): void;
}
