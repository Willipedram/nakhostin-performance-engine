<?php
/**
 * Third-party integration boundary.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface IntegrationInterface {
	public function get_id(): string;

	public function is_available(): bool;

	public function is_enabled(): bool;

	public function register(): void;
}
