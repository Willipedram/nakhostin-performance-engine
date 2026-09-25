<?php
/** Fragment cache behavior tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Cache\FilesystemFragmentStore;
use Nakhostin\PerformanceEngine\Cache\FragmentCache;
use Nakhostin\PerformanceEngine\Cache\FragmentKey;
use PHPUnit\Framework\TestCase;

final class FragmentCacheTest extends TestCase {
	/** @var string */
	private $directory;

	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/npe-fragments-' . uniqid( '', true );
	}

	protected function tearDown(): void {
		if ( ! is_dir( $this->directory ) ) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) {
			$file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $this->directory );
	}

	public function test_fragment_keys_are_stable_and_versioned(): void {
		$first  = FragmentKey::create( 'product-card', 2, array( 'language' => 'fa', 'product' => 123 ) );
		$second = FragmentKey::create( 'product-card', 2, array( 'product' => 123, 'language' => 'fa' ) );
		$third  = FragmentKey::create( 'product-card', 3, array( 'product' => 123, 'language' => 'fa' ) );

		$this->assertSame( $first->identifier(), $second->identifier() );
		$this->assertNotSame( $first->identifier(), $third->identifier() );
	}

	public function test_private_fragments_require_and_hash_scope(): void {
		$first  = FragmentKey::create( 'mini-cart', 1, array(), FragmentKey::PRIVATE_VISIBILITY, 'customer-a' );
		$second = FragmentKey::create( 'mini-cart', 1, array(), FragmentKey::PRIVATE_VISIBILITY, 'customer-b' );
		$this->assertNotSame( $first->identifier(), $second->identifier() );

		$this->expectException( InvalidArgumentException::class );
		FragmentKey::create( 'mini-cart', 1, array(), FragmentKey::PRIVATE_VISIBILITY );
	}

	public function test_sensitive_public_dimensions_are_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		FragmentKey::create( 'product-card', 1, array( 'customer_email' => 'person@example.test' ) );
	}

	public function test_dependencies_invalidate_only_relevant_fragments(): void {
		$cache   = new FragmentCache( new FilesystemFragmentStore( $this->directory ) );
		$product = FragmentKey::create( 'product-card', 1, array( 'product' => 123 ) );
		$nav     = FragmentKey::create( 'navigation' );
		$this->assertTrue( $cache->put( $product, '<article>Product</article>', 300, array( 'product-123', 'product-category-7' ) ) );
		$this->assertTrue( $cache->put( $nav, '<nav>Menu</nav>', 300, array( 'menu-2' ) ) );

		$this->assertSame( 1, $cache->invalidate_dependencies( array( 'product-123' ) ) );
		$this->assertSame( 'miss', $cache->get( $product )->status() );
		$this->assertSame( 'hit', $cache->get( $nav )->status() );
	}

	public function test_remember_generates_once_and_preserves_false_values(): void {
		$cache = new FragmentCache( new FilesystemFragmentStore( $this->directory ) );
		$key   = FragmentKey::create( 'expensive-elementor-widget' );
		$calls = 0;
		$one   = $cache->remember( $key, 60, array( 'component-widget' ), static function () use ( &$calls ) { ++$calls; return false; } );
		$two   = $cache->remember( $key, 60, array( 'component-widget' ), static function () use ( &$calls ) { ++$calls; return true; } );

		$this->assertSame( 'generated', $one->status() );
		$this->assertTrue( $two->is_hit() );
		$this->assertFalse( $two->value() );
		$this->assertSame( 1, $calls );
	}

	public function test_lock_is_shared_between_store_instances(): void {
		$first  = new FilesystemFragmentStore( $this->directory );
		$second = new FilesystemFragmentStore( $this->directory );
		$this->assertTrue( $first->acquire_lock( 'shared', 10 ) );
		$this->assertFalse( $second->acquire_lock( 'shared', 10 ) );
		$first->release_lock( 'shared' );
		$this->assertTrue( $second->acquire_lock( 'shared', 10 ) );
		$second->release_lock( 'shared' );
	}

	public function test_concurrent_remember_generates_fragment_once(): void {
		if ( ! function_exists( 'pcntl_fork' ) ) {
			$this->markTestSkipped( 'The pcntl extension is unavailable.' );
		}
		$counter  = $this->directory . '/generator-count';
		$children = array();
		for ( $worker = 0; $worker < 3; ++$worker ) {
			$pid = pcntl_fork();
			if ( 0 === $pid ) {
				$cache = new FragmentCache( new FilesystemFragmentStore( $this->directory ) );
				$cache->remember( FragmentKey::create( 'concurrent-card' ), 60, array(), static function () use ( $counter ) {
					file_put_contents( $counter, "generated\n", FILE_APPEND | LOCK_EX );
					usleep( 50000 );
					return 'content';
				} );
				exit( 0 );
			}
			$this->assertGreaterThan( 0, $pid );
			$children[] = $pid;
		}
		foreach ( $children as $pid ) {
			pcntl_waitpid( $pid, $status );
			$this->assertSame( 0, pcntl_wexitstatus( $status ) );
		}
		$this->assertSame( 1, count( file( $counter, FILE_IGNORE_NEW_LINES ) ) );
	}
}
