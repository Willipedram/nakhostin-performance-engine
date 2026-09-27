<?php
/** Safe logging status page. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
final class LogsAdminPage {
	public const SLUG = 'npe-logs';
	/** @var Settings */ private $settings; /** @var Capabilities */ private $capabilities;
	public function __construct( Settings $settings, Capabilities $capabilities ) { $this->settings = $settings; $this->capabilities = $capabilities; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); }
	public function add_menu(): void { add_submenu_page( AdminPage::SLUG, __( 'Logs', 'nakhostin-performance-engine' ), __( 'Logs', 'nakhostin-performance-engine' ), Capabilities::MANAGE, self::SLUG, array( $this, 'render' ) ); }
	public function render(): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view logging diagnostics.', 'nakhostin-performance-engine' ) ); } $enabled = $this->settings->get( 'debugging.enabled', false ) && defined( 'WP_DEBUG' ) && WP_DEBUG; ?><div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"><h1><?php echo esc_html__( 'Logs', 'nakhostin-performance-engine' ); ?></h1><div class="npe-card"><p><?php echo esc_html__( 'NPE logging is redacted, disabled by default, and written through the WordPress debug destination. Log contents are not exposed in the browser.', 'nakhostin-performance-engine' ); ?></p><p class="npe-status <?php echo esc_attr( $enabled ? 'is-enabled' : 'is-disabled' ); ?>"><span aria-hidden="true"><?php echo $enabled ? '●' : '○'; ?></span> <?php echo esc_html( $enabled ? __( 'Logging is active', 'nakhostin-performance-engine' ) : __( 'Logging is inactive', 'nakhostin-performance-engine' ) ); ?></p></div></div><?php }
}
