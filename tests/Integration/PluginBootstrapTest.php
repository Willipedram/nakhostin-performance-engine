<?php
/**
 * Plugin bootstrap tests using minimal WordPress boundary stubs.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Integration;

use Nakhostin\PerformanceEngine\Core\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginBootstrapTest extends TestCase {
	public static function setUpBeforeClass(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}

		require_once dirname( __DIR__, 2 ) . '/tests/Fixtures/wordpress-stubs.php';
		require_once dirname( __DIR__, 2 ) . '/nakhostin-performance-engine.php';
	}

	public function test_bootstrap_defines_plugin_constants(): void {
		$this->assertSame( '1.2.1', NPE_VERSION );
		$this->assertSame( 'nakhostin-performance-engine.php', NPE_BASENAME );
	}

	public function test_bootstrap_registers_plugin_and_lifecycle_hooks(): void {
		$this->assertInstanceOf( Plugin::class, Plugin::instance() );
		$this->assertArrayHasKey( 'plugins_loaded', $GLOBALS['npe_test_actions'] );
		$this->assertCount( 1, $GLOBALS['npe_test_activation_hooks'] );
		$this->assertCount( 1, $GLOBALS['npe_test_deactivation_hooks'] );
	}
}
