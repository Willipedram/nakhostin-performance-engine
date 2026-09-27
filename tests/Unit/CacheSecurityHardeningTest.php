<?php
/** Cache privacy and filesystem hardening regression tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheEntry;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use PHPUnit\Framework\TestCase;

final class CacheSecurityHardeningTest extends TestCase {
	/** @var string */ private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-security-' . uniqid( '', true ); $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTP_HOST'] = 'example.test'; $_COOKIE = array(); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); } rmdir( $this->directory ); } }

	public function test_private_session_cookies_never_enter_public_cache(): void {
		$policy = new CachePolicy();
		foreach ( array( 'wordpress_sec_hash', 'wp_woocommerce_session_hash', 'customer_session', 'member_token', 'shopping_cart' ) as $cookie ) {
			$this->assertFalse( $policy->classify_request( new CacheRequest( 'GET', 'https://example.test/', array(), array( $cookie => '' ) ) )->is_cacheable(), $cookie );
		}
	}

	public function test_sensitive_markup_and_query_parameters_are_rejected(): void {
		$policy = new CachePolicy();
		$this->assertFalse( $policy->classify_response( 200, array(), '<div id="wpadminbar">Private</div>' )->is_cacheable() );
		$this->assertFalse( $policy->classify_response( 200, array(), '<input name="woocommerce-login-nonce" value="secret">' )->is_cacheable() );
		foreach ( array( 'session_id=x', 'authorization=x', 'order-received=10', 'email=user@example.test' ) as $query ) { $this->assertFalse( ( new QueryPolicy( array( 'session_id', 'authorization', 'order-received', 'email' ) ) )->evaluate( $query )['cacheable'] ); }
		$this->assertFalse( ( new QueryPolicy( array( 'safe' ) ) )->evaluate( 'safe=' . str_repeat( 'x', 2050 ) )['cacheable'] );
	}

	public function test_host_header_port_mismatch_is_private(): void {
		$_SERVER['HTTP_HOST'] = 'example.test:8080';
		$request = ( new CacheRequestFactory() )->from_globals();
		$this->assertTrue( $request->context_value( 'is_foreign_host' ) );
		$this->assertFalse( ( new CachePolicy() )->classify_request( $request )->is_cacheable() );
	}

	public function test_cache_files_are_non_executable_and_unprefixed_data_is_rejected(): void {
		$store = new FilesystemCacheStore( $this->directory );
		$this->assertTrue( $store->write( CacheEntry::create( 'safe', 'https://example.test/', '<html>safe</html>', array(), 60, 0 ) ) );
		$this->assertFileExists( $this->directory . '/index.php' );
		$this->assertFileExists( $this->directory . '/.htaccess' );
		$files = glob( $this->directory . '/entries/*/*.php' );
		$this->assertNotEmpty( $files );
		$this->assertStringStartsWith( '<?php exit; ?>', (string) file_get_contents( $files[0] ) );
		file_put_contents( $files[0], wp_json_encode( CacheEntry::create( 'safe', 'https://example.test/', 'tampered', array(), 60, 0 )->to_array() ) );
		$this->assertNull( $store->read( 'safe' ) );
	}

	public function test_unwritable_cache_location_fails_open_without_an_entry(): void {
		$store = new FilesystemCacheStore( '/proc/npe-unwritable-' . uniqid() );
		$this->assertFalse( $store->write( CacheEntry::create( 'safe', 'https://example.test/', 'content', array(), 60, 0 ) ) );
		$this->assertNull( $store->read( 'safe' ) );
	}
}
