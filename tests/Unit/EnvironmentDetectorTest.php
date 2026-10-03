<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Environment\EnvironmentDetector;
use Nakhostin\PerformanceEngine\Environment\ServerDetector;
use PHPUnit\Framework\TestCase;

final class EnvironmentDetectorTest extends TestCase {
	public function test_detects_supported_and_unknown_servers(): void { $detector = new ServerDetector(); self::assertSame( 'apache', $detector->detect( array( 'SERVER_SOFTWARE' => 'Apache/2.4' ) ) ); self::assertSame( 'nginx', $detector->detect( array( 'SERVER_SOFTWARE' => 'nginx/1.24' ) ) ); self::assertSame( 'litespeed', $detector->detect( array( 'SERVER_SOFTWARE' => 'LiteSpeed' ) ) ); self::assertSame( 'openlitespeed', $detector->detect( array( 'SERVER_SOFTWARE' => 'OpenLiteSpeed' ) ) ); self::assertSame( 'unknown', $detector->detect( array() ) ); }
	public function test_builds_value_object_report(): void { $root = sys_get_temp_dir() . '/npe-env-' . uniqid(); mkdir( $root ); $report = ( new EnvironmentDetector( $root ) )->detect( array( 'SERVER_SOFTWARE' => 'nginx', 'HTTP_CF_RAY' => 'abc' ) ); self::assertSame( 'nginx', $report->server() ); self::assertTrue( $report->get( 'early_cache_installable' ) ); self::assertSame( 'cloudflare', $report->get( 'reverse_proxy' )['provider'] ); rmdir( $root ); }
	public function test_reports_known_external_cache_plugin_without_changing_it(): void { $root = sys_get_temp_dir() . '/npe-env-' . uniqid(); mkdir( $root ); $GLOBALS['npe_test_options']['active_plugins'] = array( 'wp-rocket/wp-rocket.php' ); $report = ( new EnvironmentDetector( $root ) )->detect( array() ); self::assertTrue( $report->get( 'cache_plugin_active' ) ); self::assertContains( 'wp-rocket/wp-rocket.php', $report->get( 'cache_plugins' ) ); unset( $GLOBALS['npe_test_options']['active_plugins'] ); rmdir( $root ); }
}
