<?php
/** LiteSpeed mode and cache ownership tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyCollector;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedAdapter;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedCacheBridge;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedDetector;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedPurgeBridge;
use PHPUnit\Framework\TestCase;

final class LiteSpeedAdapterTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']       = array();
		$GLOBALS['npe_test_actions']       = array();
		$GLOBALS['npe_test_filters']       = array();
		$_SERVER['SERVER_SOFTWARE']        = 'LiteSpeed';
	}

	public function test_default_compatible_mode_delegates_capable_page_cache(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$adapter = $this->adapter( new Settings() );
		$this->assertSame( LiteSpeedAdapter::MODE_COMPATIBLE, $adapter->mode() );
		$this->assertTrue( $adapter->is_enabled() );
		$this->assertSame( 'litespeed', $adapter->page_cache_owner() );
		$this->assertFalse( $adapter->should_run_npe_page_cache() );
	}

	public function test_independent_mode_keeps_npe_ownership_and_registers_no_bridge(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array( 'litespeed' => array( 'enabled' => true, 'mode' => 'independent' ) );
		$adapter = $this->adapter( new Settings() );
		$adapter->register();
		$this->assertSame( 'npe', $adapter->page_cache_owner() );
		$this->assertArrayNotHasKey( 'template_redirect', $GLOBALS['npe_test_actions'] );
		$this->assertArrayNotHasKey( 'npe/cache/purged', $GLOBALS['npe_test_actions'] );
	}

	public function test_compatible_mode_prevents_duplicate_npe_optimization(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$adapter = $this->adapter( new Settings() );
		$adapter->register();
		$this->assertFalse( apply_filters( 'npe/javascript/optimization_enabled', true ) );
		$this->assertFalse( apply_filters( 'npe/css/optimization_enabled', true ) );
		$this->assertArrayNotHasKey( 'npe/cache/purged', $GLOBALS['npe_test_actions'] );
	}

	public function test_inactive_plugin_falls_back_to_npe_without_errors(): void {
		$adapter = $this->adapter( new Settings() );
		$adapter->register();
		$this->assertFalse( $adapter->is_available() );
		$this->assertSame( 'npe', $adapter->page_cache_owner() );
		$this->assertTrue( $adapter->should_run_npe_page_cache() );
	}

	public function test_active_plugin_without_detected_page_cache_keeps_npe_page_ownership(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$_SERVER['SERVER_SOFTWARE']                   = 'Apache';
		$adapter = $this->adapter( new Settings() );
		$this->assertTrue( $adapter->is_enabled() );
		$this->assertSame( 'npe', $adapter->page_cache_owner() );
	}

	public function test_cooperative_mode_registers_scoped_purge_bridge(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array( 'litespeed' => array( 'enabled' => true, 'mode' => 'cooperative' ) );
		$adapter = $this->adapter( new Settings() );
		$adapter->register();
		$this->assertArrayHasKey( 'npe/cache/purged', $GLOBALS['npe_test_actions'] );
		$this->assertArrayHasKey( 'template_redirect', $GLOBALS['npe_test_actions'] );
	}

	private function adapter( Settings $settings ): LiteSpeedAdapter {
		return new LiteSpeedAdapter(
			new LiteSpeedDetector( sys_get_temp_dir() . '/missing-lscache.php' ),
			new LiteSpeedCacheBridge( new CacheRequestFactory(), new CachePolicy(), new CacheDependencyCollector(), $settings ),
			new LiteSpeedPurgeBridge(),
			$settings
		);
	}
}
