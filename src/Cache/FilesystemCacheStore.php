<?php
/**
 * Atomic filesystem page-cache backend.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Atomic cache I/O requires locks and rename semantics unavailable through WP_Filesystem.
final class FilesystemCacheStore implements PageCacheStoreInterface {
	/** @var string */ private $directory;

	public function __construct( string $directory ) { $this->directory = rtrim( $directory, '/\\' ); }

	public function get( string $key, $default = null ) { $entry = $this->read( $key ); return $entry ? $entry->to_array() : $default; }
	public function set( string $key, $value, int $ttl = 0 ): bool {
		if ( $value instanceof CacheEntry ) { return $this->write( $value ); }
		if ( ! is_array( $value ) ) { return false; }
		$entry = CacheEntry::from_array( $value );
		return $entry && hash_equals( $key, $entry->key() ) ? $this->write( $entry ) : false;
	}
	public function delete( string $key ): bool { $path = $this->path( $key ); $deleted = ! file_exists( $path ) || unlink( $path ); if ( file_exists( $path . '.lock' ) ) { unlink( $path . '.lock' ); } return $deleted; }
	public function has( string $key ): bool { return null !== $this->read( $key ); }
	public function get_name(): string { return 'filesystem'; }

	public function read( string $key ): ?CacheEntry {
		$path = $this->path( $key );
		if ( ! is_readable( $path ) ) { return null; }
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) { return null; }
		flock( $handle, LOCK_SH );
		$json = stream_get_contents( $handle );
		flock( $handle, LOCK_UN ); fclose( $handle );
		$data  = is_string( $json ) ? $this->decode( $json ) : null;
		$entry = is_array( $data ) ? CacheEntry::from_array( $data ) : null;
		if ( ! $entry || ! hash_equals( $key, $entry->key() ) ) { $this->delete( $key ); return null; }
		return $entry;
	}

	public function write( CacheEntry $entry ): bool {
		$path = $this->path( $entry->key() );
		$dir  = dirname( $path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) { return false; }
		if ( ! $this->protect_directory() ) { return false; }
		$lock = fopen( $path . '.lock', 'c' );
		if ( false === $lock || ! flock( $lock, LOCK_EX ) ) { if ( is_resource( $lock ) ) { fclose( $lock ); } return false; }
		$temp = tempnam( $dir, '.npe-' );
		$json = wp_json_encode( $entry->to_array(), JSON_UNESCAPED_SLASHES );
		$json = false !== $json ? "<?php exit; ?>\n" . $json : false;
		$ok   = false !== $temp && false !== $json && false !== file_put_contents( $temp, $json, LOCK_EX ) && rename( $temp, $path );
		if ( false !== $temp && file_exists( $temp ) ) { unlink( $temp ); }
		flock( $lock, LOCK_UN ); fclose( $lock );
		return $ok;
	}

	public function flush(): bool {
		$ok = true;
		foreach ( $this->files() as $file ) { if ( ! unlink( $file ) ) { $ok = false; } }
		return $ok;
	}

	public function purge_urls( array $urls ): int { return $this->purge_matching( static function ( CacheEntry $entry ) use ( $urls ): bool { return in_array( $entry->url(), $urls, true ); } ); }
	public function purge_dependencies( array $dependencies ): int { return $this->purge_matching( static function ( CacheEntry $entry ) use ( $dependencies ): bool { return (bool) array_intersect( $entry->dependencies(), $dependencies ); } ); }
	public function cached_urls(): array { $urls = array(); foreach ( $this->entries() as $entry ) { $urls[] = $entry->url(); } return array_values( array_unique( $urls ) ); }
	public function statistics(): array {
		$count = 0; $size = 0;
		foreach ( $this->entries() as $entry ) { ++$count; $path = $this->path( $entry->key() ); $size += file_exists( $path ) ? (int) filesize( $path ) : 0; }
		return array( 'entries' => $count, 'size' => $size, 'backend' => $this->get_name() );
	}

	private function path( string $key ): string { $hash = hash( 'sha256', $key ); return $this->directory . '/entries/' . substr( $hash, 0, 2 ) . '/' . $hash . '.php'; }
	private function files(): array { if ( ! is_dir( $this->directory ) ) { return array(); } $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ) ); $files = array(); foreach ( $iterator as $file ) { if ( $file->isFile() ) { $files[] = $file->getPathname(); } } return $files; }
	private function entries(): array { $entries = array(); foreach ( $this->files() as $file ) { if ( ! preg_match( '/\/[a-f0-9]{64}\.php$/', $file ) || ! is_readable( $file ) ) { continue; } $data = $this->decode( (string) file_get_contents( $file ) ); $entry = is_array( $data ) ? CacheEntry::from_array( $data ) : null; if ( $entry ) { $entries[] = $entry; } } return $entries; }
	private function purge_matching( callable $callback ): int { $count = 0; foreach ( $this->entries() as $entry ) { if ( $callback( $entry ) && $this->delete( $entry->key() ) ) { ++$count; } } return $count; }
	private function decode( string $contents ): ?array { $prefix = "<?php exit; ?>\n"; if ( 0 !== strpos( $contents, $prefix ) ) { return null; } $data = json_decode( substr( $contents, strlen( $prefix ) ), true ); return is_array( $data ) ? $data : null; }
	private function protect_directory(): bool {
		if ( ! is_dir( $this->directory ) ) { return false; }
		$index = $this->directory . '/index.php';
		if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX ) ) { return false; }
		$htaccess = $this->directory . '/.htaccess';
		if ( ! file_exists( $htaccess ) && false === file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", LOCK_EX ) ) { return false; }
		return true;
	}
}
// phpcs:enable WordPress.WP.AlternativeFunctions
