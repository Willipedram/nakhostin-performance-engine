<?php
/**
 * Authorization policy tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use PHPUnit\Framework\TestCase;

final class CapabilitiesTest extends TestCase {
	public function test_management_capability_is_checked(): void {
		$capabilities                    = new Capabilities();
		$GLOBALS['npe_test_can_manage'] = false;
		$this->assertFalse( $capabilities->can_manage() );

		$GLOBALS['npe_test_can_manage'] = true;
		$this->assertTrue( $capabilities->can_manage() );
	}
}
