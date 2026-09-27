<?php
/**
 * Configuration validation tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function test_sanitizer_keeps_only_known_boolean_values(): void {
		$settings = new Settings();
		$result   = $settings->sanitize(
			array(
				'general' => array( 'remove_data_on_uninstall' => 'on' ),
				'dom'     => array( 'enabled' => 'unexpected' ),
				'css'     => array( 'enabled' => '1', 'secret' => 'discard-me' ),
				'unknown' => array( 'enabled' => true ),
			)
		);

		$this->assertTrue( $result['general']['remove_data_on_uninstall'] );
		$this->assertFalse( $result['dom']['enabled'] );
		$this->assertTrue( $result['css']['enabled'] );
		$this->assertArrayNotHasKey( 'secret', $result['css'] );
		$this->assertArrayNotHasKey( 'unknown', $result );
		$this->assertFalse( $result['debugging']['enabled'] );
		$this->assertTrue( $result['litespeed']['enabled'] );
		$this->assertSame( 'compatible', $result['litespeed']['mode'] );
	}

	public function test_cache_configuration_is_bounded_and_sanitized(): void {
		$result = ( new Settings() )->sanitize(
			array(
				'cache' => array(
					'enabled'                  => 'on',
					'ttl'                      => 999999,
					'stale_ttl'                => -1,
					'allowed_query_parameters' => 'Color, bad value',
					'excluded_paths'           => "checkout\n/private",
					'important_product_ids'    => '4, 4, -8, invalid',
					'important_category_ids'   => array( 7, 0, '9' ),
				),
			)
		);

		$this->assertTrue( $result['cache']['enabled'] );
		$this->assertFalse( $result['cache']['early_cache'] );
		$this->assertSame( 86400, $result['cache']['ttl'] );
		$this->assertSame( 0, $result['cache']['stale_ttl'] );
		$this->assertSame( array( 'color', 'bad', 'value' ), $result['cache']['allowed_query_parameters'] );
		$this->assertSame( array( '/checkout', '/private' ), $result['cache']['excluded_paths'] );
		$this->assertSame( array( 4, 8 ), $result['cache']['important_product_ids'] );
		$this->assertSame( array( 7, 9 ), $result['cache']['important_category_ids'] );
	}

	public function test_early_cache_is_explicitly_opt_in(): void {
		$settings = new Settings();
		$this->assertFalse( $settings->defaults()['cache']['early_cache'] );
		$this->assertTrue( $settings->sanitize( array( 'cache' => array( 'early_cache' => 'on' ) ) )['cache']['early_cache'] );
	}

	public function test_frontend_optimization_defaults_to_safe_and_aggressive_features_off(): void {
		$defaults = ( new Settings() )->defaults();
		$this->assertFalse( $defaults['optimization']['enabled'] );
		$this->assertTrue( $defaults['optimization']['safe_mode'] );
		$this->assertFalse( $defaults['css']['critical_css_enabled'] );
		$this->assertFalse( $defaults['css']['unused_css_enabled'] );
		$this->assertFalse( $defaults['javascript']['defer_enabled'] );
		$this->assertFalse( $defaults['javascript']['delay_enabled'] );
		$this->assertFalse( $defaults['assets']['unload_enabled'] );
		$this->assertFalse( $defaults['fonts']['preload_enabled'] );
	}

	public function test_litespeed_mode_is_allowlisted(): void {
		$settings = new Settings();
		$this->assertSame( 'cooperative', $settings->sanitize( array( 'litespeed' => array( 'mode' => 'cooperative' ) ) )['litespeed']['mode'] );
		$this->assertSame( 'compatible', $settings->sanitize( array( 'litespeed' => array( 'mode' => 'dangerous' ) ) )['litespeed']['mode'] );
	}

	public function test_performance_configuration_is_bounded(): void {
		$result = ( new Settings() )->sanitize( array( 'performance' => array( 'enabled' => 'on', 'sample_rate' => 500, 'retention_days' => 0, 'max_samples' => 99999 ) ) );
		$this->assertTrue( $result['performance']['enabled'] );
		$this->assertSame( 100, $result['performance']['sample_rate'] );
		$this->assertSame( 1, $result['performance']['retention_days'] );
		$this->assertSame( 2000, $result['performance']['max_samples'] );
	}

	public function test_dom_learning_configuration_is_bounded(): void {
		$result = ( new Settings() )->sanitize( array( 'dom' => array( 'enabled' => 'on', 'sample_rate' => 500, 'cooldown_hours' => 0, 'batch_size' => 99, 'scan_interval' => 1 ) ) );
		$this->assertTrue( $result['dom']['enabled'] );
		$this->assertSame( 100, $result['dom']['sample_rate'] );
		$this->assertSame( 1, $result['dom']['cooldown_hours'] );
		$this->assertSame( 5, $result['dom']['batch_size'] );
		$this->assertSame( 5, $result['dom']['scan_interval'] );
	}

	public function test_font_optimization_is_opt_in(): void {
		$settings = new Settings();
		$this->assertFalse( $settings->defaults()['fonts']['enabled'] );
		$this->assertTrue( $settings->sanitize( array( 'fonts' => array( 'enabled' => 'on' ) ) )['fonts']['enabled'] );
	}
}
