<?php
/** Public LiteSpeed hook bridge tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyCollector;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedAdapter;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedCacheBridge;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedPurgeBridge;
use PHPUnit\Framework\TestCase;

final class LiteSpeedBridgeTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_actions']         = array();
		$GLOBALS['npe_test_options']         = array();
		$GLOBALS['npe_test_fired_actions']   = array();
		$GLOBALS['npe_test_filters']         = array();
		$GLOBALS['npe_test_conditionals']    = array();
		$GLOBALS['npe_test_queried_id']      = 0;
		$_COOKIE                            = array();
		$_SERVER['REQUEST_METHOD']           = 'GET';
		$_SERVER['HTTP_HOST']                = 'example.test';
		$_SERVER['REQUEST_URI']              = '/product/12/';
	}

	public function test_private_request_sends_documented_nocache_action(): void {
		$_COOKIE['wordpress_logged_in_test'] = 'private';
		$bridge = $this->cache_bridge();
		$bridge->register( LiteSpeedAdapter::MODE_COMPATIBLE );
		$bridge->coordinate_request();
		$this->assertSame( array( 'npe_private_cookie' ), $GLOBALS['npe_test_fired_actions']['litespeed_control_set_nocache'][0] );
		$this->assertArrayNotHasKey( 'litespeed_control_set_cacheable', $GLOBALS['npe_test_fired_actions'] );
	}

	public function test_cooperative_public_request_sends_ttl_tags_and_vary_actions(): void {
		$GLOBALS['npe_test_options'][ Settings::OPTION ] = array(
			'cache' => array( 'ttl' => 600, 'vary_device' => true, 'vary_currency' => true ),
		);
		$GLOBALS['npe_test_queried_id']                = 12;
		$GLOBALS['npe_test_post_types'][12]            = 'product';
		$GLOBALS['npe_test_post_terms'][12]['product_cat'] = array( 7 );
		$GLOBALS['npe_test_conditionals']['singular']  = true;
		add_filter( 'npe/cache/request_context', static function ( array $context ): array { $context['device'] = 'mobile'; $context['currency'] = 'USD'; return $context; } );

		$bridge = $this->cache_bridge();
		$bridge->register( LiteSpeedAdapter::MODE_COOPERATIVE );
		$bridge->coordinate_request();
		$this->assertArrayHasKey( 'litespeed_control_set_cacheable', $GLOBALS['npe_test_fired_actions'] );
		$this->assertSame( array( 600 ), $GLOBALS['npe_test_fired_actions']['litespeed_control_set_ttl'][0] );
		$this->assertContains( array( 'npe-product-12' ), $GLOBALS['npe_test_fired_actions']['litespeed_tag_add'] );
		$this->assertContains( array( 'npe-product-category-7' ), $GLOBALS['npe_test_fired_actions']['litespeed_tag_add'] );
		$this->assertContains( array( 'npe-device-mobile' ), $GLOBALS['npe_test_fired_actions']['litespeed_vary_add'] );
		$this->assertContains( array( 'npe-currency-usd' ), $GLOBALS['npe_test_fired_actions']['litespeed_vary_add'] );
	}

	public function test_purge_bridge_forwards_scoped_urls_tags_and_full_purge(): void {
		$bridge = new LiteSpeedPurgeBridge();
		$bridge->register();
		do_action( 'npe/cache/purged', 'urls', array( 'https://example.test/product/12/' ) );
		do_action( 'npe/cache/purged', 'dependencies', array( 'product-12', 'product-category-7' ) );
		$this->assertSame( array( 'https://example.test/product/12/' ), $GLOBALS['npe_test_fired_actions']['litespeed_purge_url'][0] );
		$this->assertSame( array( 'npe-product-12' ), $GLOBALS['npe_test_fired_actions']['litespeed_purge_tag'][0] );
		$this->assertSame( array( 'npe-product-category-7' ), $GLOBALS['npe_test_fired_actions']['litespeed_purge_tag'][1] );
		$this->assertArrayNotHasKey( 'litespeed_purge_all', $GLOBALS['npe_test_fired_actions'] );
		do_action( 'npe/cache/purged', 'all', array() );
		$this->assertArrayHasKey( 'litespeed_purge_all', $GLOBALS['npe_test_fired_actions'] );
	}

	private function cache_bridge(): LiteSpeedCacheBridge {
		return new LiteSpeedCacheBridge( new CacheRequestFactory(), new CachePolicy(), new CacheDependencyCollector(), new Settings() );
	}
}
