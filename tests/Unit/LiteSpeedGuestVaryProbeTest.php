<?php
/** LiteSpeed Guest Vary diagnostic tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedGuestVaryProbe;
use PHPUnit\Framework\TestCase;

final class LiteSpeedGuestVaryProbeTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array(); }

	public function test_slow_json_response_is_reported_without_exposing_body(): void {
		$times = array( 10.0, 17.998 );
		$clock = static function () use ( &$times ): float { return (float) array_shift( $times ); };
		$transport = static function (): array { return array( 'response' => array( 'code' => 200 ), 'headers' => array( 'content-type' => 'application/json' ), 'body' => '{"vary":"guest"}' ); };
		$report = ( new LiteSpeedGuestVaryProbe( 'https://example.test/wp-content/plugins/litespeed-cache/guest.vary.php', $transport, $clock ) )->probe();
		$this->assertSame( 'slow', $report['state'] );
		$this->assertSame( 7998.0, $report['duration_ms'] );
		$this->assertArrayNotHasKey( 'body', $report );
	}

	public function test_403_identifies_external_configuration_layer(): void {
		$transport = static function (): array { return array( 'response' => array( 'code' => 403 ), 'headers' => array( 'content-type' => 'text/html' ), 'body' => '<html>Forbidden</html>' ); };
		$report = ( new LiteSpeedGuestVaryProbe( 'https://example.test/wp-content/plugins/litespeed-cache/guest.vary.php', $transport ) )->probe();
		$this->assertSame( 'failed', $report['state'] );
		$this->assertSame( 403, $report['http_status'] );
		$this->assertSame( 'diagnostic_only', $report['npe_action'] );
	}

	public function test_cross_origin_probe_is_refused(): void {
		$report = ( new LiteSpeedGuestVaryProbe( 'https://attacker.invalid/guest.vary.php' ) )->probe();
		$this->assertSame( 'unavailable', $report['state'] );
	}
}
