<?php
/**
 * Conservative script optimization policy.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class ScriptSafetyPolicy {
	private const CRITICAL_PATTERNS = array(
		'jquery',
		'jquery-core',
		'jquery-migrate',
		'heartbeat',
		'wp-auth-check',
		'wc-checkout',
		'woocommerce',
		'wc-cart',
		'wc-cart-fragments',
		'wc-add-to-cart-variation',
		'elementor-frontend',
		'elementor-editor',
	);

	public function evaluate( ScriptAsset $asset, array $context = array() ): array {
		$handle   = $asset->handle();
		$data     = $asset->to_array();
		$critical = $this->is_critical( $handle, $context );
		$reasons  = array();

		if ( $critical ) {
			$reasons[] = 'critical-script';
		}
		if ( $asset->has_runtime_data() ) {
			$reasons[] = 'inline-or-localized-data';
		}
		if ( 'async' === $data['strategy'] ) {
			$reasons[] = 'async-execution-semantics';
		}
		if ( 'defer' === $data['strategy'] ) {
			$reasons[] = 'existing-defer-strategy';
		}
		if ( $data['module'] ) {
			$reasons[] = 'script-module';
		}
		if ( '' === $data['src'] ) {
			$reasons[] = 'inline-only-handle';
		}
		if ( $this->is_external( $data['src'] ) ) {
			$reasons[] = 'external-source';
		}

		$strategy_safe = '' === $data['strategy']
			&& ! $data['module']
			&& ! $this->is_external( $data['src'] );

		return array(
			'bundle'  => empty( $reasons ),
			'defer'   => ! $critical && ! $asset->has_runtime_data() && $strategy_safe,
			'delay'   => ! $critical && ! $asset->has_runtime_data() && $strategy_safe,
			'reasons' => $reasons,
		);
	}

	public function is_critical( string $handle, array $context = array() ): bool {
		$is_protected_context = ! empty( $context['is_admin'] )
			|| ! empty( $context['is_login'] )
			|| ! empty( $context['is_elementor_editor'] );
		if ( $is_protected_context ) {
			return true;
		}

		$page_type = sanitize_key( (string) ( $context['page_type'] ?? '' ) );
		if ( in_array( $page_type, array( 'cart', 'checkout', 'account' ), true ) ) {
			return true;
		}

		foreach ( self::CRITICAL_PATTERNS as $pattern ) {
			if ( $handle === $pattern || 0 === strpos( $handle, $pattern . '-' ) ) {
				return true;
			}
		}

		return false;
	}

	private function is_external( string $source ): bool {
		$host      = wp_parse_url( $source, PHP_URL_HOST );
		$site_host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );

		return is_string( $host ) && is_string( $site_host ) && strtolower( $host ) !== strtolower( $site_host );
	}
}
