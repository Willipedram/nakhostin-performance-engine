<?php
/** Lightweight lock-protected cache counters. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Locked counters avoid a database write on every cache hit.
final class CacheMetrics {
	/** @var string */ private $file;
	public function __construct( string $file ) { $this->file = $file; }
	public function record( string $event ): void {
		if ( ! in_array( $event, array( 'hits', 'misses', 'stale_hits', 'bypasses' ), true ) ) { return; }
		$dir = dirname( $this->file ); if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) { return; }
		$index = $dir . '/index.php'; if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX ) ) { return; }
		$htaccess = $dir . '/.htaccess'; if ( ! file_exists( $htaccess ) && false === file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", LOCK_EX ) ) { return; }
		$handle = fopen( $this->file, 'c+' ); if ( false === $handle || ! flock( $handle, LOCK_EX ) ) { return; }
		$data = json_decode( (string) stream_get_contents( $handle ), true ); $data = is_array( $data ) ? $data : array();
		$data[ $event ] = (int) ( $data[ $event ] ?? 0 ) + 1; rewind( $handle ); ftruncate( $handle, 0 ); fwrite( $handle, (string) wp_json_encode( $data ) ); fflush( $handle ); flock( $handle, LOCK_UN ); fclose( $handle );
	}
	public function all(): array { $defaults = array( 'hits' => 0, 'misses' => 0, 'stale_hits' => 0, 'bypasses' => 0 ); if ( ! is_readable( $this->file ) ) { return $defaults; } $data = json_decode( (string) file_get_contents( $this->file ), true ); return array_merge( $defaults, is_array( $data ) ? $data : array() ); }
	public function reset(): void { if ( file_exists( $this->file ) ) { unlink( $this->file ); } }
}
// phpcs:enable WordPress.WP.AlternativeFunctions
