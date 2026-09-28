<?php
/** Builds content-addressed, page-specific CSS bundles during background analysis. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

use RuntimeException;

final class CSSBundleWriter {
	/** @var string */ private $directory;
	/** @var string */ private $base_url;
	/** @var FontDisplayOptimizer */ private $font_display;
	public function __construct( string $directory, string $base_url, ?FontDisplayOptimizer $font_display = null ) { $this->directory = rtrim( $directory, '/\\' ); $this->base_url = trailingslashit( $base_url ); $this->font_display = $font_display ?: new FontDisplayOptimizer(); }

	public function build( array $files, CSSManifest $usage ): array {
		$data = $usage->to_array();
		$keep = array_fill_keys( array_merge( (array) ( $data['used'] ?? array() ), (array) ( $data['preserved'] ?? array() ) ), true );
		$parts = array(); $urls = array(); $original = 0;
		foreach ( $files as $file ) {
			if ( ! is_array( $file ) || empty( $file['url'] ) || ! isset( $file['css'] ) ) { continue; }
			$css = (string) $file['css']; $original += strlen( $css );
			$filtered = $this->filter_rules( $this->rewrite_urls( $css, (string) $file['url'] ), $keep );
			if ( '' !== trim( $filtered ) ) { $parts[] = '/* NPE source */' . "\n" . $filtered; $urls[] = esc_url_raw( (string) $file['url'] ); }
		}
		if ( ! $parts ) { throw new RuntimeException( 'No safe CSS source was available for bundling.' ); }
		$content = $this->font_display->rewrite( implode( "\n", $parts ) ); $hash = substr( hash( 'sha256', $content ), 0, 20 ); $filename = 'page-' . $hash . '.css';
		$this->publish( $filename, $content );
		return array( 'url' => $this->base_url . $filename, 'filename' => $filename, 'content_hash' => $hash, 'source_urls' => $urls, 'original_size' => $original, 'optimized_size' => strlen( $content ) );
	}

	private function filter_rules( string $css, array $keep ): string {
		$css = preg_replace( '!/\*.*?\*/!s', '', $css ) ?? ''; $output = ''; $offset = 0; $length = strlen( $css );
		while ( $offset < $length ) {
			$open = strpos( $css, '{', $offset );
			if ( false === $open ) { break; }
			$prelude = trim( substr( $css, $offset, $open - $offset ) ); $depth = 1; $cursor = $open + 1; $quote = '';
			for ( ; $cursor < $length && $depth > 0; ++$cursor ) { $char = $css[$cursor]; if ( '' !== $quote ) { if ( $char === $quote && '\\' !== ($css[$cursor-1] ?? '') ) { $quote = ''; } continue; } if ( '"' === $char || "'" === $char ) { $quote = $char; continue; } if ( '{' === $char ) { ++$depth; } elseif ( '}' === $char ) { --$depth; } }
			if ( 0 !== $depth ) { break; }
			$body = substr( $css, $open + 1, $cursor - $open - 2 ); $candidate = $prelude . '{' . $body . '}';
			if ( 0 === strpos( $prelude, '@' ) || $this->selector_required( $prelude, $keep ) ) { $output .= $candidate; }
			$offset = $cursor;
		}
		return $output;
	}

	private function selector_required( string $prelude, array $keep ): bool {
		foreach ( explode( ',', $prelude ) as $selector ) { if ( isset( $keep[ trim( $selector ) ] ) ) { return true; } }
		return false;
	}

	private function rewrite_urls( string $css, string $source_url ): string {
		return preg_replace_callback( '/url\(\s*(["\']?)(?!data:|https?:|\/\/|#)([^)"\']+)\1\s*\)/i', static function ( array $match ) use ( $source_url ): string { $base = rtrim( dirname( (string) wp_parse_url( $source_url, PHP_URL_PATH ) ), '/' ) . '/'; $origin = (string) wp_parse_url( $source_url, PHP_URL_SCHEME ) . '://' . (string) wp_parse_url( $source_url, PHP_URL_HOST ); return 'url("' . esc_url_raw( $origin . $base . ltrim( trim( $match[2] ), '/' ) ) . '")'; }, $css ) ?? $css;
	}

	private function publish( string $filename, string $content ): void {
		if ( ! wp_mkdir_p( $this->directory ) || ! is_writable( $this->directory ) ) { throw new RuntimeException( 'CSS bundle directory is not writable.' ); }
		$temp = $this->directory . '/.' . $filename . '.' . wp_generate_password( 8, false, false ); $target = $this->directory . '/' . $filename;
		if ( false === file_put_contents( $temp, $content, LOCK_EX ) || ! rename( $temp, $target ) ) { if ( file_exists( $temp ) ) { unlink( $temp ); } throw new RuntimeException( 'Unable to publish CSS bundle.' ); }
	}
}
