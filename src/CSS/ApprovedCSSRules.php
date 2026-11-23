<?php
/** Per-page approvals for pruning simple, top-level CSS rules. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

final class ApprovedCSSRules {
	public const OPTION = 'npe_approved_css_rules';

	public static function for_page( string $url ): array {
		$all = (array) get_option( self::OPTION, array() );
		return (array) ( $all[ hash( 'sha256', $url ) ] ?? array() );
	}

	public static function save( string $url, string $input ): bool {
		$values = preg_split( '/[\s,]+/', trim( $input ), -1, PREG_SPLIT_NO_EMPTY );
		if ( count( $values ) > 20 ) {
			return false;
		}
		foreach ( $values as $value ) {
			if ( ! preg_match( '/^\.[a-z_][a-z0-9_-]{0,63}$/iD', $value ) ) {
				return false;
			}
		}
		$all = (array) get_option( self::OPTION, array() );
		$key = hash( 'sha256', $url );
		unset( $all[ $key ] );
		if ( $values ) {
			$all[ $key ] = array_values( array_unique( $values ) );
		}
		return update_option( self::OPTION, array_slice( $all, -100, null, true ), false ) || self::for_page( $url ) === array_values( array_unique( $values ) );
	}

	// A deliberately narrow lexer. Nested rules, comments and quoted values
	// remain intact. Only a whole standalone class rule can be removed.
	public static function prune( string $css, array $approved ): string {
		if ( ! $approved ) {
			return $css;
		}
		$depth = 0;
		$functions = 0;
		$start = 0;
		$remove = false;
		$kept = '';
		$quote = '';
		$length = strlen( $css );
		for ( $i = 0; $i < $length; ++$i ) {
			$char = $css[ $i ];
			if ( '' !== $quote ) {
				if ( '\\' === $char ) { ++$i; }
				elseif ( $quote === $char ) { $quote = ''; }
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;
			}
			if ( '/' === $char && '*' === ( $css[ $i + 1 ] ?? '' ) ) {
				$end = strpos( $css, '*/', $i + 2 );
				if ( false === $end ) { return $css; }
				$i = $end + 1;
				continue;
			}
			if ( '(' === $char ) { ++$functions; continue; }
			if ( ')' === $char ) { if ( --$functions < 0 ) { return $css; } continue; }
			if ( $functions ) { continue; }
			if ( '{' === $char ) {
				if ( 0 === $depth ) {
					$remove = in_array( trim( substr( $css, $start, $i - $start ) ), $approved, true );
				} else {
					$remove = false;
				}
				++$depth;
			} elseif ( '}' === $char ) {
				if ( --$depth < 0 ) { return $css; }
				if ( 0 === $depth ) {
					if ( ! $remove ) { $kept .= substr( $css, $start, $i + 1 - $start ); }
					$start = $i + 1;
				}
			}
		}
		return 0 === $depth && 0 === $functions && '' === $quote ? $kept . substr( $css, $start ) : $css;
	}
}
