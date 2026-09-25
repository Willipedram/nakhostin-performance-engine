<?php
/** Cache key stability tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use PHPUnit\Framework\TestCase;
final class CacheKeyGeneratorTest extends TestCase {
	public function test_keys_are_stable_and_dimensions_are_bounded(): void { $generator = new CacheKeyGenerator( new URLNormalizer(), new QueryPolicy( array( 'b', 'a' ) ), array( 'vary_device' => true, 'variation_dimensions' => array( 'size' ) ) ); $one = $generator->generate( new CacheRequest( 'GET', 'https://EXAMPLE.test:443/shop?b=2&a=1#fragment', array(), array(), array( 'device' => 'mobile', 'variations' => array( 'size' => 'large', 'secret' => 'x' ) ) ) ); $two = $generator->generate( new CacheRequest( 'GET', 'https://example.test/shop?a=1&b=2', array(), array(), array( 'device' => 'mobile', 'variations' => array( 'size' => 'large' ) ) ) ); $this->assertSame( $one['key'], $two['key'] ); $this->assertArrayNotHasKey( 'variation:secret', $one['dimensions'] ); }
}
