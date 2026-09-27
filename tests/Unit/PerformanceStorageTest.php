<?php
/** Performance sample and bounded storage tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Performance\PerformanceSample;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;
use PHPUnit\Framework\TestCase;

final class PerformanceStorageTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_options'] = array(); }

	public function test_sample_contains_only_allowlisted_privacy_safe_fields(): void {
		$sample = new PerformanceSample( array( 'page_type' => 'Product #42', 'template' => '../../Secret Template.php', 'components' => array( 'Gallery', 'token=secret' ), 'cache_state' => 'hit', 'url' => 'https://example.test/?token=secret', 'password' => 'secret' ) );
		$data = $sample->to_array();
		$this->assertArrayNotHasKey( 'url', $data );
		$this->assertArrayNotHasKey( 'password', $data );
		$this->assertSame( 'product42', $data['page_type'] );
		$this->assertSame( 'default', $data['template'] );
		$this->assertSame( array( 'gallery' ), $data['components'] );
	}

	public function test_storage_enforces_maximum_and_removes_expired_samples(): void {
		$storage = new PerformanceStorage();
		$GLOBALS['npe_test_options'][ PerformanceStorage::OPTION ] = array( array( 'timestamp' => time() - 200000 ) );
		for ( $index = 0; $index < 12; ++$index ) { $storage->save( new PerformanceSample( array( 'timestamp' => time(), 'page_type' => 'page-' . $index ) ), 1, 10 ); }
		$this->assertCount( 10, $storage->all() );
		$this->assertSame( 'page-2', $storage->all()[0]['page_type'] );
	}
}
