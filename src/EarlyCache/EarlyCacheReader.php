<?php
/** Lightweight guarded filesystem cache reader. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCacheReader {
	private $directory;
	public function __construct( string $directory ) { $this->directory = rtrim( $directory, '/\\' ); }
	public function read( string $key, ?int $now = null ): EarlyCacheResponse {
		$hash = hash( 'sha256', $key ); $path = $this->directory . '/entries/' . substr( $hash, 0, 2 ) . '/' . $hash . '.php';
		if ( ! is_readable( $path ) ) { return new EarlyCacheResponse( 'MISS' ); }
		$contents = file_get_contents( $path ); $prefix = "<?php exit; ?>\n";
		if ( ! is_string( $contents ) || 0 !== strpos( $contents, $prefix ) ) { return new EarlyCacheResponse( 'MISS' ); }
		$data = json_decode( substr( $contents, strlen( $prefix ) ), true );
		$required = array( 'key', 'created_at', 'expires_at', 'stale_until', 'content_hash', 'content' ); foreach ( $required as $field ) { if ( ! array_key_exists( $field, (array) $data ) ) { return new EarlyCacheResponse( 'MISS' ); } }
		if ( ! is_array( $data ) || 3 !== (int) ( $data['cache_version'] ?? 0 ) || ! is_string( $data['content'] ?? null ) || ! hash_equals( (string) ( $data['content_hash'] ?? '' ), hash( 'sha256', $data['content'] ) ) || ! hash_equals( $key, (string) ( $data['key'] ?? '' ) ) ) { return new EarlyCacheResponse( 'MISS' ); }
		if ( ! preg_match( '/<(?:!doctype\s+html|html|body)\b/i', $data['content'] ) || ! preg_match( '/<\/(?:body|html)>/i', $data['content'] ) ) { @unlink( $path ); return new EarlyCacheResponse( 'MISS' ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Fail-open cache cleanup.
		$now = $now ?? time(); $status = $now < (int) $data['expires_at'] ? 'HIT' : ( $now <= (int) $data['stale_until'] ? 'STALE' : 'MISS' );
		if ( 'MISS' === $status ) { return new EarlyCacheResponse( 'MISS' ); }
		$headers = array(); foreach ( (array) ( $data['headers'] ?? array() ) as $name => $value ) { $name = strtolower( (string) $name ); if ( in_array( $name, array( 'content-type', 'content-language' ), true ) && is_scalar( $value ) ) { $headers[ $name ] = str_replace( array( "\r", "\n" ), '', (string) $value ); } } $headers['X-NPE-Cache'] = $status; $headers['X-NPE-Cache-Layer'] = 'EARLY'; $headers['X-NPE-Cache-Provider'] = 'NPE'; $headers['Age'] = (string) max( 0, $now - (int) $data['created_at'] ); $headers['Cache-Control'] = 'public, max-age=' . max( 0, (int) $data['expires_at'] - $now );
		return new EarlyCacheResponse( $status, $data['content'], $headers );
	}
}
