<?php
/**
 * Versioned data migration contract.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Contracts;

interface MigrationInterface {
	public function get_version(): int;

	public function up(): void;
}
