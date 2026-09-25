<?php
/**
 * Activation and deactivation tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Core\Activator;
use Nakhostin\PerformanceEngine\Core\Deactivator;
use Nakhostin\PerformanceEngine\Core\MigrationManager;
use Nakhostin\PerformanceEngine\Core\VersionManager;
use PHPUnit\Framework\TestCase;

final class LifecycleTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options']       = array();
		$GLOBALS['npe_test_cleared_hooks'] = array();
		if ( ! defined( 'NPE_VERSION' ) ) {
			define( 'NPE_VERSION', '1.4.0' );
		}
	}

	public function test_activation_records_code_and_schema_versions(): void {
		Activator::activate();

		$this->assertSame( NPE_VERSION, get_option( VersionManager::OPTION ) );
		$this->assertSame( MigrationManager::SCHEMA_VERSION, get_option( MigrationManager::OPTION ) );
	}

	public function test_deactivation_only_clears_owned_schedule(): void {
		Deactivator::deactivate();

		$this->assertSame( array( 'npe/run_maintenance', 'npe/cache/run_warmup', 'npe/cache/process_purge_queue', 'npe/cache/process_warmup_queue' ), $GLOBALS['npe_test_cleared_hooks'] );
	}
}
