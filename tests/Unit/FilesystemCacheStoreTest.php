<?php
/** Filesystem backend integrity tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Cache\CacheEntry;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use PHPUnit\Framework\TestCase;
final class FilesystemCacheStoreTest extends TestCase {
	/** @var string */ private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-store-' . uniqid( '', true ); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { $it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $it as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $this->directory ); } }
	public function test_dependency_and_url_invalidation_are_scoped(): void { $store = new FilesystemCacheStore( $this->directory ); $store->write( CacheEntry::create( 'a', 'https://example.test/a', 'A', array(), 100, 0, array( 'post-1' ), '', 1 ) ); $store->write( CacheEntry::create( 'b', 'https://example.test/b', 'B', array(), 100, 0, array( 'post-2' ), '', 1 ) ); $this->assertSame( 1, $store->purge_dependencies( array( 'post-1' ) ) ); $this->assertNull( $store->read( 'a' ) ); $this->assertNotNull( $store->read( 'b' ) ); $this->assertSame( 1, $store->purge_urls( array( 'https://example.test/b' ) ) ); }
	public function test_corrupt_file_is_treated_as_a_miss(): void { $store = new FilesystemCacheStore( $this->directory ); $store->write( CacheEntry::create( 'safe', 'https://example.test/', 'valid', array(), 100, 0, array(), '', 1 ) ); $files = array_values( array_filter( iterator_to_array( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ) ) ), static function ( $file ): bool { return (bool) preg_match( '/[a-f0-9]{64}\.php$/', $file->getPathname() ); } ) ); file_put_contents( $files[0]->getPathname(), '{broken' ); $this->assertNull( $store->read( 'safe' ) ); }
	public function test_atomic_replacement_never_exposes_partial_content(): void { $first = new FilesystemCacheStore( $this->directory ); $second = new FilesystemCacheStore( $this->directory ); for ( $i = 0; $i < 20; ++$i ) { $writer = 0 === $i % 2 ? $first : $second; $this->assertTrue( $writer->write( CacheEntry::create( 'shared', 'https://example.test/', str_repeat( (string) $i, 1000 ), array(), 100, 0 ) ) ); $entry = $first->read( 'shared' ); $this->assertNotNull( $entry ); $this->assertSame( 1000, strlen( $entry->content() ) ); } }
	public function test_process_concurrent_writes_remain_atomic(): void {
		if ( ! function_exists( 'pcntl_fork' ) ) { $this->markTestSkipped( 'The pcntl extension is unavailable.' ); }
		$children = array();
		for ( $worker = 1; $worker <= 3; ++$worker ) {
			$pid = pcntl_fork();
			if ( 0 === $pid ) { $store = new FilesystemCacheStore( $this->directory ); for ( $i = 0; $i < 10; ++$i ) { $store->write( CacheEntry::create( 'contended', 'https://example.test/', str_repeat( (string) $worker, 2048 ), array(), 100, 0 ) ); } exit( 0 ); }
			$this->assertGreaterThan( 0, $pid ); $children[] = $pid;
		}
		foreach ( $children as $pid ) { pcntl_waitpid( $pid, $status ); $this->assertTrue( pcntl_wifexited( $status ) ); $this->assertSame( 0, pcntl_wexitstatus( $status ) ); }
		$entry = ( new FilesystemCacheStore( $this->directory ) )->read( 'contended' ); $this->assertNotNull( $entry ); $this->assertSame( 2048, strlen( $entry->content() ) );
	}
}
