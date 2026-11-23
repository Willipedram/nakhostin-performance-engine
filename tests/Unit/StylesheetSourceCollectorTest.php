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

	public function test_oversized_source_is_excluded_instead_of_bundling_truncated_css(): void {
		$previous = $GLOBALS['npe_test_remote_response'] ?? null;
		$GLOBALS['npe_test_remote_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => str_repeat( 'x', StylesheetSourceCollector::MAX_FILE_BYTES + 1 ),
		);
		try {
			$result = ( new StylesheetSourceCollector() )->collect(
				array( 'source_url' => 'https://example.com/', 'stylesheets' => array( '/assets/site.css' ) )
			);
			$this->assertSame( 0, $result['fetched_files'] );
			$this->assertSame( 1, $result['skipped_files'] );
			$this->assertSame( '', $result['css'] );
		} finally {
			$GLOBALS['npe_test_remote_response'] = $previous;
		}
	}
}
