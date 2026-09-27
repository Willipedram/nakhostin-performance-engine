<?php
/**
 * Detects LiteSpeed Cache without loading or calling its internal classes.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;

final class LiteSpeedDetector {
	public const PLUGIN_BASENAME = 'litespeed-cache/litespeed-cache.php';

	/** @var string */
	private $plugin_file;

	public function __construct( string $plugin_file = '' ) {
		if ( '' === $plugin_file && defined( 'WP_PLUGIN_DIR' ) ) {
			$plugin_file = trailingslashit( WP_PLUGIN_DIR ) . self::PLUGIN_BASENAME;
		}
		$this->plugin_file = $plugin_file;
	}

	public function detect(): array {
		$active_plugins  = get_option( 'active_plugins', array() );
		$network_plugins = is_multisite() ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$active          = defined( 'LSCWP_V' )
			|| has_action( 'litespeed_init' )
			|| in_array( self::PLUGIN_BASENAME, is_array( $active_plugins ) ? $active_plugins : array(), true )
			|| isset( $network_plugins[ self::PLUGIN_BASENAME ] );
		$installed       = $active || ( '' !== $this->plugin_file && is_readable( $this->plugin_file ) );
		$server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
		$litespeed_server = false !== stripos( $server_software, 'litespeed' )
			|| defined( 'LITESPEED_ON' )
			|| defined( 'LSWS_EDITION' );

		/**
		 * Filters whether this request can be served by a LiteSpeed page cache.
		 *
		 * This supports documented external deployments such as a configured
		 * LiteSpeed/QUIC.cloud layer which cannot be inferred from PHP alone.
		 *
		 * @param bool  $capable Whether public page caching appears available.
		 * @param array $context Detection context.
		 */
		$capable = apply_filters(
			'npe/litespeed/cache_capable',
			$active && $litespeed_server,
			array( 'active' => $active, 'installed' => $installed, 'server' => $litespeed_server )
		);

		return array(
			'installed'     => $installed,
			'active'        => $active,
			'server'        => $litespeed_server,
			'cache_capable' => $active && (bool) $capable,
			'version'       => defined( 'LSCWP_V' ) ? (string) LSCWP_V : '',
		);
	}
}
