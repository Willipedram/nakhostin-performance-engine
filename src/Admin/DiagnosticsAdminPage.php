<?php
/** Read-only diagnostics administration screen. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
final class DiagnosticsAdminPage {
	public const SLUG = 'npe-diagnostics';
	/** @var Diagnostics */ private $diagnostics; /** @var Capabilities */ private $capabilities;
	public function __construct( Diagnostics $diagnostics, Capabilities $capabilities ) { $this->diagnostics = $diagnostics; $this->capabilities = $capabilities; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); }
	public function add_menu(): void { add_submenu_page( AdminPage::SLUG, __( 'Diagnostics', 'nakhostin-performance-engine' ), __( 'Diagnostics', 'nakhostin-performance-engine' ), Capabilities::MANAGE, self::SLUG, array( $this, 'render' ) ); }
	public function render(): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view diagnostics.', 'nakhostin-performance-engine' ) ); } $labels = $this->labels(); ?><div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>"><h1><?php echo esc_html__( 'Diagnostics', 'nakhostin-performance-engine' ); ?></h1><p><?php echo esc_html__( 'Read-only environment information. No configuration is changed from this screen.', 'nakhostin-performance-engine' ); ?></p><table class="widefat striped"><tbody><?php foreach ( $this->diagnostics->collect() as $key => $value ) : ?><tr><th scope="row"><?php echo esc_html( $labels[ $key ] ?? __( 'Unknown', 'nakhostin-performance-engine' ) ); ?></th><td class="npe-technical" dir="ltr"><?php echo esc_html( is_bool( $value ) ? ( $value ? __( 'Yes', 'nakhostin-performance-engine' ) : __( 'No', 'nakhostin-performance-engine' ) ) : (string) $value ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php }
	private function labels(): array { return array( 'wordpress_version' => __( 'WordPress version', 'nakhostin-performance-engine' ), 'php_version' => __( 'PHP version', 'nakhostin-performance-engine' ), 'server_software' => __( 'Server software', 'nakhostin-performance-engine' ), 'https' => __( 'HTTPS', 'nakhostin-performance-engine' ), 'multisite' => __( 'Multisite', 'nakhostin-performance-engine' ), 'woocommerce' => __( 'WooCommerce detected', 'nakhostin-performance-engine' ), 'elementor' => __( 'Elementor detected', 'nakhostin-performance-engine' ), 'litespeed_cache' => __( 'LiteSpeed Cache detected', 'nakhostin-performance-engine' ), 'object_cache' => __( 'External object cache', 'nakhostin-performance-engine' ), 'redis' => __( 'Redis detected', 'nakhostin-performance-engine' ) ); }
}
