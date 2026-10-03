<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\EarlyCache\EarlyCacheInstaller;
use Nakhostin\PerformanceEngine\Environment\DropInDetector;
use PHPUnit\Framework\TestCase;

final class EarlyCacheInstallerTest extends TestCase {
	private $root;
	protected function setUp(): void { $this->root = sys_get_temp_dir() . '/npe-dropin-' . uniqid(); mkdir( $this->root ); }
	protected function tearDown(): void { foreach ( glob( $this->root . '/*' ) ?: array() as $file ) { unlink( $file ); } rmdir( $this->root ); }
	private function installer(): EarlyCacheInstaller { return new EarlyCacheInstaller( new DropInDetector( $this->root ), dirname( __DIR__, 2 ) . '/dropins/advanced-cache.php', dirname( __DIR__, 2 ) ); }
	public function test_installs_verifies_and_removes_owned_dropin(): void { $result = $this->installer()->install(); self::assertTrue( $result['success'] ); self::assertStringContainsString( 'NPE_ADVANCED_CACHE_DROPIN', file_get_contents( $this->root . '/advanced-cache.php' ) ); self::assertTrue( $this->installer()->remove() ); self::assertFileDoesNotExist( $this->root . '/advanced-cache.php' ); }
	public function test_never_overwrites_unknown_dropin(): void { $path = $this->root . '/advanced-cache.php'; file_put_contents( $path, '<?php /* foreign cache */' ); $before = hash_file( 'sha256', $path ); $result = $this->installer()->install(); self::assertFalse( $result['success'] ); self::assertSame( 'external_dropin', $result['status'] ); self::assertSame( $before, hash_file( 'sha256', $path ) ); self::assertFalse( $this->installer()->remove() ); }
	public function test_recognizes_and_upgrades_markerless_legacy_npe_dropin(): void { $path = $this->root . '/advanced-cache.php'; file_put_contents( $path, "<?php\nnew \\Nakhostin\\PerformanceEngine\\EarlyCache\\EarlyCacheRuntime( array() );\n\$config = 'cache/nakhostin-performance-engine/early-config.json';" ); $before = hash_file( 'sha256', $path ); $state = $this->installer()->state(); self::assertTrue( $state['npe_owned'] ); self::assertSame( 'legacy-npe-signatures', $state['reason'] ); $result = $this->installer()->install(); self::assertTrue( $result['success'] ); self::assertNotSame( $before, hash_file( 'sha256', $path ) ); self::assertStringContainsString( 'NPE_ADVANCED_CACHE_DROPIN', file_get_contents( $path ) ); }
	public function test_identifies_known_foreign_owner_without_modifying_it(): void { $path = $this->root . '/advanced-cache.php'; file_put_contents( $path, '<?php /* Cache Enabler */ define( "CACHE_ENABLER_DIR", __DIR__ );' ); $before = hash_file( 'sha256', $path ); $state = $this->installer()->state(); self::assertSame( 'cache_enabler', $state['owner'] ); self::assertSame( 'known-owner-marker', $state['reason'] ); self::assertFalse( $this->installer()->install()['success'] ); self::assertSame( $before, hash_file( 'sha256', $path ) ); }
	public function test_fails_safely_when_content_directory_is_unavailable(): void { $missing = $this->root . '/missing/content'; $installer = new EarlyCacheInstaller( new DropInDetector( $missing ), dirname( __DIR__, 2 ) . '/dropins/advanced-cache.php', dirname( __DIR__, 2 ) ); $result = $installer->install(); self::assertFalse( $result['success'] ); self::assertSame( 'unwritable', $result['status'] ); self::assertDirectoryDoesNotExist( $missing ); }
}
