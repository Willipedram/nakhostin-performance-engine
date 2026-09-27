<?php
/**
 * Hashed JavaScript bundle output tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptBundleWriter;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use PHPUnit\Framework\TestCase;

final class JavaScriptBundleWriterTest extends TestCase {
	public function test_writes_content_hashed_bundle_in_dependency_order(): void {
		$directory = sys_get_temp_dir() . '/npe-js-' . uniqid( '', true );
		$writer    = new JavaScriptBundleWriter( new ConservativeMinifier(), $directory );
		$assets    = array(
			new ScriptAsset(
				array( 'handle' => 'app', 'src' => '/app.js', 'dependencies' => array( 'dependency' ) )
			),
			new ScriptAsset( array( 'handle' => 'dependency', 'src' => '/dependency.js' ) ),
		);
		$result = $writer->build(
			'component',
			$assets,
			array( 'dependency' => 'window.dep = true;', 'app' => 'window.app = window.dep;' ),
			$directory
		);
		$file = $directory . '/' . $result['filename'];

		$this->assertFileExists( $file );
		$this->assertFileExists( $directory . '/index.php' );
		$this->assertFileExists( $directory . '/.htaccess' );
		$this->assertStringContainsString( 'Options -ExecCGI', (string) file_get_contents( $directory . '/.htaccess' ) );
		$this->assertMatchesRegularExpression( '/^component-[a-f0-9]{20}\.js$/', $result['filename'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Test fixture read.
		$content = file_get_contents( $file );
		$this->assertLessThan( strpos( $content, 'window.app' ), strpos( $content, 'window.dep' ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture cleanup.
		unlink( $file );
		unlink( $directory . '/index.php' );
		unlink( $directory . '/.htaccess' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
		rmdir( $directory );
	}

	public function test_rejects_php_payloads(): void {
		$trusted = sys_get_temp_dir() . '/npe-js-trusted-' . uniqid( '', true );
		$asset   = new ScriptAsset( array( 'handle' => 'app', 'src' => '/app.js' ) );
		$writer  = new JavaScriptBundleWriter( new ConservativeMinifier(), $trusted );
		$this->expectException( \RuntimeException::class );
		$writer->build( 'page', array( $asset ), array( 'app' => '<?php echo "unsafe"; ?>' ), $trusted );
	}

	public function test_rejects_untrusted_output_directory(): void {
		$trusted = sys_get_temp_dir() . '/npe-js-trusted-' . uniqid( '', true );
		$other   = sys_get_temp_dir() . '/npe-js-other-' . uniqid( '', true );
		wp_mkdir_p( $trusted );
		$asset  = new ScriptAsset( array( 'handle' => 'app', 'src' => '/app.js' ) );
		$writer = new JavaScriptBundleWriter( new ConservativeMinifier(), $trusted );
		try {
			$writer->build( 'page', array( $asset ), array( 'app' => 'window.safe = true;' ), $other );
			$this->fail( 'An untrusted output directory must be rejected.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertStringContainsString( 'outside the trusted', $exception->getMessage() );
		} finally {
			if ( is_dir( $other ) ) { rmdir( $other ); }
			if ( is_dir( $trusted ) ) { rmdir( $trusted ); }
		}
	}
}
