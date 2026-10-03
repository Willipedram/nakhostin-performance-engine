<?php
/** Detects common reverse-proxy signals without trusting forwarded identity. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;
final class ReverseProxyDetector {
	public function detect( array $server = array() ): array {
		$provider = 'none';
		if ( ! empty( $server['HTTP_CF_RAY'] ) ) { $provider = 'cloudflare'; }
		elseif ( ! empty( $server['HTTP_X_VARNISH'] ) ) { $provider = 'varnish'; }
		elseif ( ! empty( $server['HTTP_VIA'] ) || ! empty( $server['HTTP_X_FORWARDED_FOR'] ) ) { $provider = 'generic'; }
		return array( 'detected' => 'none' !== $provider, 'provider' => $provider );
	}
}
