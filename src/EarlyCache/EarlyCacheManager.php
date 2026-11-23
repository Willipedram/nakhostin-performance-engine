<?php
/** Synchronizes opt-in early cache configuration and owned drop-in. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
use Nakhostin\PerformanceEngine\Cache\CacheRuntimeMode;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class EarlyCacheManager {
	private $installer; private $settings; private $mode; private $cache_directory;
	/** @var string|null */ private $setup_status;
	public function __construct( EarlyCacheInstaller $installer, Settings $settings, CacheRuntimeMode $mode, string $cache_directory ) { $this->installer = $installer; $this->settings = $settings; $this->mode = $mode; $this->cache_directory = rtrim( $cache_directory, '/\\' ); }
	public function register(): void { add_action( 'admin_init', array( $this, 'synchronize' ) ); add_action( 'update_option_' . Settings::OPTION, array( $this, 'synchronize' ) ); add_action( 'admin_notices', array( $this, 'render_notice' ) ); }
	public function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! $this->requested() || $this->mode->is( CacheRuntimeMode::LITESPEED ) ) { return; }
		if ( $this->mode->is( CacheRuntimeMode::EXTERNAL ) ) {
			$state = $this->installer->state();
			$owner = $this->owner_label( (string) ( $state['owner'] ?? 'external' ) );
			$message = sprintf(
				/* translators: %s: detected owner of advanced-cache.php. */
				__( 'NPE did not install its Early Cache because advanced-cache.php is already owned by %s. The existing file remains unchanged.', 'nakhostin-performance-engine' ),
				$owner
			);
			$guidance = __( 'If that cache is active, no action is required. To switch to NPE Early Cache, first disable the owning cache through its own settings and cleanup tool, confirm that it removed advanced-cache.php, and then save the NPE cache settings again. Never delete the file while its owner is active.', 'nakhostin-performance-engine' );
			$fingerprint = sanitize_key( (string) ( $state['fingerprint'] ?? '' ) );
			$diagnostic = '' === $fingerprint ? '' : sprintf(
				/* translators: %s: non-sensitive short SHA-256 fingerprint. */
				__( 'Drop-in diagnostic ID: %s', 'nakhostin-performance-engine' ),
				$fingerprint
			);
			echo '<div class="notice notice-info"><p><strong>' . esc_html( $message ) . '</strong></p><p>' . esc_html( $guidance ) . '</p>' . ( '' !== $diagnostic ? '<p><code>' . esc_html( $diagnostic ) . '</code></p>' : '' ) . '</div>';
			return;
		}
		if ( 'restart' === $this->setup_status ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'NPE enabled WP_CACHE. Early Cache setup will continue on the next admin request.', 'nakhostin-performance-engine' ) . '</p></div>';
			return;
		}
		if ( $this->mode->is( CacheRuntimeMode::NPE_EARLY ) && null === $this->setup_status ) {
			return;
		}
		$messages = array(
			'config-multisite'   => __( 'This is a WordPress multisite installation. Ask the network administrator to enable WP_CACHE in the shared configuration.', 'nakhostin-performance-engine' ),
			'config-policy'      => __( 'WordPress or your host blocks automatic file changes. Enable WP_CACHE in the hosting configuration.', 'nakhostin-performance-engine' ),
			'config-location'    => __( 'NPE could not locate a regular wp-config.php file in the standard locations. Ask your host to enable WP_CACHE.', 'nakhostin-performance-engine' ),
			'config-permissions' => __( 'PHP cannot write to wp-config.php or its parent directory. Ask your host to enable WP_CACHE.', 'nakhostin-performance-engine' ),
			'config-format'      => __( 'NPE found a custom or ambiguous wp-config.php and left it untouched. Ask your host to enable WP_CACHE in the existing configuration.', 'nakhostin-performance-engine' ),
			'config-write'       => __( 'NPE could not safely replace wp-config.php while preserving its ownership and permissions. Ask your host to enable WP_CACHE.', 'nakhostin-performance-engine' ),
		);
		$message = $messages[ $this->setup_status ] ?? __( 'NPE could not prepare Early Cache. Check that PHP can write to wp-content and the NPE cache directory. Application cache is available as a fallback.', 'nakhostin-performance-engine' );
		echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
	}
	public function synchronize(): void {
		if ( $this->requested() && $this->mode->is( CacheRuntimeMode::NPE_EARLY ) ) {
			$result = $this->installer->install();
			$this->setup_status = $result['success'] && $this->write_config() ? null : 'filesystem';
			return;
		}
		if ( $this->requested() && $this->mode->is( CacheRuntimeMode::NPE_APPLICATION ) ) {
			if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
				$this->setup_status = 'filesystem';
				return;
			}
			if ( ! current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
				return;
			}
			if ( ! $this->installer->can_install() ) {
				$this->setup_status = 'filesystem';
				return;
			}
			$this->setup_status = $this->enable_wp_cache();
			return;
		}
		$this->disable_config();
		$this->installer->remove();
	}
	private function requested(): bool {
		return $this->settings->get( 'cache.enabled', false ) && $this->settings->get( 'cache.early_cache', false );
	}
	private function enable_wp_cache(): string {
		if ( is_multisite() ) {
			return 'config-multisite';
		}
		if ( function_exists( 'wp_is_file_mod_allowed' ) && ! wp_is_file_mod_allowed( 'npe_early_cache' ) ) {
			return 'config-policy';
		}
		$root = rtrim( ABSPATH, '/\\' );
		$path = $root . '/wp-config.php';
		$parent = dirname( $root );
		if ( ! file_exists( $path ) && ! is_file( $parent . '/wp-settings.php' ) ) {
			$path = $parent . '/wp-config.php';
		}
		$configuration = new WpCacheConfiguration();
		return $configuration->enable( $path ) ? 'restart' : $configuration->failure();
	}
	private function write_config(): bool {
		if ( ! is_dir( $this->cache_directory ) && ! wp_mkdir_p( $this->cache_directory ) ) { return false; }
		$config = array( 'enabled' => true, 'cache_directory' => $this->cache_directory, 'home_host' => strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . ( wp_parse_url( home_url( '/' ), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url( '/' ), PHP_URL_PORT ) : '' ) ), 'site_id' => get_current_blog_id(), 'language' => determine_locale(), 'allowed_query_parameters' => $this->settings->get( 'cache.allowed_query_parameters', array() ), 'excluded_paths' => $this->settings->get( 'cache.excluded_paths', array() ) );
		$path = $this->cache_directory . '/early-config.json';
		$json = wp_json_encode( $config, JSON_UNESCAPED_SLASHES );
		if ( false === $json ) { return false; }
		if ( is_readable( $path ) && file_get_contents( $path ) === $json ) { return true; }
		$temp = tempnam( $this->cache_directory, '.npe-config-' );
		$ok = false !== $temp && file_put_contents( $temp, $json, LOCK_EX ) === strlen( $json ) && rename( $temp, $path );
		if ( false !== $temp && file_exists( $temp ) ) { unlink( $temp ); }
		return $ok;
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
