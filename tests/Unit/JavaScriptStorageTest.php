<?php
/**
 * JavaScript manifest invalidation tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use PHPUnit\Framework\TestCase;

final class JavaScriptStorageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_manifest_can_be_saved_and_explicitly_invalidated(): void {
		$storage  = new JavaScriptStorage();
		$manifest = new JavaScriptManifest( array( 'signature' => 'hash', 'assets' => array() ) );

		$this->assertTrue( $storage->save( $manifest ) );
		$this->assertSame( 'hash', $storage->latest()->signature() );
		$this->assertTrue( $storage->invalidate() );
		$this->assertNull( $storage->latest() );
	}
}
