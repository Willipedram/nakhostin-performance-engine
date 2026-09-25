<?php
/**
 * Plugin Name:       Nakhostin Performance Engine
 * Plugin URI:        https://github.com/nakhostin/nakhostin-performance-engine
 * Description:       A modular performance foundation for WordPress and WooCommerce.
 * Version:           1.2.1
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Seyed Pedram Nakhostin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nakhostin-performance-engine
 * Domain Path:       /languages
 *
 * @package NakhostinPerformanceEngine
 */

defined( 'ABSPATH' ) || exit;

define( 'NPE_BOOTSTRAP_START', microtime( true ) );

define( 'NPE_VERSION', '1.2.1' );
define( 'NPE_FILE', __FILE__ );
define( 'NPE_PATH', plugin_dir_path( __FILE__ ) );
define( 'NPE_URL', plugin_dir_url( __FILE__ ) );
define( 'NPE_BASENAME', plugin_basename( __FILE__ ) );

$npe_autoloader = NPE_PATH . 'vendor/autoload.php';

if ( is_readable( $npe_autoloader ) ) {
	require_once $npe_autoloader;
} else {
	// Keep source installations operable before Composer dependencies are installed.
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'Nakhostin\\PerformanceEngine\\';

			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}

			$relative_class = substr( $class_name, strlen( $prefix ) );
			$file           = NPE_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

if ( ! class_exists( \Nakhostin\PerformanceEngine\Core\Plugin::class ) ) {
	return;
}

register_activation_hook( NPE_FILE, array( \Nakhostin\PerformanceEngine\Core\Activator::class, 'activate' ) );
register_deactivation_hook( NPE_FILE, array( \Nakhostin\PerformanceEngine\Core\Deactivator::class, 'deactivate' ) );

\Nakhostin\PerformanceEngine\Core\Plugin::instance()->register();

define( 'NPE_BOOTSTRAP_END', microtime( true ) );
