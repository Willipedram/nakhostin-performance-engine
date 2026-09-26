<?php
/**
 * Minimal WordPress stubs used only for bootstrap integration testing.
 *
 * @package NakhostinPerformanceEngine
 */

namespace {
	defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
	defined( 'HOUR_IN_SECONDS' ) || define( 'HOUR_IN_SECONDS', 3600 );
	defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
	$GLOBALS['npe_test_actions']            = array();
	$GLOBALS['npe_test_filters']            = array();
	$GLOBALS['npe_test_fired_actions']      = array();
	$GLOBALS['npe_test_activation_hooks']   = array();
	$GLOBALS['npe_test_deactivation_hooks'] = array();
	$GLOBALS['npe_test_options']            = array();
	$GLOBALS['npe_test_can_manage']         = true;
	$GLOBALS['npe_test_is_rtl']             = false;
	$GLOBALS['npe_test_is_multisite']       = false;
	$GLOBALS['npe_test_is_ssl']             = true;
	$GLOBALS['npe_test_external_cache']     = false;
	$GLOBALS['npe_test_cleared_hooks']      = array();
	$GLOBALS['npe_test_script_data']        = array();
	$GLOBALS['npe_test_object_cache']       = array();
	$GLOBALS['npe_test_post_types']         = array();
	$GLOBALS['npe_test_post_terms']         = array();
	$GLOBALS['npe_test_queried_id']         = 0;
	$GLOBALS['npe_test_conditionals']       = array();
	$GLOBALS['npe_test_admin_pages']        = array();
	$GLOBALS['npe_test_styles']             = array();
	$GLOBALS['npe_test_transients']         = array();

	function plugin_dir_path( string $file ): string {
		return trailingslashit( dirname( $file ) );
	}

	function plugin_dir_url( string $file ): string {
		return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}

