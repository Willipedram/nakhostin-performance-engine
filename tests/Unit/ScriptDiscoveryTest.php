<?php
/**
 * WordPress script source discovery tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ScriptDiscoveryTest extends TestCase {
	public function test_discovers_registration_inline_localized_strategy_and_modules(): void {
		$registered        = new stdClass();
		$registered->src   = '/app.js?ver=1';
		$registered->deps  = array( 'jquery' );
		$registered->ver   = '1.0';
		$registered->extra = array(
			'group'    => 1,
			'strategy' => 'defer',
			'before'   => array( 'window.before = true;' ),
			'after'    => array( 'window.after = true;' ),
			'data'     => 'window.App = {nonce:"private"};',
		);
		$scripts             = new stdClass();
		$scripts->registered = array( 'app' => $registered );
		$scripts->queue      = array( 'app' );

		$assets = ( new ScriptDiscovery() )->discover(
			$scripts,
			array(
				'module-app' => array(
					'src'          => '/module.js',
					'dependencies' => array( 'module-dependency' ),
					'enqueued'     => true,
				),
			)
		);

		$this->assertCount( 2, $assets );
		$this->assertTrue( $assets[0]->has_runtime_data() );
		$this->assertTrue( $assets[0]->to_array()['localized'] );
		$this->assertSame( 2, $assets[0]->to_array()['inline_before_count'] );
		$this->assertSame( 'defer', $assets[0]->to_array()['strategy'] );
		$this->assertTrue( $assets[1]->to_array()['module'] );
		$this->assertStringNotContainsString( 'private', wp_json_encode( $assets[0]->to_array() ) );
	}
}
