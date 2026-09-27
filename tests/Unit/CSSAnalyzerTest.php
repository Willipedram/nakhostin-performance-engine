<?php
/**
 * CSS usage intelligence tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use PHPUnit\Framework\TestCase;

final class CSSAnalyzerTest extends TestCase {
	public function test_classifies_dom_matches_missing_and_dynamic_selectors(): void {
		$manifest = ( new CSSAnalyzer() )->analyze_stylesheet(
			'.card {color:red}.missing > .price {color:blue}.menu-open .panel:hover {display:block}#hero[data-mode] {display:grid}',
			array(
				'classes'    => array( 'card', 'price' ),
				'ids'        => array( 'hero' ),
				'attributes' => array( 'data-mode' ),
			)
		)->to_array();

		$this->assertContains( '.card', $manifest['used'] );
		$this->assertContains( '#hero[data-mode]', $manifest['used'] );
		$this->assertContains( '.missing > .price', $manifest['unused_candidates'] );
		$this->assertContains( '.menu-open .panel:hover', $manifest['preserved'] );
		$this->assertFalse( $manifest['optimization_active'] );
		$this->assertStringNotContainsString( 'color:red', (string) wp_json_encode( $manifest ) );
	}

	public function test_selector_lists_do_not_split_function_arguments(): void {
		$selectors = ( new CSSAnalyzer() )->extract_selectors( '.a, :is(.b,.c) { color:red }' );

		$this->assertSame( array( '.a', ':is(.b,.c)' ), $selectors );
	}
}
