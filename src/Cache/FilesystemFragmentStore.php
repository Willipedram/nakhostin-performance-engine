<?php
/**
 * Atomic filesystem fallback for fragment caching.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fragment files require portable locking and atomic rename semantics.
final class FilesystemFragmentStore implements FragmentStoreInterface {
	/** @var string */
	private $directory;

	/** @var array<string, resource> */
	private $locks = array();

	public function __construct( string $directory ) {
		$this->directory = rtrim( $directory, '/\\' );
	}

	public function get( string $key ): ?FragmentEntry {
		$path = $this->path( $key );
		if ( ! is_readable( $path ) ) {
			$this->record( $key, '', false );
			return null;
		}

		$contents = file_get_contents( $path );
		$data     = is_string( $contents ) ? $this->decode( $contents ) : null;
		$entry    = is_array( $data ) ? FragmentEntry::from_array( $data ) : null;
		if ( ! $entry || ! hash_equals( $key, $entry->identifier() ) || $entry->is_expired() ) {
			$this->delete( $key );
			$this->record( $key, '', false );
			return null;
		}

		$this->record( $key, $entry->name(), true );
		return $entry;
	}

	public function set( FragmentEntry $entry, int $ttl ): bool {
		$path = $this->path( $entry->identifier() );
		if ( ! $this->ensure_directory( dirname( $path ) ) ) {
			return false;
		}

		$json = wp_json_encode( $entry->to_array(), JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		$temp = tempnam( dirname( $path ), '.npe-fragment-' );
		if ( false === $temp ) {
			return false;
		}
		$written = false !== file_put_contents( $temp, "<?php exit; ?>\n" . $json, LOCK_EX );
		$stored  = $written && rename( $temp, $path );
		if ( file_exists( $temp ) ) {
			unlink( $temp );
		}

		return $stored;
	}

	public function delete( string $key ): bool {
		$path = $this->path( $key );
		return ! file_exists( $path ) || unlink( $path );
	}

	public function invalidate_dependencies( array $dependencies ): int {
		$dependencies = array_values( array_unique( array_filter( array_map( 'sanitize_key', $dependencies ) ) ) );
		$count        = 0;
		foreach ( $this->entries( true ) as $entry ) {
			if ( array_intersect( $entry->dependencies(), $dependencies ) && $this->delete( $entry->identifier() ) ) {
				++$count;
			}
		}

		return $count;
	}

	public function flush(): int {
		$count = 0;
		foreach ( $this->entries( true ) as $entry ) {
			if ( $this->delete( $entry->identifier() ) ) {
				++$count;
			}
		}
		return $count;
	}

	public function acquire_lock( string $key, int $ttl ): bool {
		$path = $this->lock_path( $key );
		if ( ! $this->ensure_directory( dirname( $path ) ) ) {
			return false;
		}
		$handle = fopen( $path, 'c' );
		if ( false === $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
			return false;
		}
		$this->locks[ $key ] = $handle;
		return true;
	}

	public function release_lock( string $key ): void {
		if ( ! isset( $this->locks[ $key ] ) ) {
			return;
		}
		flock( $this->locks[ $key ], LOCK_UN );
		fclose( $this->locks[ $key ] );
		unset( $this->locks[ $key ] );
	}

	public function statistics(): array {
		$metrics = $this->metrics();
		return array(
			'backend' => $this->backend_name(),
			'entries' => count( $this->entries() ),
			'hits'    => (int) $metrics['hits'],
			'misses'  => (int) $metrics['misses'],
		);
	}

	public function top( int $limit = 10 ): array {
		$items = $this->metrics()['fragments'];
		uasort( $items, static function ( array $left, array $right ): int { return $right['hits'] <=> $left['hits']; } );
		return array_slice( array_values( $items ), 0, max( 0, $limit ) );
	}

	public function backend_name(): string {
		return 'filesystem';
	}

	private function entries( bool $include_expired = false ): array {
		if ( ! is_dir( $this->directory . '/entries' ) ) {
			return array();
		}
		$entries  = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory . '/entries', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() || ! preg_match( '/[a-f0-9]{64}\.php$/', $file->getFilename() ) ) {
				continue;
			}
			$data  = $this->decode( (string) file_get_contents( $file->getPathname() ) );
			$entry = is_array( $data ) ? FragmentEntry::from_array( $data ) : null;
			if ( $entry && ( $include_expired || ! $entry->is_expired() ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	private function record( string $key, string $name, bool $hit ): void {
		if ( ! $this->ensure_directory( $this->directory ) ) {
			return;
		}
		$path   = $this->directory . '/metrics.json';
		$handle = fopen( $path, 'c+' );
		if ( false === $handle || ! flock( $handle, LOCK_EX ) ) {
			return;
		}
		$data = json_decode( (string) stream_get_contents( $handle ), true );
		$data = is_array( $data ) ? $data : array( 'hits' => 0, 'misses' => 0, 'fragments' => array() );
		$data[ $hit ? 'hits' : 'misses' ] = (int) ( $data[ $hit ? 'hits' : 'misses' ] ?? 0 ) + 1;
		if ( $hit ) {
			$data['fragments'][ $key ] = array(
				'name' => $name,
				'hits' => (int) ( $data['fragments'][ $key ]['hits'] ?? 0 ) + 1,
			);
		}
		rewind( $handle );
		ftruncate( $handle, 0 );
		fwrite( $handle, (string) wp_json_encode( $data ) );
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}

	private function metrics(): array {
		$defaults = array( 'hits' => 0, 'misses' => 0, 'fragments' => array() );
		$path     = $this->directory . '/metrics.json';
		if ( ! is_readable( $path ) ) {
			return $defaults;
		}
		$data = json_decode( (string) file_get_contents( $path ), true );
		return array_merge( $defaults, is_array( $data ) ? $data : array() );
	}

	private function ensure_directory( string $directory ): bool {
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			return false;
		}
		$index = $this->directory . '/index.php';
		if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX ) ) {
			return false;
		}
		$htaccess = $this->directory . '/.htaccess';
		if ( ! file_exists( $htaccess ) && false === file_put_contents( $htaccess, "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", LOCK_EX ) ) { return false; }
		return true;
	}

	private function path( string $key ): string {
		$hash = hash( 'sha256', $key );
		return $this->directory . '/entries/' . substr( $hash, 0, 2 ) . '/' . $hash . '.php';
	}

	private function lock_path( string $key ): string {
		return $this->directory . '/locks/' . hash( 'sha256', $key ) . '.lock';
	}

	private function decode( string $contents ): ?array {
		$prefix = "<?php exit; ?>\n";
		if ( 0 !== strpos( $contents, $prefix ) ) { return null; }
		$data = json_decode( substr( $contents, strlen( $prefix ) ), true );
		return is_array( $data ) ? $data : null;
	}
}
// phpcs:enable WordPress.WP.AlternativeFunctions
