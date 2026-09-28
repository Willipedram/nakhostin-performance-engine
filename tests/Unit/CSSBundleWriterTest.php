<?php
/** Page CSS bundle tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\CSS\CSSBundleWriter;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use PHPUnit\Framework\TestCase;
final class CSSBundleWriterTest extends TestCase {
	private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-css-bundle-' . uniqid(); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { foreach ( glob( $this->directory . '/*' ) ?: array() as $file ) { unlink( $file ); } rmdir( $this->directory ); } }
	public function test_builds_ordered_page_bundle_and_removes_only_classified_unused_rules(): void {
		$manifest = new CSSManifest( array( 'used' => array( '.hero' ), 'preserved' => array( '.menu:hover' ), 'unused_candidates' => array( '.unused' ) ) );
		$writer = new CSSBundleWriter( $this->directory, 'https://example.test/cache/css/' );
		$result = $writer->build( array( array( 'url' => 'https://example.test/theme/css/site.css', 'css' => '.hero{background:url(../img/a.png)}.unused{display:none}.menu:hover{color:red}@font-face{font-family:x;src:url(font.woff2)}' ) ), $manifest );
		$content = file_get_contents( $this->directory . '/' . $result['filename'] );
		self::assertStringContainsString( '.hero{', $content ); self::assertStringContainsString( '.menu:hover{', $content ); self::assertStringContainsString( '@font-face{', $content ); self::assertStringNotContainsString( '.unused{', $content ); self::assertStringContainsString( 'https://example.test/theme/img/a.png', $content );
	}
}
