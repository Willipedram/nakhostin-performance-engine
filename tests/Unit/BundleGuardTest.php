<?php
/** Regression tests for generated asset fallback. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Optimization\BundleGuard;
use PHPUnit\Framework\TestCase;

final class BundleGuardTest extends TestCase {
	public function test_missing_truncated_or_unexpected_bundle_retains_original_assets(): void {
		$directory = sys_get_temp_dir() . '/npe-bundle-guard-' . uniqid();
		mkdir( $directory );
		$filename = 'page-' . str_repeat( 'a', 20 ) . '.css';
		$base = 'https://example.test/generated/';
		$bundle = array( 'status' => 'generated', 'filename' => $filename, 'content_hash' => str_repeat( 'a', 20 ), 'url' => $base . $filename, 'optimized_size' => 4 );
		$guard = new BundleGuard();
		try {
			self::assertFalse( $guard->available( $bundle, 'css', $directory, $base ) );
			file_put_contents( $directory . '/' . $filename, 'bad' );
			clearstatcache();
			self::assertFalse( $guard->available( $bundle, 'css', $directory, $base ) );
			file_put_contents( $directory . '/' . $filename, 'body' );
			clearstatcache();
			self::assertTrue( $guard->available( $bundle, 'css', $directory, $base ) );
			$bundle['url'] = 'https://other.test/' . $filename;
			self::assertFalse( $guard->available( $bundle, 'css', $directory, $base ) );
		} finally {
			unlink( $directory . '/' . $filename );
			rmdir( $directory );
		}
	}

	public function test_replacement_requires_order_and_preserves_live_dependencies_and_inline_styles(): void {
		$guard = new BundleGuard();
		$manager = (object) array(
			'queue'      => array( 'base', 'second', 'third' ),
			'done'       => array(),
			'registered' => array(
				'base'   => $this->asset(),
				'second' => $this->asset( array( 'base' ) ),
				'third'  => $this->asset( array( 'second' ) ),
			),
		);
		self::assertTrue( $guard->can_replace( $manager, array( 'second', 'third' ), 'css' ) );
		self::assertFalse( $guard->can_replace( $manager, array( 'base', 'second' ), 'css' ) );
		self::assertFalse( $guard->can_replace( $manager, array( 'third', 'second' ), 'css' ) );
		$manager->registered['remaining'] = $this->asset( array( 'third' ) );
		self::assertFalse( $guard->can_replace( $manager, array( 'second', 'third' ), 'css' ) );
		unset( $manager->registered['remaining'] );
		$manager->registered['second']->extra['after'] = array( '.menu{display:flex}' );
		self::assertFalse( $guard->can_replace( $manager, array( 'second', 'third' ), 'css' ) );
	}

	private function asset( array $dependencies = array() ): object {
		return (object) array( 'src' => '/asset.css', 'extra' => array(), 'args' => 'all', 'deps' => $dependencies );
	}
}
