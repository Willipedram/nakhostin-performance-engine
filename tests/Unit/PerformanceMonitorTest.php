<?php
/** Performance monitor tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\Performance\PageContextResolver;
use Nakhostin\PerformanceEngine\Performance\PerformanceMonitor;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;
use PHPUnit\Framework\TestCase;

final class PerformanceMonitorTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array(); $GLOBALS['npe_test_actions'] = array(); $_SERVER['REQUEST_TIME_FLOAT'] = 1.0; $_SERVER['REQUEST_METHOD'] = 'GET'; }

	public function test_timer_uses_injected_clock_accurately(): void {
		$times = array( 1.0, 1.025 );
		$clock = static function () use ( &$times ): float { return (float) array_shift( $times ); };
		$monitor = new PerformanceMonitor( new Settings(), new PerformanceStorage(), new PageContextResolver(), $clock );
		$monitor->start( 'custom' );
		$this->assertSame( 25.0, $monitor->stop( 'custom' ) );
		$this->assertSame( 25.0, $monitor->report()['custom_ms'] );
	}

	public function test_disabled_monitor_registers_no_runtime_hooks(): void {
		$monitor = new PerformanceMonitor( new Settings(), new PerformanceStorage(), new PageContextResolver() );
		$before  = $GLOBALS['npe_test_actions'];
		$monitor->register();
		$this->assertSame( $before, $GLOBALS['npe_test_actions'] );
		$this->assertSame( array(), $monitor->report() );
	}

	public function test_cache_state_allowlist_rejects_unknown_values(): void {
		$monitor = new PerformanceMonitor( new Settings(), new PerformanceStorage(), new PageContextResolver() );
		$monitor->cache_lookup( 'contains-secret-data' );
		$this->assertSame( array(), $monitor->report() );
	}
}
