<?php
/** LiteSpeed detection tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedDetector;
use PHPUnit\Framework\TestCase;

final class LiteSpeedDetectorTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
		$GLOBALS['npe_test_actions'] = array();
		$_SERVER['SERVER_SOFTWARE']  = 'nginx';
	}

	public function test_absent_plugin_is_non_fatal(): void {
		$result = ( new LiteSpeedDetector( sys_get_temp_dir() . '/missing-lscache.php' ) )->detect();
		$this->assertFalse( $result['installed'] );
		$this->assertFalse( $result['active'] );
		$this->assertFalse( $result['cache_capable'] );
	}

	public function test_installed_but_inactive_plugin_is_detected_without_enabling_integration(): void {
		$file = tempnam( sys_get_temp_dir(), 'npe-lscache-' );
		$result = ( new LiteSpeedDetector( $file ) )->detect();
		unlink( $file );
		$this->assertTrue( $result['installed'] );
		$this->assertFalse( $result['active'] );
		$this->assertFalse( $result['cache_capable'] );
	}

	public function test_active_plugin_on_litespeed_server_is_cache_capable(): void {
		$GLOBALS['npe_test_options']['active_plugins'] = array( LiteSpeedDetector::PLUGIN_BASENAME );
		$_SERVER['SERVER_SOFTWARE']                   = 'LiteSpeed';
		$result = ( new LiteSpeedDetector() )->detect();
		$this->assertTrue( $result['installed'] );
		$this->assertTrue( $result['active'] );
		$this->assertTrue( $result['server'] );
		$this->assertTrue( $result['cache_capable'] );
	}
}
