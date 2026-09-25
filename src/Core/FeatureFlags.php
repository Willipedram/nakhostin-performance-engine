<?php
/**
 * Safe feature flag reader.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class FeatureFlags {
	/** @var Settings */
	private $settings;

	/** @var array<string, bool> */
	private $available;

	public function __construct( Settings $settings, array $available = array() ) {
		$this->settings  = $settings;
		$this->available = array_fill_keys( Settings::MODULE_GROUPS, false );

		foreach ( $available as $feature => $is_available ) {
			if ( array_key_exists( $feature, $this->available ) ) {
				$this->available[ $feature ] = true === $is_available;
			}
		}
	}

	public function is_enabled( string $feature ): bool {
		if ( empty( $this->available[ $feature ] ) ) {
			return false;
		}

		return true === $this->settings->get( $feature . '.enabled', false );
	}

	public function is_available( string $feature ): bool {
		return ! empty( $this->available[ $feature ] );
	}
}
