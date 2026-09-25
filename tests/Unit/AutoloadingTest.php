<?php
/**
 * Autoloading tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Contracts\CacheInterface;
use Nakhostin\PerformanceEngine\Core\ServiceRegistry;
use PHPUnit\Framework\TestCase;

final class AutoloadingTest extends TestCase {
	public function test_core_class_is_autoloaded(): void {
		$this->assertTrue( class_exists( ServiceRegistry::class ) );
	}

	public function test_contract_is_autoloaded(): void {
		$this->assertTrue( interface_exists( CacheInterface::class ) );
	}
}
