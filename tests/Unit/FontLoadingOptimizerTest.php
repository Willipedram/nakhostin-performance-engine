<?php
/** Page-aware font loading tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\FontLoadingOptimizer;
use Nakhostin\PerformanceEngine\CSS\FontManifest;
use Nakhostin\PerformanceEngine\CSS\FontStorage;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class FontLoadingOptimizerTest extends TestCase {
	protected function setUp(): void { $GLOBALS['npe_test_filters'] = array(); $GLOBALS['npe_test_actions'] = array(); $GLOBALS['npe_test_options'] = array(); $GLOBALS['npe_test_dequeued_styles'] = array(); $GLOBALS['npe_test_inline_styles'] = array(); }
	public function test_replaces_font_only_stylesheet_with_required_faces(): void {
		$GLOBALS['npe_test_options'] = array( Settings::OPTION => array( 'fonts' => array( 'enabled' => true ), 'optimization' => array( 'safe_mode' => false ) ) );
		$GLOBALS['npe_test_dequeued_styles'] = array(); $GLOBALS['npe_test_inline_styles'] = array();
		$_SERVER['REQUEST_URI'] = '/refrigerator/';
		$storage = new FontStorage();
		$regular = array( 'family' => 'IRANSansX', 'weight' => '400', 'style' => 'normal', 'display' => 'swap', 'required' => true, 'sources' => array( array( 'url' => 'https://example.test/fonts/regular.woff2', 'format' => 'woff2' ) ) );
		$black = array( 'family' => 'IRANSansX', 'weight' => '900', 'style' => 'normal', 'display' => 'swap', 'required' => false, 'sources' => array( array( 'url' => 'https://example.test/fonts/black.woff2', 'format' => 'woff2' ) ) );
		$data = array( 'source_url' => 'https://example.test/refrigerator/', 'required_sources' => array( 'https://example.test/fonts/regular.woff2' ), 'unused_sources' => array( 'https://example.test/fonts/black.woff2' ), 'stylesheets' => array( array( 'url' => 'https://example.test/fonts/font.css', 'font_only' => true, 'faces' => array( $regular, $black ) ) ) );
		$storage->save( new FontManifest( $data ) );
		$GLOBALS['wp_styles'] = (object) array( 'registered' => array( 'site-fonts' => (object) array( 'src' => 'https://example.test/fonts/font.css?ver=1' ) ) );
		$optimizer = new FontLoadingOptimizer( $storage, new Settings() ); $optimizer->optimize();
		$this->assertSame( array( 'site-fonts' ), $GLOBALS['npe_test_dequeued_styles'] );
		$css = $GLOBALS['npe_test_inline_styles']['npe-required-fonts'][0];
		$this->assertStringContainsString( 'regular.woff2', $css );
		$this->assertStringNotContainsString( 'black.woff2', $css );
	}

	public function test_safe_mode_does_not_register_destructive_font_replacement(): void {
		$GLOBALS['npe_test_options'] = array( Settings::OPTION => array( 'fonts' => array( 'enabled' => true ), 'optimization' => array( 'safe_mode' => true ) ) );
		$optimizer = new FontLoadingOptimizer( new FontStorage(), new Settings() );

		$optimizer->register();

		$this->assertArrayNotHasKey( 'wp_enqueue_scripts', $GLOBALS['npe_test_actions'] );
	}

	public function test_cache_purge_invalidates_stale_font_decision(): void {
		$GLOBALS['npe_test_options'] = array();
		$storage = new FontStorage();
		$storage->save( new FontManifest( array( 'source_url' => 'https://example.test/refrigerator/' ) ) );
		$optimizer = new FontLoadingOptimizer( $storage, new Settings() );
		$optimizer->cache_purged( 'urls', array( 'https://example.test/refrigerator/' ) );
		$this->assertNull( $storage->for_url( 'https://example.test/refrigerator/' ) );
	}

	public function test_litespeed_compatibility_gate_keeps_original_stylesheet(): void {
		$GLOBALS['npe_test_options'] = array( Settings::OPTION => array( 'fonts' => array( 'enabled' => true ), 'optimization' => array( 'safe_mode' => false ) ) );
		add_filter( 'npe/css/optimization_enabled', static function (): bool { return false; } );
		( new FontLoadingOptimizer( new FontStorage(), new Settings() ) )->optimize();
		$this->assertSame( array(), $GLOBALS['npe_test_dequeued_styles'] );
	}
}
