<?php
/**
 * Conservative CSS selector usage analyzer.
 *
 * It reports candidates only. It never rewrites or removes stylesheet rules.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

use Nakhostin\PerformanceEngine\Contracts\CSSAnalyzerInterface;

final class CSSAnalyzer implements CSSAnalyzerInterface {
	private const DYNAMIC_PATTERN = '/(?:^|[-_:])(active|checked|current|disabled|expanded|focus|hover|loading|menu-open|modal-open|open|selected|variation|visible)(?:$|[-_:])/i';
	private const MAX_SELECTORS = 5000;
	private const MAX_SELECTOR_BYTES = 512;

	public function analyze( string $document, array $assets = array() ): array {
		$usage = isset( $assets['dom_manifest'] ) && is_array( $assets['dom_manifest'] ) ? $assets['dom_manifest'] : array();
		return $this->analyze_stylesheet( $document, $usage )->to_array();
	}

	public function supports( string $asset_type ): bool {
		return in_array( strtolower( $asset_type ), array( 'css', 'stylesheet' ), true );
	}

	public function extract_selectors( string $css ): array {
		$css       = preg_replace( '!/\*.*?\*/!s', '', $css ) ?? '';
		$selectors = array();
		if ( preg_match_all( '/(?:^|[{}])\s*([^@{}][^{}]*)\{/m', $css, $matches ) ) {
			foreach ( $matches[1] as $prelude ) {
				foreach ( $this->split_selector_list( trim( $prelude ) ) as $selector ) {
					$selector = preg_replace( '/[\x00-\x1F\x7F]/u', '', $selector ) ?? '';
					if ( '' !== $selector && strlen( $selector ) <= self::MAX_SELECTOR_BYTES ) {
						$selectors[] = $selector;
					}
					if ( count( $selectors ) >= self::MAX_SELECTORS ) {
						break 2;
					}
				}
			}
		}

		return array_values( array_unique( $selectors ) );
	}

	public function analyze_stylesheet( string $css, array $dom_manifest ): CSSManifest {
		$selectors = $this->extract_selectors( $css );
		$known     = array(
			'classes'    => array_fill_keys( $this->strings( $dom_manifest['classes'] ?? array() ), true ),
			'ids'        => array_fill_keys( $this->strings( $dom_manifest['ids'] ?? array() ), true ),
			'elements'   => array_fill_keys( $this->strings( $dom_manifest['elements'] ?? array(), true ), true ),
			'attributes' => array_fill_keys( $this->attribute_names( $dom_manifest['attributes'] ?? array() ), true ),
		);
		$groups = array( 'used' => array(), 'unused_candidates' => array(), 'preserved' => array() );

		foreach ( $selectors as $selector ) {
			$groups[ $this->classify( $selector, $known ) ][] = $selector;
		}

		return new CSSManifest(
			array(
				'manifest_version'   => 1,
				'generated_at'       => gmdate( 'c' ),
				'dom_signature'      => sanitize_text_field( (string) ( $dom_manifest['dom_signature'] ?? '' ) ),
				'source_bytes'       => strlen( $css ),
				'selector_count'     => count( $selectors ),
				'used'               => $groups['used'],
				'unused_candidates'  => $groups['unused_candidates'],
				'preserved'          => $groups['preserved'],
				'optimization_active' => false,
				'warning'             => 'Candidates require review; NPE has not changed the stylesheet.',
			)
		);
	}

	private function classify( string $selector, array $known ): string {
		if ( preg_match( '/:(?:before|after|hover|focus|focus-visible|checked|disabled|target|has|not|is|where|nth-|first-|last-)/i', $selector ) || preg_match( self::DYNAMIC_PATTERN, $selector ) ) {
			return 'preserved';
		}

		$tokens = array();
		preg_match_all( '/\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/', $selector, $classes );
		foreach ( $classes[1] as $value ) {
			$tokens[] = isset( $known['classes'][ $value ] );
		}
		preg_match_all( '/#(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)/', $selector, $ids );
		foreach ( $ids[1] as $value ) {
			$tokens[] = isset( $known['ids'][ $value ] );
		}
		preg_match_all( '/\[\s*([a-zA-Z_:][-a-zA-Z0-9_:.]*)/', $selector, $attributes );
		foreach ( $attributes[1] as $value ) {
			$tokens[] = isset( $known['attributes'][ strtolower( $value ) ] );
		}

		$without = preg_replace( '/[#.\[][^{\s>+~,:]*/', '', $selector ) ?? $selector;
		if ( preg_match_all( '/(?:^|[\s>+~])([a-zA-Z][a-zA-Z0-9-]*)/', $without, $elements ) ) {
			foreach ( $elements[1] as $value ) {
				$value = strtolower( $value );
				if ( ! in_array( $value, array( 'html', 'body' ), true ) ) {
					$tokens[] = isset( $known['elements'][ $value ] );
				}
			}
		}

		if ( empty( $tokens ) ) {
			return 'preserved';
		}
		return in_array( false, $tokens, true ) ? 'unused_candidates' : 'used';
	}

	private function split_selector_list( string $input ): array {
		$result = array();
		$buffer = '';
		$depth  = 0;
		$length = strlen( $input );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $input[ $i ];
			if ( '(' === $char || '[' === $char ) {
				$depth++;
			}
			if ( ')' === $char || ']' === $char ) {
				$depth = max( 0, $depth - 1 );
			}
			if ( ',' === $char && 0 === $depth ) {
				$result[] = trim( $buffer );
				$buffer   = '';
				continue;
			}
			$buffer .= $char;
		}
		$result[] = trim( $buffer );
		return $result;
	}

	private function strings( $values, bool $lowercase = false ): array {
		return array_values(
			array_filter(
				array_map(
					static function ( $value ) use ( $lowercase ): string {
						$value = sanitize_text_field( (string) $value );
						return $lowercase ? strtolower( $value ) : $value;
					},
					is_array( $values ) ? $values : array()
				)
			)
		);
	}

	private function attribute_names( $attributes ): array {
		$names = array();
		foreach ( is_array( $attributes ) ? $attributes : array() as $key => $value ) {
			$name = is_string( $key ) ? $key : $value;
			if ( is_array( $value ) && isset( $value['name'] ) ) {
				$name = $value['name'];
			}
			$names[] = strtolower( sanitize_key( (string) $name ) );
		}
		return array_filter( $names );
	}
}
