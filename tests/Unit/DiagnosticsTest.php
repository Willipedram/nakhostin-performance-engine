<?php
/**
 * Environment diagnostics tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use PHPUnit\Framework\TestCase;

final class DiagnosticsTest extends TestCase {
	public function test_diagnostics_are_read_only_and_sanitize_server_value(): void {
		$GLOBALS['npe_test_actions']          = array();
		$GLOBALS['npe_test_options']          = array();
		$_SERVER['SERVER_SOFTWARE']          = '<script>bad</script> nginx';
		$GLOBALS['npe_test_external_cache'] = true;
		$result                             = ( new Diagnostics() )->collect();

		$this->assertSame( 'bad nginx', $result['server_software'] );
		$this->assertTrue( $result['https'] );
		$this->assertTrue( $result['object_cache'] );
		$this->assertArrayHasKey( 'redis', $result );
		$this->assertFalse( $result['litespeed_cache'] );
	}

	public function test_support_report_populates_every_exported_section(): void {
		$GLOBALS['npe_test_options'] = array();
		$report = ( new Diagnostics() )->report();

		foreach ( array( 'server', 'php', 'cache', 'object_cache', 'performance', 'optimization', 'integrations', 'security_headers' ) as $section ) {
			$this->assertNotEmpty( $report[ $section ], $section . ' must contain facts or an explicit collection state.' );
		}

		$this->assertIsBool( $report['server']['object_cache'] );
		$this->assertArrayHasKey( 'memory_limit', $report['php'] );
		$this->assertArrayHasKey( 'backend', $report['object_cache'] );
		$this->assertArrayHasKey( 'collection', $report['security_headers'] );
	}
}
