<?php
/**
 * Read-only component integration adapter.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Components;

use Nakhostin\PerformanceEngine\Contracts\IntegrationInterface;

interface ComponentAdapterInterface extends IntegrationInterface {
	public function inspect( array $manifest ): array;
}
