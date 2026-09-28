<?php
/** Ensures generated @font-face rules do not block text rendering. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

final class FontDisplayOptimizer {
	private $strategy;
	public function __construct( string $strategy = 'swap' ) { $strategy = sanitize_key( $strategy ); $this->strategy = in_array( $strategy, array( 'swap', 'fallback', 'optional' ), true ) ? $strategy : 'swap'; }
	public function rewrite( string $css ): string {
		return preg_replace_callback( '/@font-face\s*\{([^{}]*)\}/is', function ( array $match ): string {
			$body = trim( (string) $match[1] );
			if ( preg_match( '/font-display\s*:\s*([^;!}]+)/i', $body, $display ) ) {
				$current = sanitize_key( trim( $display[1] ) );
				if ( in_array( $current, array( 'swap', 'fallback', 'optional' ), true ) ) { return $match[0]; }
				$body = preg_replace( '/font-display\s*:\s*[^;}]+;?/i', 'font-display:' . $this->strategy . ';', $body ) ?? $body;
			} else {
				$body = rtrim( $body, "; \t\n\r" ) . ';font-display:' . $this->strategy . ';';
			}
			return '@font-face{' . $body . '}';
		}, $css ) ?? $css;
	}
}
