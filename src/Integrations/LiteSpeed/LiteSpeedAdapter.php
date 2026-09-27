<?php
/**
 * Optional LiteSpeed Cache integration and ownership policy.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;

use Nakhostin\PerformanceEngine\Contracts\IntegrationInterface;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class LiteSpeedAdapter implements IntegrationInterface {
	public const MODE_INDEPENDENT = 'independent';
	public const MODE_COMPATIBLE = 'compatible';
	public const MODE_COOPERATIVE = 'cooperative';

	/** @var LiteSpeedDetector */ private $detector;
	/** @var LiteSpeedCacheBridge */ private $cache_bridge;
	/** @var LiteSpeedPurgeBridge */ private $purge_bridge;
	/** @var Settings */ private $settings;
	/** @var bool */ private $registered = false;

	public function __construct( LiteSpeedDetector $detector, LiteSpeedCacheBridge $cache_bridge, LiteSpeedPurgeBridge $purge_bridge, Settings $settings ) {
		$this->detector     = $detector;
		$this->cache_bridge = $cache_bridge;
		$this->purge_bridge = $purge_bridge;
		$this->settings     = $settings;
	}

	public function get_id(): string {
		return 'litespeed';
	}

	public function is_available(): bool {
		return (bool) $this->detector->detect()['active'];
	}

	public function is_enabled(): bool {
		return $this->is_available()
			&& (bool) $this->settings->get( 'litespeed.enabled', true )
			&& self::MODE_INDEPENDENT !== $this->mode();
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}
		$this->registered = true;
		if ( ! $this->is_enabled() ) {
			return;
		}
		$this->cache_bridge->register( $this->mode() );
		if ( self::MODE_COOPERATIVE === $this->mode() ) {
			$this->purge_bridge->register();
		}
		add_filter( 'npe/css/optimization_enabled', array( $this, 'prevent_duplicate_optimization' ) );
		add_filter( 'npe/javascript/optimization_enabled', array( $this, 'prevent_duplicate_optimization' ) );
	}

	public function mode(): string {
		$mode = (string) $this->settings->get( 'litespeed.mode', self::MODE_COMPATIBLE );
		return in_array( $mode, array( self::MODE_INDEPENDENT, self::MODE_COMPATIBLE, self::MODE_COOPERATIVE ), true ) ? $mode : self::MODE_COMPATIBLE;
	}

	public function page_cache_owner(): string {
		$detection = $this->detector->detect();
		if ( $this->is_enabled() && $detection['cache_capable'] ) {
			return 'litespeed';
		}
		return 'npe';
	}

	public function should_run_npe_page_cache(): bool {
		return 'npe' === $this->page_cache_owner();
	}

	public function prevent_duplicate_optimization( $enabled ): bool {
		if ( $this->is_enabled() ) {
			return false;
		}
		return (bool) $enabled;
	}
}
