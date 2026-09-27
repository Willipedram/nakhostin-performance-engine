<?php
/**
 * Safe fragment conventions for WooCommerce integrations.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\WooCommerce;

use InvalidArgumentException;
use Nakhostin\PerformanceEngine\Cache\FragmentKey;

final class WooCommerceFragmentPolicy {
	public function product_key( string $fragment_name, int $product_id, int $version = 1, array $dimensions = array() ): FragmentKey {
		if ( $product_id < 1 ) {
			throw new InvalidArgumentException( 'A valid product ID is required.' );
		}
		$dimensions['product_id'] = $product_id;
		return FragmentKey::create( $fragment_name, $version, $dimensions );
	}

	public function product_dependencies( int $product_id, array $category_ids = array() ): array {
		$dependencies = array( 'product-' . absint( $product_id ), 'post-' . absint( $product_id ) );
		foreach ( $category_ids as $category_id ) {
			$category_id = absint( $category_id );
			if ( $category_id > 0 ) {
				$dependencies[] = 'product-category-' . $category_id;
			}
		}
		return array_values( array_unique( $dependencies ) );
	}

	public function private_customer_key( string $fragment_name, string $customer_scope, int $version = 1 ): FragmentKey {
		if ( '' === $customer_scope ) {
			throw new InvalidArgumentException( 'Customer-specific fragments require an opaque scope.' );
		}
		return FragmentKey::create( $fragment_name, $version, array(), FragmentKey::PRIVATE_VISIBILITY, $customer_scope );
	}
}
