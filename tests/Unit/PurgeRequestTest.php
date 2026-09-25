<?php
/** Purge request validation tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Cache\PurgeRequest;
use PHPUnit\Framework\TestCase;

final class PurgeRequestTest extends TestCase {
	public function test_all_supported_purge_levels_can_be_created(): void {
		$requests = array(
			PurgeRequest::create( 'url', 'https://example.test/page/', 'manual' ),
			PurgeRequest::create( 'page', 12, 'saved' ),
			PurgeRequest::create( 'component', 'PRODUCT_CARD', 'changed' ),
			PurgeRequest::create( 'product', 44, 'stock' ),
			PurgeRequest::create( 'taxonomy', 'product_cat:7', 'edited' ),
			PurgeRequest::create( 'page_type', 'shop', 'changed' ),
			PurgeRequest::create( 'asset', 'gallery-js', 'changed' ),
			PurgeRequest::create( 'full', 'ignored', 'manual' ),
		);
		$this->assertSame( PurgeRequest::LEVELS, array_map( static function ( PurgeRequest $request ): string { return $request->level(); }, $requests ) );
	}

	public function test_invalid_taxonomy_identifier_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		PurgeRequest::create( 'taxonomy', 'product_cat', 'invalid' );
	}
}
