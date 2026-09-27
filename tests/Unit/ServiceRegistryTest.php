<?php
/**
 * Service registry tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Core\ServiceRegistry;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ServiceRegistryTest extends TestCase {
	public function test_registry_returns_registered_object(): void {
		$registry = new ServiceRegistry();
		$service  = new stdClass();

		$registry->set( 'example', $service );

		$this->assertTrue( $registry->has( 'example' ) );
		$this->assertSame( $service, $registry->get( 'example' ) );
	}

	public function test_factory_is_resolved_only_once(): void {
		$registry = new ServiceRegistry();
		$calls    = 0;

		$registry->set(
			'lazy',
			static function () use ( &$calls ): stdClass {
				++$calls;
				return new stdClass();
			}
		);

		$this->assertSame( $registry->get( 'lazy' ), $registry->get( 'lazy' ) );
		$this->assertSame( 1, $calls );
	}

	public function test_unknown_service_throws_exception(): void {
		$this->expectException( InvalidArgumentException::class );

		( new ServiceRegistry() )->get( 'missing' );
	}
}
