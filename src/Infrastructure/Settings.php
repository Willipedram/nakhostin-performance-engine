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
			'batch_size'     => 3,
			'scan_interval'  => 10,
		);
		$defaults['fonts'] = array(
			'enabled' => false,
			'preload_enabled' => false,
		);
		$defaults['optimization'] = array( 'enabled' => false, 'safe_mode' => true );
		$defaults['assets'] = array( 'unload_enabled' => false );
		$defaults['css']['critical_css_enabled'] = false;
		$defaults['css']['unused_css_enabled'] = false;
		$defaults['javascript']['defer_enabled'] = false;
		$defaults['javascript']['delay_enabled'] = false;
		$defaults['cache'] = array(
			'enabled'                  => false,
			'early_cache'              => false,
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
			'hook_profiling' => false,
			'sample_rate'    => 10,
			'retention_days' => 7,
			'max_samples'    => 500,
		);
		$defaults['security'] = array( 'csp_mode' => 'disabled', 'hsts_enabled' => false, 'hsts_include_subdomains' => false, 'hsts_preload' => false );

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
		$sanitized['performance']['hook_profiling'] = $this->to_bool( $performance['hook_profiling'] ?? false );
		$sanitized['performance']['sample_rate']    = max( 1, min( 100, absint( $performance['sample_rate'] ?? 10 ) ) );
		$sanitized['performance']['retention_days'] = max( 1, min( 90, absint( $performance['retention_days'] ?? 7 ) ) );
		$sanitized['performance']['max_samples']    = max( 10, min( 2000, absint( $performance['max_samples'] ?? 500 ) ) );
		$dom = isset( $input['dom'] ) && is_array( $input['dom'] ) ? $input['dom'] : array();
		$sanitized['dom']['sample_rate']    = max( 1, min( 100, absint( $dom['sample_rate'] ?? 10 ) ) );
		$sanitized['dom']['cooldown_hours'] = max( 1, min( 720, absint( $dom['cooldown_hours'] ?? 24 ) ) );
		$sanitized['dom']['batch_size']     = max( 1, min( 5, absint( $dom['batch_size'] ?? 3 ) ) );
		$sanitized['dom']['scan_interval']  = max( 5, min( 300, absint( $dom['scan_interval'] ?? 10 ) ) );
		$fonts = isset( $input['fonts'] ) && is_array( $input['fonts'] ) ? $input['fonts'] : array();
		$sanitized['fonts']['enabled'] = $this->to_bool( $fonts['enabled'] ?? false );
		$sanitized['fonts']['preload_enabled'] = $this->to_bool( $fonts['preload_enabled'] ?? false );
		$optimization = isset( $input['optimization'] ) && is_array( $input['optimization'] ) ? $input['optimization'] : array();
		$sanitized['optimization']['enabled'] = $this->to_bool( $optimization['enabled'] ?? false );
		$sanitized['optimization']['safe_mode'] = $this->to_bool( $optimization['safe_mode'] ?? true );
		$assets = isset( $input['assets'] ) && is_array( $input['assets'] ) ? $input['assets'] : array();
		$sanitized['assets']['unload_enabled'] = $this->to_bool( $assets['unload_enabled'] ?? false );
		$sanitized['css']['critical_css_enabled'] = $this->to_bool( $input['css']['critical_css_enabled'] ?? false );
		$sanitized['css']['unused_css_enabled'] = $this->to_bool( $input['css']['unused_css_enabled'] ?? false );
		$sanitized['javascript']['defer_enabled'] = $this->to_bool( $input['javascript']['defer_enabled'] ?? false );
		$sanitized['javascript']['delay_enabled'] = $this->to_bool( $input['javascript']['delay_enabled'] ?? false );
		$cache = isset( $input['cache'] ) && is_array( $input['cache'] ) ? $input['cache'] : array();
		$sanitized['cache']['ttl']       = max( 30, min( 86400, absint( $cache['ttl'] ?? 300 ) ) );
		$sanitized['cache']['stale_ttl'] = max( 0, min( 3600, (int) ( $cache['stale_ttl'] ?? 60 ) ) );
		$sanitized['cache']['early_cache'] = $this->to_bool( $cache['early_cache'] ?? false );
		$sanitized['cache']['vary_device']   = $this->to_bool( $cache['vary_device'] ?? false );
		$sanitized['cache']['vary_currency'] = $this->to_bool( $cache['vary_currency'] ?? false );
		$sanitized['cache']['warm_homepage'] = $this->to_bool( $cache['warm_homepage'] ?? true );
		$sanitized['cache']['allowed_query_parameters'] = $this->sanitize_keys( $cache['allowed_query_parameters'] ?? array() );
		$sanitized['cache']['variation_dimensions'] = $this->sanitize_keys( $cache['variation_dimensions'] ?? array() );
		$sanitized['cache']['important_product_ids'] = $this->sanitize_ids( $cache['important_product_ids'] ?? array() );
		$sanitized['cache']['important_category_ids'] = $this->sanitize_ids( $cache['important_category_ids'] ?? array() );
		$paths = is_array( $cache['excluded_paths'] ?? null ) ? $cache['excluded_paths'] : preg_split( '/[\r\n,]+/', (string) ( $cache['excluded_paths'] ?? '' ) );
		$sanitized['cache']['excluded_paths'] = array_values( array_unique( array_filter( array_map( static function ( $path ): string { return '/' . ltrim( sanitize_text_field( (string) $path ), '/' ); }, $paths ) ) ) );

		$security = isset( $input['security'] ) && is_array( $input['security'] ) ? $input['security'] : array();
		$csp_mode = sanitize_key( (string) ( $security['csp_mode'] ?? 'disabled' ) );
		$sanitized['security']['csp_mode'] = in_array( $csp_mode, array( 'disabled', 'report_only', 'enforce' ), true ) ? $csp_mode : 'disabled';
		$sanitized['security']['hsts_enabled'] = $this->to_bool( $security['hsts_enabled'] ?? false );
		$sanitized['security']['hsts_include_subdomains'] = $this->to_bool( $security['hsts_include_subdomains'] ?? false );
		$sanitized['security']['hsts_preload'] = $this->to_bool( $security['hsts_preload'] ?? false );

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
