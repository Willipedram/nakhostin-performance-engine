<?php
/**
 * Versioned fragment value and invalidation metadata.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class FragmentEntry {
	public const FORMAT_VERSION = 1;

	/** @var array */
	private $data;

	private function __construct( array $data ) {
		$this->data = $data;
	}

	public static function create( FragmentKey $key, $value, int $ttl, array $dependencies, ?int $now = null ): ?self {
		if ( ! is_null( $value ) && ! is_scalar( $value ) && ! is_array( $value ) ) {
			return null;
		}

		$now          = $now ?? time();
		$dependencies = array_values( array_unique( array_filter( array_map( 'sanitize_key', $dependencies ) ) ) );
		$content_hash = self::content_hash( $value );
		if ( null === $content_hash ) {
			return null;
		}
		$data         = array(
			'format_version' => self::FORMAT_VERSION,
			'key'            => $key->identifier(),
			'name'           => $key->name(),
			'version'        => $key->version(),
			'visibility'     => $key->visibility(),
			'created_at'     => $now,
			'expires_at'     => $now + max( 1, $ttl ),
			'dependencies'   => $dependencies,
			'value'          => $value,
		);
		$data['content_hash'] = $content_hash;

		return new self( $data );
	}

	public static function from_array( array $data ): ?self {
		$required = array( 'format_version', 'key', 'name', 'version', 'visibility', 'created_at', 'expires_at', 'dependencies', 'value', 'content_hash' );
		foreach ( $required as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				return null;
			}
		}
		if ( self::FORMAT_VERSION !== (int) $data['format_version'] ) {
			return null;
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $data['key'] ) || '' === sanitize_key( (string) $data['name'] ) ) {
			return null;
		}
		if ( ! in_array( $data['visibility'], array( FragmentKey::PUBLIC_VISIBILITY, FragmentKey::PRIVATE_VISIBILITY ), true ) || ! is_array( $data['dependencies'] ) ) {
			return null;
		}
		if ( (int) $data['version'] < 1 || (int) $data['expires_at'] <= (int) $data['created_at'] ) {
			return null;
		}
		if ( ! is_null( $data['value'] ) && ! is_scalar( $data['value'] ) && ! is_array( $data['value'] ) ) {
			return null;
		}
		$content_hash = self::content_hash( $data['value'] );
		if ( null === $content_hash || ! hash_equals( (string) $data['content_hash'], $content_hash ) ) {
			return null;
		}

		return new self( $data );
	}

	public function to_array(): array { return $this->data; }
	public function identifier(): string { return (string) $this->data['key']; }
	public function name(): string { return (string) $this->data['name']; }
	public function value() { return $this->data['value']; }
	public function dependencies(): array { return (array) $this->data['dependencies']; }
	public function expires_at(): int { return (int) $this->data['expires_at']; }
	public function is_expired( ?int $now = null ): bool { return ( $now ?? time() ) >= $this->expires_at(); }

	private static function content_hash( $value ): ?string {
		$encoded = wp_json_encode( $value );
		return false === $encoded ? null : hash( 'sha256', (string) $encoded );
	}
}
