<?php
/** WooCommerce fragment safety tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Cache\FragmentKey;
use Nakhostin\PerformanceEngine\Integrations\WooCommerce\WooCommerceFragmentPolicy;
use PHPUnit\Framework\TestCase;

final class WooCommerceFragmentPolicyTest extends TestCase {
	public function test_product_fragments_have_product_and_category_dependencies(): void {
		$policy = new WooCommerceFragmentPolicy();
		$key    = $policy->product_key( 'product-card', 123 );
		$this->assertSame( FragmentKey::PUBLIC_VISIBILITY, $key->visibility() );
		$this->assertSame( array( 'product-123', 'post-123', 'product-category-7' ), $policy->product_dependencies( 123, array( 7 ) ) );
	}

	public function test_customer_fragment_is_always_private(): void {
		$key = ( new WooCommerceFragmentPolicy() )->private_customer_key( 'mini-cart-shell', 'opaque-session-scope' );
		$this->assertSame( FragmentKey::PRIVATE_VISIBILITY, $key->visibility() );
	}
}
