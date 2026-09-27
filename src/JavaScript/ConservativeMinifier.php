<?php
/**
 * Semantics-preserving JavaScript whitespace normalizer.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class ConservativeMinifier {
	public function minify( string $source ): string {
		$source = preg_replace( '/^\xEF\xBB\xBF/', '', $source ) ?? $source;
		if ( false !== strpos( $source, '`' ) ) {
			return $source;
		}

		$source = str_replace( array( "\r\n", "\r" ), "\n", $source );
		$lines  = explode( "\n", $source );

		foreach ( $lines as &$line ) {
			$line = rtrim( $line );
		}
		unset( $line );

		return trim( implode( "\n", $lines ) ) . "\n";
	}
}
