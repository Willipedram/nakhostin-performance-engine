<?php
/**
 * Component definition, registry, signature, and invalidation tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Components\BundleAssembler;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use PHPUnit\Framework\TestCase;

final class ComponentRegistryTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_custom_component_is_sanitized_registered_and_detected(): void {
		$registry  = $this->registry();
		$component = new ComponentDefinition(
			array(
				'id'                      => 'custom-product-card',
				'name'                    => 'Custom Product Card',
				'selectors'               => array( '.custom-product-card', '<script>' ),
				'css_dependencies'        => array( 'custom-card', 'custom-card' ),
				'javascript_dependencies' => array( 'custom-cart' ),
				'dynamic_states'          => array( 'loading', 'selected' ),
				'integration_owner'       => 'custom',
			)
		);

		$registry->register_custom( $component );
		$stored   = $registry->custom()['CUSTOM_PRODUCT_CARD'];
		$detected = $registry->ingest_manifest(
			array(
				'page_type' => 'product-archive',
				'classes'   => array( 'custom-product-card' ),
				'ids'       => array(),
				'elements'  => array( 'div' ),
				'components' => array(),
			)
		);

		$this->assertSame( array( '.custom-product-card' ), $stored->to_array()['selectors'] );
		$this->assertSame( array( 'custom-card' ), $stored->to_array()['css_dependencies'] );
		$detected_ids = array_map(
			static function ( $item ) {
				return $item->id();
			},
			$detected
		);
		$this->assertContains( 'CUSTOM_PRODUCT_CARD', $detected_ids );
		$this->assertSame( 1, $registry->usage()['CUSTOM_PRODUCT_CARD']['count'] );
	}

	public function test_signature_is_stable_for_equivalent_dependency_order(): void {
		$first = new ComponentDefinition(
			array(
				'id'               => 'EXAMPLE',
				'name'             => 'First label',
				'selectors'        => array( '.b', '.a' ),
				'css_dependencies' => array( 'two', 'one' ),
			)
		);
		$second = new ComponentDefinition(
			array(
				'id'               => 'EXAMPLE',
				'name'             => 'Translated label',
				'selectors'        => array( '.a', '.b' ),
				'css_dependencies' => array( 'one', 'two' ),
			)
		);

		$this->assertSame( $first->signature(), $second->signature() );
	}

	public function test_changed_component_invalidates_only_dependent_bundle(): void {
		$index     = new BundleIndex();
		$assembler = new BundleAssembler( $index );
		$registry  = new ComponentRegistry( new ComponentStorage(), $index );
		$first     = new ComponentDefinition(
			array( 'id' => 'CARD', 'name' => 'Card', 'selectors' => array( '.card' ) )
		);
		$other     = new ComponentDefinition(
			array( 'id' => 'HEADER', 'name' => 'Header', 'selectors' => array( 'header' ) )
		);
		$registry->register_custom( $first );
		$dependent   = $assembler->assemble( array( $first ), 'shop', 'archive', '0.4.0' );
		$independent = $assembler->assemble( array( $other ), 'shop', 'archive', '0.4.0' );
		$changed     = new ComponentDefinition(
			array( 'id' => 'CARD', 'name' => 'Card', 'selectors' => array( '.card-v2' ) )
		);

		$invalidated = $registry->register_custom( $changed );

		$this->assertSame( array( $dependent['bundle_id'] ), $invalidated );
		$this->assertArrayHasKey( $independent['bundle_id'], $index->all() );
	}

	private function registry(): ComponentRegistry {
		return new ComponentRegistry( new ComponentStorage(), new BundleIndex() );
	}
}
