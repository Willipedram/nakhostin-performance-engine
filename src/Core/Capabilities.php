<?php
/**
 * Centralized authorization policy.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

final class Capabilities {
	public const MANAGE = 'manage_options';

	public function can_manage(): bool {
		return current_user_can( self::MANAGE );
	}
}
