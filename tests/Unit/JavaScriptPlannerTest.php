<?php
/**
 * Dependency-safe JavaScript planning tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Components\ComponentDefinition;
use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptPlanner;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use Nakhostin\PerformanceEngine\JavaScript\ScriptSafetyPolicy;
use Nakhostin\PerformanceEngine\JavaScript\ScriptStrategyApplier;
use PHPUnit\Framework\TestCase;

final class JavaScriptPlannerTest extends TestCase {
	/** @var JavaScriptPlanner */
	private $planner;

	protected function setUp(): void {
		$this->planner = new JavaScriptPlanner( new ScriptSafetyPolicy(), new ConservativeMinifier() );
	}

	public function test_component_bundle_preserves_dependency_order(): void {
		$assets = array(
			$this->asset( 'gallery-core' ),
			$this->asset( 'gallery', array( 'gallery-core' ) ),
			$this->asset( 'page', array(), true ),
		);
		$component = new ComponentDefinition(
			array(
				'id'                      => 'GALLERY',
				'name'                    => 'Gallery',
				'selectors'               => array( '.gallery' ),
				'javascript_dependencies' => array( 'gallery' ),
			)
		);
		$contents = array(
			'gallery-core' => 'window.core = true;',
			'gallery'      => 'window.gallery = window.core;',
			'page'         => 'window.page = true;',
		);

		$manifest = $this->planner->plan( $assets, array( $component ), array(), array(), $contents )->to_array();

		$this->assertSame( array( 'gallery-core', 'gallery' ), $manifest['bundles'][0]['handles'] );
		$this->assertSame( 'component', $manifest['bundles'][0]['layer'] );
		$this->assertSame( array( 'page' ), $manifest['bundles'][1]['handles'] );
	}

	public function test_localized_inline_woocommerce_and_elementor_scripts_are_retained(): void {
		$assets = array(
			$this->asset( 'wc-checkout', array(), true ),
			$this->asset( 'elementor-frontend', array(), true ),
			new ScriptAsset(
				array(
					'handle'        => 'custom-config',
					'src'           => '/config.js',
					'inline_before' => array( 'window.Config={nonce:"secret"};' ),
					'localized'     => true,
					'enqueued'      => true,
				)
			),
		);
		$contents = array(
			'wc-checkout'       => 'checkout();',
			'elementor-frontend' => 'elementor();',
			'custom-config'      => 'config();',
		);

		$manifest = $this->planner->plan( $assets, array(), array(), array(), $contents )->to_array();
		$encoded  = wp_json_encode( $manifest );

		$this->assertArrayHasKey( 'wc-checkout', $manifest['retained'] );
		$this->assertArrayHasKey( 'elementor-frontend', $manifest['retained'] );
		$this->assertArrayHasKey( 'custom-config', $manifest['retained'] );
		$this->assertStringNotContainsString( 'secret', $encoded );
	}

	public function test_bundles_preserve_retained_jquery_as_external_dependency(): void {
		$assets = array(
			$this->asset( 'jquery', array(), true ),
			$this->asset( 'custom-ui', array( 'jquery' ), true ),
		);
		$contents = array( 'jquery' => 'jQuery();', 'custom-ui' => 'jQuery(".ui");' );
		$manifest = $this->planner->plan( $assets, array(), array(), array(), $contents )->to_array();

		$this->assertArrayHasKey( 'jquery', $manifest['retained'] );
		$this->assertSame( array( 'custom-ui' ), $manifest['bundles'][0]['handles'] );
		$this->assertSame( array( 'jquery' ), $manifest['bundles'][0]['external_dependencies'] );
	}

	public function test_reports_registered_scripts_not_required_by_the_page(): void {
		$assets = array(
			$this->asset( 'library' ),
			$this->asset( 'page', array( 'library' ), true ),
			$this->asset( 'unrelated' ),
		);
		$contents = array( 'library' => 'lib();', 'page' => 'page();', 'unrelated' => 'unused();' );

		$manifest = $this->planner->plan( $assets, array(), array(), array(), $contents )->to_array();

		$this->assertSame( array( 'library', 'page' ), $manifest['required'] );
		$this->assertSame( array( 'unrelated' ), $manifest['unused_candidates'] );
	}

	public function test_delay_is_opt_in_and_rejected_for_critical_or_required_scripts(): void {
		$assets = array(
			$this->asset( 'jquery', array(), true ),
			$this->asset( 'library', array(), true ),
			$this->asset( 'consumer', array( 'library' ), true ),
		);
		$contents = array( 'jquery' => 'jQuery();', 'library' => 'lib();', 'consumer' => 'useLib();' );
		$options  = array( 'delay_handles' => array( 'jquery', 'library' ) );

		$manifest = $this->planner->plan( $assets, array(), array(), $options, $contents )->to_array();

		$this->assertSame( array(), $manifest['delayed'] );
		$this->assertNotEmpty( $manifest['warnings'] );
	}

	public function test_approved_defer_plan_uses_native_wordpress_strategy(): void {
		$asset    = $this->asset( 'non-critical', array(), true );
		$contents = array( 'non-critical' => 'interactive();' );
		$manifest = $this->planner->plan(
			array( $asset ),
			array(),
			array(),
			array( 'defer_handles' => array( 'non-critical' ) ),
			$contents
		);
		$result = ( new ScriptStrategyApplier() )->apply_defer( $manifest );

		$this->assertSame( array( 'non-critical' ), $result['applied'] );
		$this->assertSame( 'defer', $GLOBALS['npe_test_script_data']['non-critical']['strategy'] );
	}

	public function test_dependency_cycle_retains_original_scripts(): void {
		$first = $this->asset( 'first', array( 'second' ), true );
		$second = $this->asset( 'second', array( 'first' ), true );
		$manifest = $this->planner->plan(
			array( $first, $second ),
			array(),
			array(),
			array(),
			array( 'first' => 'first();', 'second' => 'second();' )
		)->to_array();

		$this->assertSame( array(), $manifest['bundles'] );
		$this->assertArrayHasKey( 'first', $manifest['retained'] );
		$this->assertArrayHasKey( 'second', $manifest['retained'] );
		$this->assertNotEmpty( $manifest['warnings'] );
	}

	public function test_signature_invalidates_for_source_dependency_version_settings_and_component_changes(): void {
		$asset     = $this->asset( 'app', array(), true );
		$component = new ComponentDefinition(
			array( 'id' => 'APP', 'name' => 'App', 'selectors' => array( '.app' ) )
		);
		$base      = array(
			'source_hashes' => array( 'app' => 'aaa' ),
			'versions'      => array( 'theme' => '1.0' ),
			'settings_hash' => 'settings-a',
		);
		$first = $this->planner->plan( array( $asset ), array( $component ), $base )->signature();

		$source = $base;
		$source['source_hashes']['app'] = 'bbb';
		$dependency_asset = $this->asset( 'app', array( 'dependency' ), true );
		$version = $base;
		$version['versions']['theme'] = '2.0';
		$settings = $base;
		$settings['settings_hash'] = 'settings-b';
		$changed_component = new ComponentDefinition(
			array( 'id' => 'APP', 'name' => 'App', 'selectors' => array( '.app-v2' ) )
		);

		$this->assertNotSame( $first, $this->planner->plan( array( $asset ), array( $component ), $source )->signature() );
		$this->assertNotSame(
			$first,
			$this->planner->plan( array( $dependency_asset ), array( $component ), $base )->signature()
		);
		$this->assertNotSame( $first, $this->planner->plan( array( $asset ), array( $component ), $version )->signature() );
		$this->assertNotSame( $first, $this->planner->plan( array( $asset ), array( $component ), $settings )->signature() );
		$this->assertNotSame(
			$first,
			$this->planner->plan( array( $asset ), array( $changed_component ), $base )->signature()
		);
	}

	private function asset( string $handle, array $dependencies = array(), bool $enqueued = false ): ScriptAsset {
		return new ScriptAsset(
			array(
				'handle'       => $handle,
				'src'          => '/' . $handle . '.js',
				'dependencies' => $dependencies,
				'enqueued'     => $enqueued,
			)
		);
	}
}
