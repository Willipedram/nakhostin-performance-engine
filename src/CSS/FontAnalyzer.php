<?php
/** Conservative per-page web-font discovery and requirement analyzer. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

final class FontAnalyzer {
	private const MAX_FACES = 200;
	private const FORMAT_ALLOWLIST = array( 'woff2', 'woff', 'woff2-variations', 'woff-variations', 'truetype', 'opentype' );

	public function analyze( array $files, array $dom_manifest, CSSManifest $css_manifest ): FontManifest {
		$css_data = $css_manifest->to_array();
		$active_selectors = array_fill_keys( array_merge( (array) ( $css_data['used'] ?? array() ), (array) ( $css_data['preserved'] ?? array() ) ), true );
		$required_families = array();
		$used_weights = array( '400' => true );
		foreach ( $files as $file ) {
			$this->collect_usage( (string) ( $file['css'] ?? '' ), $active_selectors, $required_families, $used_weights );
		}

		$families = array();
		$stylesheets = array();
		$required_sources = array();
		$unused_sources = array();
		$face_count = 0;
		foreach ( $files as $file ) {
			$stylesheet_url = esc_url_raw( (string) ( $file['url'] ?? '' ) );
			$css = (string) ( $file['css'] ?? '' );
			$faces = array();
			if ( preg_match_all( '/@font-face\s*\{([^{}]*)\}/is', $css, $matches ) ) {
				foreach ( $matches[1] as $block ) {
					if ( ++$face_count > self::MAX_FACES ) { break 2; }
					$face = $this->parse_face( (string) $block, $stylesheet_url );
					if ( null === $face ) { continue; }
					$key = strtolower( $face['family'] );
					$required = isset( $required_families[ $key ] ) && $this->weight_required( $face['weight'], $used_weights );
					$face['required'] = $required;
					$families[ $key ]['family'] = $face['family'];
					$families[ $key ]['required'] = isset( $required_families[ $key ] );
					$families[ $key ]['faces'][] = $face;
					foreach ( $face['sources'] as $source ) {
						if ( $required ) { $required_sources[] = $source['url']; } else { $unused_sources[] = $source['url']; }
					}
					$faces[] = $face;
				}
			}
			$without_faces = preg_replace( '/@font-face\s*\{[^{}]*\}/is', '', preg_replace( '!/\*.*?\*/!s', '', $css ) ?? '' ) ?? '';
			$without_faces = preg_replace( '/@charset\s+[\'\"][^\'\"]+[\'\"]\s*;/i', '', $without_faces ) ?? $without_faces;
			$stylesheets[] = array(
				'url' => $stylesheet_url,
				'font_only' => '' === trim( $without_faces ),
				'required' => (bool) array_filter( $faces, static function ( array $face ): bool { return $face['required']; } ),
				'faces' => $faces,
			);
		}

		return new FontManifest(
			array(
				'manifest_version' => 1,
				'generated_at' => gmdate( 'c' ),
				'source_url' => esc_url_raw( (string) ( $dom_manifest['source_url'] ?? '' ) ),
				'dom_signature' => sanitize_text_field( (string) ( $dom_manifest['dom_signature'] ?? '' ) ),
				'families' => array_values( $families ),
				'stylesheets' => $stylesheets,
				'required_sources' => array_values( array_unique( $required_sources ) ),
				'unused_sources' => array_values( array_unique( array_diff( $unused_sources, $required_sources ) ) ),
				'optimization_active' => false,
			)
		);
	}

	private function collect_usage( string $css, array $active, array &$families, array &$weights ): void {
		$css = preg_replace( '/@font-face\s*\{[^{}]*\}/is', '', $css ) ?? '';
		if ( ! preg_match_all( '/([^@{}][^{}]*)\{([^{}]*)\}/s', $css, $rules, PREG_SET_ORDER ) ) { return; }
		foreach ( $rules as $rule ) {
			$selectors = array_map( 'trim', explode( ',', trim( $rule[1] ) ) );
			$is_active = (bool) array_intersect_key( $active, array_fill_keys( $selectors, true ) );
			if ( ! $is_active ) {
				foreach ( $active as $selector => $unused ) {
					unset( $unused );
					if ( '' !== $selector && false !== strpos( $rule[1], $selector ) ) { $is_active = true; break; }
				}
			}
			if ( ! $is_active ) { continue; }
			if ( preg_match( '/font-family\s*:\s*([^;!}]+)/i', $rule[2], $match ) ) {
				foreach ( explode( ',', $match[1] ) as $family ) {
					$family = $this->clean_family( $family );
					if ( '' !== $family && ! in_array( strtolower( $family ), array( 'serif', 'sans-serif', 'monospace', 'system-ui', 'inherit' ), true ) ) { $families[ strtolower( $family ) ] = true; }
				}
			}
			if ( preg_match( '/font-weight\s*:\s*([1-9]00|normal|bold)/i', $rule[2], $match ) ) {
				$weight = strtolower( $match[1] );
				$weights[ 'normal' === $weight ? '400' : ( 'bold' === $weight ? '700' : $weight ) ] = true;
			}
		}
	}

	private function parse_face( string $block, string $stylesheet_url ): ?array {
		if ( ! preg_match( '/font-family\s*:\s*([^;!}]+)/i', $block, $family_match ) || ! preg_match_all( '/url\(\s*[\'\"]?([^\)\'\"]+)[\'\"]?\s*\)(?:\s*format\(\s*[\'\"]?([^\)\'\"]+)[\'\"]?\s*\))?/i', $block, $source_matches, PREG_SET_ORDER ) ) { return null; }
		$family = $this->clean_family( $family_match[1] );
		if ( '' === $family ) { return null; }
		$sources = array();
		foreach ( $source_matches as $source ) {
			$url = $this->absolute_url( trim( $source[1] ), $stylesheet_url );
			$format = strtolower( sanitize_key( (string) ( $source[2] ?? pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ) );
			if ( '' !== $url && in_array( $format, self::FORMAT_ALLOWLIST, true ) ) { $sources[] = array( 'url' => $url, 'format' => $format ); }
		}
		if ( ! $sources ) { return null; }
		$weight = preg_match( '/font-weight\s*:\s*([^;!}]+)/i', $block, $match ) ? sanitize_text_field( trim( $match[1] ) ) : '400';
		$style = preg_match( '/font-style\s*:\s*([^;!}]+)/i', $block, $match ) ? sanitize_key( trim( $match[1] ) ) : 'normal';
		$display = preg_match( '/font-display\s*:\s*([^;!}]+)/i', $block, $match ) ? sanitize_key( trim( $match[1] ) ) : 'swap';
		$unicode_range = preg_match( '/unicode-range\s*:\s*([^;!}]+)/i', $block, $match ) ? preg_replace( '/[^uU+0-9a-fA-F?\-, ]/', '', trim( $match[1] ) ) : '';
		return array( 'family' => $family, 'weight' => $weight, 'style' => in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ? $style : 'normal', 'display' => in_array( $display, array( 'auto', 'block', 'swap', 'fallback', 'optional' ), true ) ? $display : 'swap', 'unicode_range' => $unicode_range, 'sources' => $sources );
	}

	private function weight_required( string $weight, array $used ): bool {
		if ( false !== strpos( $weight, ' ' ) ) { return true; }
		$weight = 'normal' === strtolower( $weight ) ? '400' : ( 'bold' === strtolower( $weight ) ? '700' : preg_replace( '/[^0-9]/', '', $weight ) );
		return '400' === $weight || isset( $used[ $weight ] );
	}

	private function clean_family( string $family ): string { $family = trim( sanitize_text_field( trim( $family, " \t\n\r\0\x0B\"'" ) ) ); return preg_replace( '/[^\p{L}\p{N} _-]/u', '', $family ) ?? ''; }
	private function absolute_url( string $source, string $stylesheet ): string {
		$source = trim( $source );
		if ( wp_parse_url( $source, PHP_URL_HOST ) ) { return esc_url_raw( $source, array( 'http', 'https' ) ); }
		$base = rtrim( dirname( (string) wp_parse_url( $stylesheet, PHP_URL_PATH ) ), '/' ) . '/';
		$path = 0 === strpos( $source, '/' ) ? $source : $base . $source;
		$segments = array(); foreach ( explode( '/', $path ) as $part ) { if ( '..' === $part ) { array_pop( $segments ); } elseif ( '' !== $part && '.' !== $part ) { $segments[] = $part; } }
		$port = wp_parse_url( $stylesheet, PHP_URL_PORT );
		return esc_url_raw( (string) wp_parse_url( $stylesheet, PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( $stylesheet, PHP_URL_HOST ) . ( is_int( $port ) ? ':' . $port : '' ) . '/' . implode( '/', $segments ) );
	}
}
