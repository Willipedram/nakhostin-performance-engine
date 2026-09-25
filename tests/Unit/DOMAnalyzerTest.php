<?php
/**
 * DOM Intelligence analyzer tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMSnapshot;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use PHPUnit\Framework\TestCase;

final class DOMAnalyzerTest extends TestCase {
	/** @var DOMAnalyzer */
	private $analyzer;

	protected function setUp(): void {
		$this->analyzer = new DOMAnalyzer(
			new DOMComponentDetector(),
			new DOMStateRegistry(),
			new DOMSignature()
		);
	}

	public function test_generates_structured_manifest_and_extracts_usage(): void {
		$html = '<!doctype html><html><body class="page page-template-landing menu-open">'
			. '<header class="site-header"><nav><a class="nav-link active" href="/shop">Shop</a></nav></header>'
			. '<main id="content" data-layout="wide"><form><input type="submit"><button>Go</button></form></main>'
			. '<link rel="stylesheet" href="/assets/site.css?v=1"><script src="/assets/app.js?token=private"></script>'
			. '<footer></footer></body></html>';

		$manifest = $this->analyzer->analyze(
			$html,
			array(
				'source_url' => 'https://example.test/landing?customer=42#private',
				'versions'   => array( 'wordpress' => '6.6', 'unknown' => 'discard' ),
			)
		);

		$this->assertSame( 'page', $manifest['page_type'] );
		$this->assertSame( 'page-template-landing', $manifest['template_identifier'] );
		$this->assertContains( 'nav-link', $manifest['classes'] );
		$this->assertContains( 'page-template-landing', $manifest['body_classes'] );
		$this->assertContains( 'content', $manifest['ids'] );
		$this->assertContains( 'data-layout', $manifest['data_attributes'] );
		$this->assertSame( 1, $manifest['forms'] );
		$this->assertSame( 2, $manifest['buttons'] );
		$this->assertSame( 1, $manifest['links'] );
		$this->assertContains( 'header', $manifest['components'] );
		$this->assertContains( 'navigation', $manifest['components'] );
		$this->assertContains( 'menu-open', $manifest['detected_states'] );
		$this->assertSame( 'https://example.test/landing', $manifest['source_url'] );
		$this->assertSame( '/assets/site.css', $manifest['stylesheets'][0] );
		$this->assertSame( '/assets/app.js', $manifest['scripts'][0]['src'] );
		$this->assertArrayNotHasKey( 'unknown', $manifest['versions'] );
	}

	public function test_signature_is_stable_across_dynamic_page_instances(): void {
		$first = '<body class="single-product postid-123 elementor-page elementor-element-a1b2c3d">'
			. '<main><div id="product-123" class="product type-product"><h1>First product</h1></div></main></body>';
		$second = '<body class="single-product postid-999 elementor-page elementor-element-f9e8d7c">'
			. '<main><div id="product-999" class="product type-product"><h1>Another product</h1></div></main></body>';

		$first_manifest  = $this->analyzer->analyze( $first, array( 'source_url' => 'https://example.test/product/a' ) );
		$second_manifest = $this->analyzer->analyze( $second, array( 'source_url' => 'https://example.test/product/b' ) );

		$this->assertSame( $first_manifest['dom_signature'], $second_manifest['dom_signature'] );
	}

	public function test_detects_elementor_and_woocommerce_structures(): void {
		$html = '<body class="woocommerce elementor-page">'
			. '<div class="elementor-widget elementor-widget-heading"></div>'
			. '<ul class="products"><li class="product"><div class="woocommerce-product-gallery"></div></li></ul>'
			. '<form class="variations" data-product_variations="private-payload"></form>'
			. '<div id="reviews"></div><div class="widget_shopping_cart"></div></body>';
		$manifest = $this->analyzer->analyze( $html );

		$this->assertContains( 'elementor', $manifest['integrations'] );
		$this->assertContains( 'woocommerce', $manifest['integrations'] );
		$this->assertContains( 'heading', $manifest['elementor_widgets'] );
		$this->assertContains( 'product-grid', $manifest['components'] );
		$this->assertContains( 'product-card', $manifest['components'] );
		$this->assertContains( 'product-gallery', $manifest['components'] );
		$this->assertContains( 'product-variations', $manifest['components'] );
		$this->assertContains( 'cart-fragments', $manifest['components'] );
		$this->assertContains( 'reviews', $manifest['components'] );
	}

	public function test_malformed_and_malicious_html_is_bounded_and_privacy_filtered(): void {
		$html = '<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><body>'
			. '<input type="password" value="do-not-store"><div id="user-secret-token" class="customer-email@example.test">'
			. '&xxe;<a href="https://example.test/?token=secret">link';
		$manifest = $this->analyzer->analyze(
			$html,
			array( 'source_url' => 'https://user:pass@example.test/account/user@example.test?token=secret' )
		);
		$serialized = wp_json_encode( $manifest );

		$this->assertNotFalse( $serialized );
		$this->assertStringNotContainsString( 'do-not-store', $serialized );
		$this->assertStringNotContainsString( 'user-secret-token', $serialized );
		$this->assertStringNotContainsString( 'customer-email@example.test', $serialized );
		$this->assertStringNotContainsString( 'file:///etc/passwd', $serialized );
		$this->assertStringNotContainsString( 'token=secret', $serialized );
		$this->assertStringNotContainsString( 'user:pass', $serialized );
	}

	public function test_rejects_oversized_snapshots(): void {
		$this->expectException( InvalidArgumentException::class );

		new DOMSnapshot( str_repeat( 'x', DOMSnapshot::MAX_BYTES + 1 ) );
	}
}
