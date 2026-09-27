<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheKeyGenerator;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheRequest;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheRuntime;
use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use PHPUnit\Framework\TestCase;

final class EarlyCacheRuntimeTest extends TestCase {
	private $root; private $config;
	protected function setUp(): void { $this->root = sys_get_temp_dir() . '/npe-early-' . uniqid(); mkdir( $this->root ); $this->config = array( 'enabled' => true, 'cache_directory' => $this->root, 'home_host' => 'example.test', 'site_id' => 1, 'language' => 'en_US', 'allowed_query_parameters' => array(), 'excluded_paths' => array() ); }
	protected function tearDown(): void { if ( is_dir( $this->root ) ) { $it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $it as $file ) { $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); } rmdir( $this->root ); } }
	private function request( string $method = 'GET', string $uri = '/', array $cookies = array(), array $headers = array() ): EarlyCacheRequest { return new EarlyCacheRequest( $method, 'https', 'example.test', $uri, $headers, $cookies ); }
	private function write( EarlyCacheRequest $request, int $expires, int $stale ): void { $key = ( new EarlyCacheKeyGenerator() )->generate( $request, $this->config )['key']; $hash = hash( 'sha256', $key ); $dir = $this->root . '/entries/' . substr( $hash, 0, 2 ); mkdir( $dir, 0777, true ); $content = '<html>cached</html>'; $data = array( 'cache_version' => 1, 'key' => $key, 'url' => 'https://example.test/', 'created_at' => 100, 'expires_at' => $expires, 'stale_until' => $stale, 'content_hash' => hash( 'sha256', $content ), 'headers' => array( 'content-type' => 'text/html' ), 'content' => $content ); file_put_contents( $dir . '/' . $hash . '.php', "<?php exit; ?>\n" . json_encode( $data ) ); }
	public function test_hit_miss_and_stale(): void { $request = $this->request(); $this->write( $request, 200, 300 ); $runtime = new EarlyCacheRuntime( $this->config ); $hit = $runtime->lookup( $request, 150 ); self::assertSame( 'HIT', $hit->status() ); self::assertSame( 'EARLY', $hit->headers()['X-NPE-Cache-Layer'] ); self::assertSame( 'NPE', $hit->headers()['X-NPE-Cache-Provider'] ); self::assertSame( 'STALE', $runtime->lookup( $request, 250 )->status() ); self::assertSame( 'MISS', $runtime->lookup( $request, 301 )->status() ); }
	public function test_key_matches_application_cache_default_dimensions(): void { $early = ( new EarlyCacheKeyGenerator() )->generate( $this->request(), $this->config ); $application_request = new CacheRequest( 'GET', 'https://example.test/', array(), array(), array( 'site_id' => 1, 'language' => 'en_US' ) ); $application = ( new CacheKeyGenerator( new URLNormalizer(), new QueryPolicy() ) )->generate( $application_request ); self::assertSame( $application['url'], $early['url'] ); self::assertSame( $application['key'], $early['key'] ); }
	/** @dataProvider privateRequests */
	public function test_private_requests_bypass( EarlyCacheRequest $request ): void { self::assertSame( 'BYPASS', ( new EarlyCacheRuntime( $this->config ) )->lookup( $request )->status() ); }
	public static function privateRequests(): array { $request = static function ( string $method = 'GET', string $uri = '/', array $cookies = array(), array $headers = array() ): EarlyCacheRequest { return new EarlyCacheRequest( $method, 'https', 'example.test', $uri, $headers, $cookies ); }; return array( 'post' => array( $request( 'POST' ) ), 'authorization' => array( $request( 'GET', '/', array(), array( 'authorization' => 'present' ) ) ), 'session' => array( $request( 'GET', '/', array( 'wp_woocommerce_session_abc' => '1' ) ) ), 'cart' => array( $request( 'GET', '/cart/' ) ), 'checkout' => array( $request( 'GET', '/checkout/' ) ), 'account' => array( $request( 'GET', '/my-account/' ) ), 'wc ajax' => array( $request( 'GET', '/?wc-ajax=add_to_cart' ) ) ); }
}
