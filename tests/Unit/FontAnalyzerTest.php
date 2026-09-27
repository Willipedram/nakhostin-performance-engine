<?php
/** Font intelligence tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\FontAnalyzer;
use PHPUnit\Framework\TestCase;

final class FontAnalyzerTest extends TestCase {
	public function test_detects_family_formats_urls_and_only_required_weights(): void {
		$font_css = '@font-face{font-family:"IRANSansX";font-weight:400;src:url("iransansx/Regular.woff2") format("woff2")}@font-face{font-family:"IRANSansX";font-weight:900;src:url("iransansx/Black.woff2") format("woff2")}';
		$page_css = 'body{font-family:"IRANSansX",sans-serif;font-weight:400}.not-on-page{font-weight:900}';
		$dom = array( 'source_url' => 'https://example.test/refrigerator/', 'dom_signature' => 'sig', 'elements' => array( 'body' ), 'classes' => array(), 'ids' => array(), 'attributes' => array() );
		$css_manifest = ( new CSSAnalyzer() )->analyze_stylesheet( $font_css . $page_css, $dom );
		$data = ( new FontAnalyzer() )->analyze( array( array( 'url' => 'https://example.test/fonts/font.css', 'css' => $font_css ), array( 'url' => 'https://example.test/css/page.css', 'css' => $page_css ) ), $dom, $css_manifest )->to_array();
		$this->assertSame( 'IRANSansX', $data['families'][0]['family'] );
		$this->assertTrue( $data['families'][0]['faces'][0]['required'] );
		$this->assertFalse( $data['families'][0]['faces'][1]['required'] );
		$this->assertSame( 'https://example.test/fonts/iransansx/Regular.woff2', $data['required_sources'][0] );
		$this->assertTrue( $data['stylesheets'][0]['font_only'] );
	}
}
