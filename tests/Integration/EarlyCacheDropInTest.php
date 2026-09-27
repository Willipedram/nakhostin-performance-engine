<?php
namespace Nakhostin\PerformanceEngine\Tests\Integration;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheInstaller;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheKeyGenerator;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheRequest;
use Nakhostin\PerformanceEngine\Environment\DropInDetector;
use PHPUnit\Framework\TestCase;

final class EarlyCacheDropInTest extends TestCase {
	private $root;
	protected function setUp(): void { $this->root = sys_get_temp_dir() . '/npe-dropin-e2e-' . uniqid(); mkdir( $this->root ); mkdir( $this->root . '/cache/nakhostin-performance-engine', 0777, true ); }
	protected function tearDown(): void { if ( ! is_dir( $this->root ) ) { return; } $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST ); foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); } rmdir( $this->root ); }
	public function test_hit_terminates_before_normal_bootstrap_continues(): void {
		if ( ! function_exists( 'shell_exec' ) ) { $this->markTestSkipped( 'shell_exec is required for isolated drop-in verification.' ); }
		$plugin = dirname( __DIR__, 2 ); $installer = new EarlyCacheInstaller( new DropInDetector( $this->root ), $plugin . '/dropins/advanced-cache.php', $plugin ); self::assertTrue( $installer->install()['success'] );
		$config = array( 'enabled' => true, 'cache_directory' => $this->root . '/cache/nakhostin-performance-engine', 'home_host' => 'example.test', 'site_id' => 1, 'language' => 'en_US', 'allowed_query_parameters' => array(), 'excluded_paths' => array() ); file_put_contents( $config['cache_directory'] . '/early-config.json', json_encode( $config ) );
		$request = new EarlyCacheRequest( 'GET', 'https', 'example.test', '/' ); $key = ( new EarlyCacheKeyGenerator() )->generate( $request, $config )['key']; $hash = hash( 'sha256', $key ); $directory = $config['cache_directory'] . '/entries/' . substr( $hash, 0, 2 ); mkdir( $directory, 0777, true ); $html = '<html>early-hit</html>'; $now = time(); $record = array( 'cache_version' => 2, 'key' => $key, 'url' => 'https://example.test/', 'created_at' => $now, 'expires_at' => $now + 60, 'stale_until' => $now + 120, 'content_hash' => hash( 'sha256', $html ), 'headers' => array( 'content-type' => 'text/html' ), 'content' => $html ); file_put_contents( $directory . '/' . $hash . '.php', "<?php exit; ?>\n" . json_encode( $record ) );
		$runner = $this->root . '/runner.php'; $code = "<?php define('ABSPATH', __DIR__ . '/'); define('WP_CONTENT_DIR', " . var_export( $this->root, true ) . "); \$_SERVER = array('REQUEST_METHOD'=>'GET','HTTPS'=>'on','HTTP_HOST'=>'example.test','REQUEST_URI'=>'/'); \$_COOKIE = array(); include " . var_export( $this->root . '/advanced-cache.php', true ) . "; echo 'BOOT-CONTINUED';"; file_put_contents( $runner, $code );
		$output = shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $runner ) ); self::assertSame( $html, $output );
	}
}
