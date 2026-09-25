<?php
/** Lightweight administration page for integration and reserved module status. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
final class SectionAdminPage {
	/** @var string */ private $slug; /** @var string */ private $title; /** @var string */ private $description; /** @var string */ private $setting; /** @var Capabilities */ private $capabilities; /** @var Settings */ private $settings; /** @var FeatureFlags */ private $features;
	public function __construct( string $slug, string $title, string $description, string $setting, Settings $settings, Capabilities $capabilities, FeatureFlags $features ) { $this->slug = sanitize_key( $slug ); $this->title = $title; $this->description = $description; $this->setting = sanitize_key( $setting ); $this->settings = $settings; $this->capabilities = $capabilities; $this->features = $features; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); }
	public function add_menu(): void { add_submenu_page( AdminPage::SLUG, $this->title, $this->title, Capabilities::MANAGE, $this->slug, array( $this, 'render' ) ); }
	public function render(): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view this NPE section.', 'nakhostin-performance-engine' ) ); } $available = $this->features->is_available( $this->setting ); $enabled = $this->features->is_enabled( $this->setting ); ?><div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"><h1><?php echo esc_html( $this->title ); ?></h1><div class="npe-card"><p><?php echo esc_html( $this->description ); ?></p><p><span class="npe-status <?php echo esc_attr( $enabled ? 'is-enabled' : 'is-disabled' ); ?>"><span aria-hidden="true"><?php echo $enabled ? '●' : '○'; ?></span> <?php echo esc_html( $enabled ? __( 'Enabled', 'nakhostin-performance-engine' ) : ( $available ? __( 'Disabled', 'nakhostin-performance-engine' ) : __( 'Planned', 'nakhostin-performance-engine' ) ) ); ?></span></p><p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminPage::SETTINGS_SLUG ) ); ?>"><?php echo esc_html__( 'Open settings', 'nakhostin-performance-engine' ); ?></a></p></div></div><?php }
	public function slug(): string { return $this->slug; }
}
