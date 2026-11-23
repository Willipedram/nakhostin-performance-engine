<?php
/** Tests bounded cache references used by both cache layers. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\GeneratedAssetReferences;
use PHPUnit\Framework\TestCase;

final class GeneratedAssetReferencesTest extends TestCase {
	public function test_checks_file_size_and_rejects_untrusted_paths(): void {
		$root = sys_get_temp_dir() . '/npe-cache-assets-' . uniqid();
		$base = $root . '/cache/nakhostin-performance-engine-assets/css';
		$relative = 'css/page-' . str_repeat( 'a', 20 ) . '.css';
		$file = dirname( $base ) . '/' . $relative;
		mkdir( $base, 0700, true );
		try {
			self::assertFalse( GeneratedAssetReferences::available( array( $relative => 4 ), $root ) );
			file_put_contents( $file, 'body' );
			self::assertTrue( GeneratedAssetReferences::available( array( $relative => 4 ), $root ) );
			self::assertFalse( GeneratedAssetReferences::available( array( $relative => 5 ), $root ) );
			self::assertFalse( GeneratedAssetReferences::available( array( 'css/../private.php' => 4 ), $root ) );
			unlink( $file );
			self::assertFalse( GeneratedAssetReferences::available( array( $relative => 4 ), $root ) );
		} finally {
			if ( is_file( $file ) ) { unlink( $file ); }
			rmdir( $base );
			rmdir( dirname( $base ) );
			rmdir( $root . '/cache' );
			rmdir( $root );
		}
	}
}
