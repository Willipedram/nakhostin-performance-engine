<?php
/**
 * DOM manifest storage tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\DOM\DOMManifest;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use PHPUnit\Framework\TestCase;

final class DOMStorageTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array();
	}

	public function test_stores_manifest_without_html(): void {
		$storage  = new DOMStorage();
		$manifest = new DOMManifest( array( 'dom_signature' => 'signature', 'classes' => array( 'page' ) ) );

		$this->assertTrue( $storage->save( $manifest ) );
		$this->assertSame( 'signature', $storage->latest()->signature() );
		$this->assertArrayNotHasKey( 'html', get_option( DOMStorage::OPTION ) );
		$this->assertTrue( $storage->delete() );
		$this->assertNull( $storage->latest() );
	}
}
