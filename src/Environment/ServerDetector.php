<?php
/** Capability-oriented web-server detection. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;

final class ServerDetector {
	public function detect( array $server = array() ): string {
		$software = strtolower( (string) ( $server['SERVER_SOFTWARE'] ?? '' ) );
		$signature = strtolower( (string) ( $server['SERVER_SIGNATURE'] ?? '' ) );
		$powered = strtolower( (string) ( $server['X-LSCACHE'] ?? $server['HTTP_X_LSCACHE'] ?? '' ) );
		$combined = $software . ' ' . $signature . ' ' . $powered;
		if ( defined( 'LSWS_EDITION' ) && false !== stripos( (string) LSWS_EDITION, 'open' ) ) { return 'openlitespeed'; }
		if ( false !== strpos( $combined, 'openlitespeed' ) ) { return 'openlitespeed'; }
		if ( defined( 'LITESPEED_ON' ) || defined( 'LSWS_EDITION' ) || false !== strpos( $combined, 'litespeed' ) ) { return 'litespeed'; }
		if ( false !== strpos( $combined, 'nginx' ) ) { return 'nginx'; }
		if ( function_exists( 'apache_get_version' ) || false !== strpos( $combined, 'apache' ) ) { return 'apache'; }
		return 'unknown';
	}
}
