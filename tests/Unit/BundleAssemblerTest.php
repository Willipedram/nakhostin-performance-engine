<?php
/**
 * Component bundle plan tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Components\BundleAssembler;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;
use PHPUnit\Framework\TestCase;

final class BundleAssemblerTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_resolves_dependencies_and_reuses_equivalent_bundle_plan(): void {
		$assembler = new BundleAssembler( new BundleIndex() );
		$card      = new ComponentDefinition(
			array(
				'id'                      => 'CARD',
				'name'                    => 'Card',
				'css_dependencies'        => array( 'shared', 'card' ),
				'javascript_dependencies' => array( 'interaction' ),
			)
		);
		$price     = new ComponentDefinition(
			array(
				'id'                      => 'PRICE',
				'name'                    => 'Price',
				'css_dependencies'        => array( 'price', 'shared' ),
				'javascript_dependencies' => array( 'interaction' ),
			)
		);

		$first  = $assembler->assemble( array( $card, $price ), 'shop', 'archive', '0.4.0' );
		$second = $assembler->assemble( array( $price, $card ), 'shop', 'archive', '0.4.0' );

		$this->assertSame( $first['bundle_id'], $second['bundle_id'] );
		$this->assertSame( array( 'card', 'price', 'shared' ), $first['css_dependencies'] );
		$this->assertSame( array( 'interaction' ), $first['js_dependencies'] );
		$this->assertSame( array( 'core', 'components', 'page_type', 'template' ), $first['layers'] );
	}
}
