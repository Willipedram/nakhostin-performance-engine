<?php
/** Bounded storage for per-page font usage manifests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

final class FontStorage {
	public const OPTION = 'npe_font_manifests';
	private const MAX_MANIFESTS = 100;

	public function save( FontManifest $manifest ): bool {
		$data = $manifest->to_array();
		$url = $this->normalize_url( (string) ( $data['source_url'] ?? '' ) );
		if ( '' === $url ) { return false; }
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$stored[ hash( 'sha256', $url ) ] = $data;
		$stored = array_slice( $stored, -self::MAX_MANIFESTS, null, true );
		return update_option( self::OPTION, $stored, false );
	}

	public function for_url( string $url ): ?FontManifest {
		$url = $this->normalize_url( $url );
		$stored = get_option( self::OPTION, array() );
		$data = is_array( $stored ) ? ( $stored[ hash( 'sha256', $url ) ] ?? null ) : null;
		return is_array( $data ) ? new FontManifest( $data ) : null;
	}

	public function invalidate_url( string $url ): bool {
		$url = $this->normalize_url( $url );
		if ( '' === $url ) { return false; }
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) { return false; }
		unset( $stored[ hash( 'sha256', $url ) ] );
		return update_option( self::OPTION, $stored, false );
	}

	public function flush(): bool { return delete_option( self::OPTION ); }

	public function latest(): ?FontManifest {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) || ! $stored ) { return null; }
		$data = end( $stored );
		return is_array( $data ) ? new FontManifest( $data ) : null;
	}

	/** @return FontManifest[] */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) { return array(); }
		return array_values( array_map( static function ( array $data ): FontManifest { return new FontManifest( $data ); }, array_filter( $stored, 'is_array' ) ) );
	}

	private function normalize_url( string $url ): string {
		$parts = wp_parse_url( esc_url_raw( $url ) );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) { return ''; }
		return strtolower( (string) ( $parts['scheme'] ?? 'https' ) ) . '://' . strtolower( (string) $parts['host'] ) . '/' . ltrim( (string) ( $parts['path'] ?? '/' ), '/' );
	}
}
