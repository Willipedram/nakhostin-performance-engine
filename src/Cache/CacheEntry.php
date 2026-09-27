<?php
/**
 * Versioned page-cache value and metadata.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheEntry {
	public const VERSION = 1;
	/** @var array */ private $data;

	private function __construct( array $data ) { $this->data = $data; }

	public static function create( string $key, string $url, string $content, array $headers, int $ttl, int $stale_ttl, array $dependencies = array(), string $signature = '', ?int $now = null ): self {
		$now = $now ?? time();
		return new self(
			array(
				'cache_version' => self::VERSION,
				'key'           => $key,
				'url'           => $url,
				'created_at'    => $now,
				'expires_at'    => $now + max( 1, $ttl ),
				'stale_until'   => $now + max( 1, $ttl ) + max( 0, $stale_ttl ),
				'signature'     => sanitize_key( $signature ),
				'dependencies'  => array_values( array_unique( array_filter( array_map( 'sanitize_key', $dependencies ) ) ) ),
				'content_hash'  => hash( 'sha256', $content ),
				'headers'       => self::safe_headers( $headers ),
				'content'       => $content,
			)
		);
	}

	public static function from_array( array $data ): ?self {
		$required = array( 'cache_version', 'key', 'url', 'created_at', 'expires_at', 'stale_until', 'content_hash', 'headers', 'content' );
		foreach ( $required as $field ) { if ( ! array_key_exists( $field, $data ) ) { return null; } }
		if ( self::VERSION !== (int) $data['cache_version'] || ! is_string( $data['content'] ) || ! hash_equals( (string) $data['content_hash'], hash( 'sha256', $data['content'] ) ) ) { return null; }
		return new self( $data );
	}

	public function to_array(): array { return $this->data; }
	public function key(): string { return (string) $this->data['key']; }
	public function url(): string { return (string) $this->data['url']; }
	public function content(): string { return (string) $this->data['content']; }
	public function headers(): array { return (array) $this->data['headers']; }
	public function created_at(): int { return (int) $this->data['created_at']; }
	public function expires_at(): int { return (int) $this->data['expires_at']; }
	public function dependencies(): array { return (array) ( $this->data['dependencies'] ?? array() ); }
	public function is_fresh( ?int $now = null ): bool { return ( $now ?? time() ) < $this->expires_at(); }
	public function is_stale( ?int $now = null ): bool { $now = $now ?? time(); return $now >= $this->expires_at() && $now <= (int) $this->data['stale_until']; }

	private static function safe_headers( array $headers ): array {
		$safe = array();
		foreach ( $headers as $name => $value ) {
			$name = strtolower( trim( (string) $name ) );
			if ( in_array( $name, array( 'content-type', 'content-language' ), true ) && is_scalar( $value ) ) { $safe[ $name ] = str_replace( array( "\r", "\n" ), '', (string) $value ); }
		}
		return $safe;
	}
}