	function plugin_basename( string $file ): string {
		return basename( $file );
	}

	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}

	function register_activation_hook( string $file, $callback ): void {
		$GLOBALS['npe_test_activation_hooks'][ $file ] = $callback;
	}

	function register_deactivation_hook( string $file, $callback ): void {
		$GLOBALS['npe_test_deactivation_hooks'][ $file ] = $callback;
	}

	function add_action( string $hook, $callback ): void {
		$GLOBALS['npe_test_actions'][ $hook ][] = $callback;
	}

	function add_filter( string $hook, $callback ): void {
		$GLOBALS['npe_test_filters'][ $hook ][] = $callback;
	}

	function add_menu_page( string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = null, string $icon_url = '', $position = null ): string {
		$GLOBALS['npe_test_admin_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
		return 'toplevel_page_' . $menu_slug;
	}

	function add_submenu_page( string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, $callback = null ): string {
		$GLOBALS['npe_test_admin_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' );
		return $parent_slug . '_page_' . $menu_slug;
	}

	function wp_enqueue_style( string $handle, string $source = '', array $dependencies = array(), $version = false ): void {
		$GLOBALS['npe_test_styles'][ $handle ] = compact( 'source', 'dependencies', 'version' );
	}

	function load_plugin_textdomain( string $domain, bool $deprecated = false, string $path = '' ): bool {
		return true;
	}

	function do_action( string $hook_name, ...$args ): void {
		$GLOBALS['npe_test_fired_actions'][ $hook_name ][] = $args;
		foreach ( $GLOBALS['npe_test_actions'][ $hook_name ] ?? array() as $callback ) {
			$callback( ...$args );
		}
	}

	function apply_filters( string $hook_name, $value ) {
		$args = func_get_args();
		array_shift( $args );
		foreach ( $GLOBALS['npe_test_filters'][ $hook_name ] ?? array() as $callback ) {
			$args[0] = $callback( ...$args );
		}
		$value = $args[0];
		return $value;
	}

	function get_option( string $name, $default = false ) {
		return $GLOBALS['npe_test_options'][ $name ] ?? $default;
	}

	function get_site_option( string $name, $default = false ) {
		return get_option( $name, $default );
	}

	function update_option( string $name, $value, $autoload = null ): bool {
		$GLOBALS['npe_test_options'][ $name ] = $value;
		return true;
	}

	function add_option( string $name, $value = '', string $deprecated = '', $autoload = 'yes' ): bool {
		if ( array_key_exists( $name, $GLOBALS['npe_test_options'] ) ) {
			return false;
		}
		$GLOBALS['npe_test_options'][ $name ] = $value;
		return true;
	}

	function delete_option( string $name ): bool {
		unset( $GLOBALS['npe_test_options'][ $name ] );
		return true;
	}

	function current_user_can( string $capability ): bool {
		return $GLOBALS['npe_test_can_manage'];
	}

	function is_multisite(): bool {
		return $GLOBALS['npe_test_is_multisite'];
	}

	function is_admin(): bool {
		return false;
	}

	function is_ssl(): bool {
		return $GLOBALS['npe_test_is_ssl'];
	}

	function wp_using_ext_object_cache(): bool {
		return $GLOBALS['npe_test_external_cache'];
	}

	function wp_cache_get( string $key, string $group = '', bool $force = false, &$found = null ) {
		$cache_key = $group . ':' . $key;
		$found     = array_key_exists( $cache_key, $GLOBALS['npe_test_object_cache'] );
		return $found ? $GLOBALS['npe_test_object_cache'][ $cache_key ] : false;
	}

	function wp_cache_set( string $key, $value, string $group = '', int $expire = 0 ): bool {
		$GLOBALS['npe_test_object_cache'][ $group . ':' . $key ] = $value;
		return true;
	}

	function wp_cache_add( string $key, $value, string $group = '', int $expire = 0 ): bool {
		$cache_key = $group . ':' . $key;
		if ( array_key_exists( $cache_key, $GLOBALS['npe_test_object_cache'] ) ) {
			return false;
		}
		$GLOBALS['npe_test_object_cache'][ $cache_key ] = $value;
		return true;
	}

	function wp_cache_delete( string $key, string $group = '' ): bool {
		$cache_key = $group . ':' . $key;
		$existed   = array_key_exists( $cache_key, $GLOBALS['npe_test_object_cache'] );
		unset( $GLOBALS['npe_test_object_cache'][ $cache_key ] );
		return $existed;
	}

	function wp_cache_incr( string $key, int $offset = 1, string $group = '' ) {
		$cache_key = $group . ':' . $key;
		if ( ! isset( $GLOBALS['npe_test_object_cache'][ $cache_key ] ) ) {
			return false;
		}
		$GLOBALS['npe_test_object_cache'][ $cache_key ] += $offset;
		return $GLOBALS['npe_test_object_cache'][ $cache_key ];
	}

	function get_bloginfo( string $show ): string {
		return '6.6-test';
	}

	function wp_get_theme() {
		return new class() {
			public function get( string $header ): string {
				return '1.0.0';
			}
			public function get_template(): string {
				return 'test-theme';
			}
			public function get_stylesheet(): string {
				return 'test-theme';
			}
		};
	}

	function has_action( string $hook_name ): bool {
		return ! empty( $GLOBALS['npe_test_actions'][ $hook_name ] );
	}

	function wp_unslash( $value ) {
		return $value;
	}

	function wp_parse_url( string $url, int $component = -1 ) {
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}

	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}

	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
	}

	function absint( $value ): int {
		return abs( (int) $value );
	}

	function get_current_blog_id(): int {
		return 1;
	}

	function determine_locale(): string {
		return 'en_US';
	}

	function is_user_logged_in(): bool {
		return false;
	}

	function wp_doing_ajax(): bool {
		return false;
	}

	function wp_doing_cron(): bool {
		return false;
	}

	function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ) {
		$GLOBALS['npe_test_scheduled_event'] = compact( 'timestamp', 'hook', 'args' );
		return true;
	}

	function wp_next_scheduled( string $hook ) {
		return false;
	}

	function wp_safe_remote_get( string $url, array $args = array() ): array {
		return $GLOBALS['npe_test_remote_response'] ?? array( 'response' => array( 'code' => 200 ), 'body' => '' );
	}

	function wp_remote_retrieve_body( $response ): string {
		return (string) ( $response['body'] ?? '' );
	}

	function wp_remote_retrieve_response_code( $response ): int {
		return (int) ( $response['response']['code'] ?? 0 );
	}

	function is_wp_error( $thing ): bool {
		return $thing instanceof \WP_Error;
	}

	function set_transient( string $key, $value, int $expiration = 0 ): bool {
		$GLOBALS['npe_test_transients'][ $key ] = $value;
		return true;
	}

	function get_transient( string $key ) {
		return $GLOBALS['npe_test_transients'][ $key ] ?? false;
	}

	function delete_transient( string $key ): bool {
		unset( $GLOBALS['npe_test_transients'][ $key ] );
		return true;
	}

	function nocache_headers(): void {}

	function wp_generate_uuid4(): string {
		static $uuid = 0;
		++$uuid;
		return sprintf( '00000000-0000-4000-8000-%012d', $uuid );
	}

	function get_post_type( int $post_id ): string {
		return $GLOBALS['npe_test_post_types'][ $post_id ] ?? 'post';
	}

	function get_permalink( int $post_id ): string {
		return 'https://example.test/post/' . $post_id . '/';
	}

	function get_post_type_archive_link( string $post_type ): string {
		return 'https://example.test/' . $post_type . '/';
	}

	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
		return $GLOBALS['npe_test_post_terms'][ $post_id ][ $taxonomy ] ?? array();
	}

	function get_term_link( int $term_id, string $taxonomy = '' ): string {
		return 'https://example.test/' . $taxonomy . '/' . $term_id . '/';
	}

	function wp_is_post_revision( int $post_id ): bool {
		return false;
	}

	function wp_is_post_autosave( int $post_id ): bool {
		return false;
	}

	function get_queried_object_id(): int {
		return (int) $GLOBALS['npe_test_queried_id'];
	}

	function is_singular(): bool {
		return ! empty( $GLOBALS['npe_test_conditionals']['singular'] );
	}

	function is_front_page(): bool {
		return ! empty( $GLOBALS['npe_test_conditionals']['front_page'] );
	}

	function is_shop(): bool {
		return ! empty( $GLOBALS['npe_test_conditionals']['shop'] );
	}

	function is_product_category(): bool {
		return ! empty( $GLOBALS['npe_test_conditionals']['product_category'] );
	}

	function is_product_tag(): bool {
		return ! empty( $GLOBALS['npe_test_conditionals']['product_tag'] );
	}

	function number_format_i18n( $number ): string {
		return number_format( (float) $number );
	}

	function size_format( int $bytes ): string {
		return $bytes . ' B';
	}

	function check_admin_referer( string $action ): bool {
		return true;
	}

	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args );
	}

	function wp_safe_redirect( string $url ): bool {
		$GLOBALS['npe_test_redirect'] = $url;
		return true;
	}

	function wp_json_encode( $value ): string {
		return (string) json_encode( $value );
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}

	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( __( $text, $domain ) );
	}

	function esc_html( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	function esc_attr( $text ): string {
		return esc_html( $text );
	}

	function esc_url( $url ): string {
		return esc_attr( $url );
	}

	function esc_url_raw( $url, ?array $protocols = null ): string {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}

	function checked( $checked, $current = true, bool $display = true ): string {
		$result = $checked == $current ? 'checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}

	function is_rtl(): bool {
		return $GLOBALS['npe_test_is_rtl'];
	}

	function settings_fields( string $group ): void {
		echo '<input type="hidden" name="_wpnonce" value="test-nonce">';
	}

	function submit_button( string $text = 'Save' ): void {
		echo '<button type="submit">' . esc_html( $text ) . '</button>';
	}

	function wp_nonce_field( string $action ): void {
		echo '<input type="hidden" name="_wpnonce" value="dom-test-nonce">';
	}

	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function home_url( string $path = '' ): string {
		return 'https://example.test/' . ltrim( $path, '/' );
	}

	function wp_die( $message ): void {
		throw new \RuntimeException( (string) $message );
	}

	function wp_clear_scheduled_hook( string $hook ): void {
		$GLOBALS['npe_test_cleared_hooks'][] = $hook;
	}

	function wp_mkdir_p( string $path ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test-only WordPress stub.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Mirrors race-safe WordPress directory creation in tests.
		return is_dir( $path ) || @mkdir( $path, 0777, true ) || is_dir( $path );
	}

	function wp_generate_password(
		int $length = 12,
		bool $special_chars = true,
		bool $extra_special_chars = false
	): string {
		return substr( str_repeat( 'abcdefgh', $length ), 0, $length );
	}

	function wp_script_add_data( string $handle, string $key, $value ): bool {
		$GLOBALS['npe_test_script_data'][ $handle ][ $key ] = $value;
		return true;
	}
}
