<?php
/**
 * Centralized plugin configuration.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Infrastructure;

final class Settings {
	public const OPTION = 'npe_settings';

	public const MODULE_GROUPS = array(
		'dom',
		'css',
		'javascript',
		'cache',
		'woocommerce',
		'elementor',
		'woodmart',
		'litespeed',
		'performance',
	);

	public function defaults(): array {
		$defaults = array(
			'general'   => array(
				'remove_data_on_uninstall' => false,
			),
			'debugging' => array(
				'enabled' => false,
			),
		);

		foreach ( self::MODULE_GROUPS as $group ) {
			$defaults[ $group ] = array( 'enabled' => false );
		}
		$defaults['dom'] = array(
			'enabled'        => false,
			'sample_rate'    => 10,
			'cooldown_hours' => 24,
		);
		$defaults['cache'] = array(
			'enabled'                  => false,
			'ttl'                      => 300,
			'stale_ttl'                => 60,
			'allowed_query_parameters' => array(),
			'excluded_paths'           => array(),
			'vary_device'              => false,
			'vary_currency'            => false,
			'variation_dimensions'     => array(),
			'warm_homepage'            => true,
			'important_product_ids'     => array(),
			'important_category_ids'    => array(),
		);
		$defaults['litespeed'] = array(
			'enabled' => true,
			'mode'    => 'compatible',
		);
		$defaults['performance'] = array(
			'enabled'        => false,
			'sample_rate'    => 10,
			'retention_days' => 7,
			'max_samples'    => 500,
		);

		return $defaults;
	}

	public function all(): array {
		$stored = get_option( self::OPTION, array() );

		return $this->sanitize( is_array( $stored ) ? $stored : array() );
	}

	public function get( string $path, $default = null ) {
		$value = $this->all();

		foreach ( explode( '.', $path ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return $default;
			}

			$value = $value[ $segment ];
		}

		return $value;
	}

	public function sanitize( $input ): array {
		$input     = is_array( $input ) ? $input : array();
		$sanitized = $this->defaults();
		$general   = isset( $input['general'] ) && is_array( $input['general'] ) ? $input['general'] : array();
		$debugging = isset( $input['debugging'] ) && is_array( $input['debugging'] ) ? $input['debugging'] : array();

		$sanitized['general']['remove_data_on_uninstall'] = $this->to_bool(
			$general['remove_data_on_uninstall'] ?? false
		);
		$sanitized['debugging']['enabled'] = $this->to_bool( $debugging['enabled'] ?? false );

		foreach ( self::MODULE_GROUPS as $group ) {
			$group_input = isset( $input[ $group ] ) && is_array( $input[ $group ] ) ? $input[ $group ] : array();
			$sanitized[ $group ]['enabled'] = $this->to_bool( $group_input['enabled'] ?? $sanitized[ $group ]['enabled'] );
		}
		$litespeed_mode = sanitize_key( (string) ( $input['litespeed']['mode'] ?? 'compatible' ) );
		$sanitized['litespeed']['mode'] = in_array( $litespeed_mode, array( 'independent', 'compatible', 'cooperative' ), true ) ? $litespeed_mode : 'compatible';
		$performance = isset( $input['performance'] ) && is_array( $input['performance'] ) ? $input['performance'] : array();
		$sanitized['performance']['sample_rate']    = max( 1, min( 100, absint( $performance['sample_rate'] ?? 10 ) ) );
		$sanitized['performance']['retention_days'] = max( 1, min( 90, absint( $performance['retention_days'] ?? 7 ) ) );
		$sanitized['performance']['max_samples']    = max( 10, min( 2000, absint( $performance['max_samples'] ?? 500 ) ) );
		$dom = isset( $input['dom'] ) && is_array( $input['dom'] ) ? $input['dom'] : array();
		$sanitized['dom']['sample_rate']    = max( 1, min( 100, absint( $dom['sample_rate'] ?? 10 ) ) );
		$sanitized['dom']['cooldown_hours'] = max( 1, min( 720, absint( $dom['cooldown_hours'] ?? 24 ) ) );
		$cache = isset( $input['cache'] ) && is_array( $input['cache'] ) ? $input['cache'] : array();
		$sanitized['cache']['ttl']       = max( 30, min( 86400, absint( $cache['ttl'] ?? 300 ) ) );
		$sanitized['cache']['stale_ttl'] = max( 0, min( 3600, (int) ( $cache['stale_ttl'] ?? 60 ) ) );
		$sanitized['cache']['vary_device']   = $this->to_bool( $cache['vary_device'] ?? false );
		$sanitized['cache']['vary_currency'] = $this->to_bool( $cache['vary_currency'] ?? false );
		$sanitized['cache']['warm_homepage'] = $this->to_bool( $cache['warm_homepage'] ?? true );
		$sanitized['cache']['allowed_query_parameters'] = $this->sanitize_keys( $cache['allowed_query_parameters'] ?? array() );
		$sanitized['cache']['variation_dimensions'] = $this->sanitize_keys( $cache['variation_dimensions'] ?? array() );
		$sanitized['cache']['important_product_ids'] = $this->sanitize_ids( $cache['important_product_ids'] ?? array() );
		$sanitized['cache']['important_category_ids'] = $this->sanitize_ids( $cache['important_category_ids'] ?? array() );
		$paths = is_array( $cache['excluded_paths'] ?? null ) ? $cache['excluded_paths'] : preg_split( '/[\r\n,]+/', (string) ( $cache['excluded_paths'] ?? '' ) );
		$sanitized['cache']['excluded_paths'] = array_values( array_unique( array_filter( array_map( static function ( $path ): string { return '/' . ltrim( sanitize_text_field( (string) $path ), '/' ); }, $paths ) ) ) );

		return $sanitized;
	}

	private function to_bool( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'yes' === $value || 'on' === $value;
	}

	private function sanitize_keys( $values ): array {
		if ( ! is_array( $values ) ) {
			$values = preg_split( '/[\s,]+/', (string) $values );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $values ) ) ) );
	}

	private function sanitize_ids( $values ): array {
		if ( ! is_array( $values ) ) {
			$values = preg_split( '/[\s,]+/', (string) $values );
		}
		return array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
	}
}
