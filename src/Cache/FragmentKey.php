<?php
/**
 * Stable, privacy-aware fragment key.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use InvalidArgumentException;

final class FragmentKey {
	public const PUBLIC_VISIBILITY = 'public';
	public const PRIVATE_VISIBILITY = 'private';

	/** @var string */
	private $identifier;

	/** @var string */
	private $name;

	/** @var int */
	private $version;

	/** @var string */
	private $visibility;

	/** @var array */
	private $dimensions;

	private function __construct( string $identifier, string $name, int $version, string $visibility, array $dimensions ) {
		$this->identifier = $identifier;
		$this->name       = $name;
		$this->version    = $version;
		$this->visibility = $visibility;
		$this->dimensions = $dimensions;
	}

	public static function create(
		string $name,
		int $version = 1,
		array $dimensions = array(),
		string $visibility = self::PUBLIC_VISIBILITY,
		string $private_scope = ''
	): self {
		$name = sanitize_key( $name );
		if ( '' === $name || $version < 1 ) {
			throw new InvalidArgumentException( 'Fragment names and versions must be valid.' );
		}
		if ( ! in_array( $visibility, array( self::PUBLIC_VISIBILITY, self::PRIVATE_VISIBILITY ), true ) ) {
			throw new InvalidArgumentException( 'Fragment visibility must be public or private.' );
		}
		if ( self::PRIVATE_VISIBILITY === $visibility && '' === $private_scope ) {
			throw new InvalidArgumentException( 'Private fragments require an opaque scope.' );
		}

		$normalized = self::normalize_dimensions( $dimensions );
		$payload    = array(
			'name'       => $name,
			'version'    => $version,
			'visibility' => $visibility,
			'dimensions' => $normalized,
		);
		if ( self::PRIVATE_VISIBILITY === $visibility ) {
			$payload['scope_hash'] = hash( 'sha256', $private_scope );
		}

		return new self(
			hash( 'sha256', (string) wp_json_encode( $payload ) ),
			$name,
			$version,
			$visibility,
			$normalized
		);
	}

	public function identifier(): string {
		return $this->identifier;
	}

	public function name(): string {
		return $this->name;
	}

	public function version(): int {
		return $this->version;
	}

	public function visibility(): string {
		return $this->visibility;
	}

	public function dimensions(): array {
		return $this->dimensions;
	}

	private static function normalize_dimensions( array $dimensions ): array {
		$normalized = array();
		foreach ( $dimensions as $name => $value ) {
			$name = sanitize_key( (string) $name );
			if ( '' === $name ) {
				continue;
			}
			if ( self::is_sensitive_dimension( $name ) || ! is_scalar( $value ) ) {
				throw new InvalidArgumentException( 'Fragment dimensions cannot contain sensitive or structured data.' );
			}
			$normalized[ $name ] = substr( sanitize_text_field( (string) $value ), 0, 128 );
		}
		ksort( $normalized, SORT_STRING );

		return $normalized;
	}

	private static function is_sensitive_dimension( string $name ): bool {
		return false !== strpos( $name, 'token' )
			|| false !== strpos( $name, 'password' )
			|| false !== strpos( $name, 'nonce' )
			|| false !== strpos( $name, 'email' )
			|| false !== strpos( $name, 'user' )
			|| false !== strpos( $name, 'customer' )
			|| false !== strpos( $name, 'session' )
			|| false !== strpos( $name, 'cookie' )
			|| false !== strpos( $name, 'cart' );
	}
}
