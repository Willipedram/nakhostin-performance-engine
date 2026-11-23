<?php
/** Page CSS bundle tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\CSS\CSSBundleWriter;
use PHPUnit\Framework\TestCase;
final class CSSBundleWriterTest extends TestCase {
	private $directory;
	protected function setUp(): void { $this->directory = sys_get_temp_dir() . '/npe-css-bundle-' . uniqid(); }
	protected function tearDown(): void { if ( is_dir( $this->directory ) ) { foreach ( glob( $this->directory . '/*' ) ?: array() as $file ) { unlink( $file ); } rmdir( $this->directory ); } }
	public function test_builds_bundle_without_removing_selectors_or_changing_font_display(): void {
		$writer = new CSSBundleWriter( $this->directory, 'https://example.test/cache/css/' );
		$result = $writer->build( array( array( 'url' => 'https://example.test/theme/css/site.css', 'css' => '.hero{background:url(../img/a.png)}.unused{display:none}.menu:hover{color:red}@font-face{font-family:x;src:url(font.woff2)}' ) ) );
		$content = file_get_contents( $this->directory . '/' . $result['filename'] );
		self::assertStringContainsString( '.hero{', $content ); self::assertStringContainsString( '.menu:hover{', $content ); self::assertStringContainsString( '@font-face{', $content ); self::assertStringNotContainsString( 'font-display:swap', $content ); self::assertStringContainsString( '.unused{', $content ); self::assertStringContainsString( 'https://example.test/theme/img/a.png', $content );
	}

	public function test_root_relative_url_preserves_origin_port_and_suffix(): void {
		$writer = new CSSBundleWriter( $this->directory, 'https://example.test:8443/cache/css/' );
		$result = $writer->build(
			array(
				array(
					'url' => 'https://example.test:8443/theme/css/site.css',
					'css' => '.hero{background:url("/img/banner.svg?v=2#hero")}',
				),
			)
		);

		self::assertStringContainsString(
			'url("https://example.test:8443/img/banner.svg?v=2#hero")',
			file_get_contents( $this->directory . '/' . $result['filename'] )
		);
	}
}
