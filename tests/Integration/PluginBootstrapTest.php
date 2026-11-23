<?php
/**
 * Plugin bootstrap tests using minimal WordPress boundary stubs.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Integration;

use Nakhostin\PerformanceEngine\Core\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class PluginBootstrapTest extends TestCase {
	public function test_bootstrap_defines_plugin_constants(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', NPE_VERSION );
		$this->assertSame( 'nakhostin-performance-engine.php', NPE_BASENAME );
	}

	public function test_bootstrap_registers_plugin_and_lifecycle_hooks(): void {
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
		$this->assertArrayHasKey( 'plugins_loaded', $GLOBALS['npe_test_actions'] );
		$this->assertCount( 1, $GLOBALS['npe_test_activation_hooks'] );
		$this->assertCount( 1, $GLOBALS['npe_test_deactivation_hooks'] );
	}

	public function test_boot_registers_lightweight_page_analysis_listener(): void {
		do_action( 'plugins_loaded' );

		$this->assertArrayHasKey( 'template_redirect', $GLOBALS['npe_test_actions'] );
	}
}
