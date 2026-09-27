<?php
/** Synchronizes opt-in early cache configuration and owned drop-in. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
use Nakhostin\PerformanceEngine\Cache\CacheRuntimeMode;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class EarlyCacheManager {
	private $installer; private $settings; private $mode; private $cache_directory;
	public function __construct( EarlyCacheInstaller $installer, Settings $settings, CacheRuntimeMode $mode, string $cache_directory ) { $this->installer = $installer; $this->settings = $settings; $this->mode = $mode; $this->cache_directory = rtrim( $cache_directory, '/\\' ); }
	public function register(): void { add_action( 'admin_init', array( $this, 'synchronize' ) ); add_action( 'update_option_' . Settings::OPTION, array( $this, 'synchronize' ) ); add_action( 'admin_notices', array( $this, 'render_notice' ) ); }
	public function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->settings->get( 'cache.enabled', false ) || ! $this->settings->get( 'cache.early_cache', false ) || $this->mode->is( CacheRuntimeMode::NPE_EARLY ) || $this->mode->is( CacheRuntimeMode::LITESPEED ) ) { return; }
		$message = $this->mode->is( CacheRuntimeMode::EXTERNAL )
			? __( 'NPE Early Cache was not installed because advanced-cache.php is owned by another cache system. NPE will not overwrite or modify that file.', 'nakhostin-performance-engine' )
			: __( 'NPE Early Cache is unavailable. Confirm that WP_CACHE is enabled and wp-content is writable; NPE is using the safe application-cache fallback.', 'nakhostin-performance-engine' );
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}
	public function synchronize(): void {
		if ( $this->mode->is( CacheRuntimeMode::NPE_EARLY ) ) { $result = $this->installer->install(); if ( $result['success'] ) { $this->write_config(); } return; }
		$this->disable_config();
		$this->installer->remove();
	}
	private function write_config(): bool {
		if ( ! is_dir( $this->cache_directory ) && ! wp_mkdir_p( $this->cache_directory ) ) { return false; }
		$config = array( 'enabled' => true, 'cache_directory' => $this->cache_directory, 'home_host' => strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . ( wp_parse_url( home_url( '/' ), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url( '/' ), PHP_URL_PORT ) : '' ) ), 'site_id' => get_current_blog_id(), 'language' => determine_locale(), 'allowed_query_parameters' => $this->settings->get( 'cache.allowed_query_parameters', array() ), 'excluded_paths' => $this->settings->get( 'cache.excluded_paths', array() ) );
		$temp = tempnam( $this->cache_directory, '.npe-config-' ); $json = wp_json_encode( $config, JSON_UNESCAPED_SLASHES ); $ok = false !== $temp && false !== $json && false !== file_put_contents( $temp, $json, LOCK_EX ) && rename( $temp, $this->cache_directory . '/early-config.json' ); if ( false !== $temp && file_exists( $temp ) ) { unlink( $temp ); } return $ok;
	}
	private function disable_config(): void { $file = $this->cache_directory . '/early-config.json'; if ( is_file( $file ) ) { unlink( $file ); } }
}
