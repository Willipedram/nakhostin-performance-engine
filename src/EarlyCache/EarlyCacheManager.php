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
		if ( $this->mode->is( CacheRuntimeMode::EXTERNAL ) ) {
			$state = $this->installer->state();
			$owner = $this->owner_label( (string) ( $state['owner'] ?? 'external' ) );
			$message = sprintf(
				/* translators: %s: detected owner of advanced-cache.php. */
				__( 'NPE did not install its Early Cache because advanced-cache.php is already owned by %s. The existing file remains unchanged.', 'nakhostin-performance-engine' ),
				$owner
			);
			$guidance = __( 'If that cache is active, no action is required. To switch to NPE Early Cache, first disable the owning cache through its own settings and cleanup tool, confirm that it removed advanced-cache.php, and then save the NPE cache settings again. Never delete the file while its owner is active.', 'nakhostin-performance-engine' );
			echo '<div class="notice notice-info"><p><strong>' . esc_html( $message ) . '</strong></p><p>' . esc_html( $guidance ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'NPE Early Cache is unavailable. Confirm that WP_CACHE is enabled and wp-content is writable; NPE is using the safe application-cache fallback.', 'nakhostin-performance-engine' ) . '</p></div>';
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
	private function owner_label( string $owner ): string {
		$labels = array(
			'litespeed'      => 'LiteSpeed Cache',
			'wp_rocket'      => 'WP Rocket',
			'w3_total_cache' => 'W3 Total Cache',
			'wp_super_cache' => 'WP Super Cache',
			'cache_enabler'  => 'Cache Enabler',
			'unknown'        => __( 'an unreadable cache drop-in', 'nakhostin-performance-engine' ),
			'external'       => __( 'an unrecognized cache system', 'nakhostin-performance-engine' ),
		);
		return (string) ( $labels[ $owner ] ?? $labels['external'] );
	}
}
