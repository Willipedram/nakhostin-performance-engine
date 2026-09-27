<?php
/** Automated same-origin stylesheet collection tests. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\StylesheetSourceCollector;
use PHPUnit\Framework\TestCase;

final class StylesheetSourceCollectorTest extends TestCase {
	public function test_collects_same_origin_and_rejects_cross_origin_stylesheets(): void {
		$GLOBALS['npe_test_remote_response'] = array( 'response' => array( 'code' => 200 ), 'body' => '.card{color:red}' );
		$result = ( new StylesheetSourceCollector() )->collect(
			array(
				'source_url'  => 'https://example.com/shop/',
				'stylesheets' => array( 'https://example.com/assets/site.css', 'https://cdn.example.net/foreign.css' ),
			)
		);

		$this->assertSame( 1, $result['fetched_files'] );
		$this->assertSame( 1, $result['skipped_files'] );
		$this->assertSame( '.card{color:red}', $result['css'] );
	}
}
