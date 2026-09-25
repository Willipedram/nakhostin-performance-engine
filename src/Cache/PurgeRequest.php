<?php
/**
 * Validated smart-purge request.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use InvalidArgumentException;

final class PurgeRequest {
	public const LEVELS = array( 'url', 'page', 'component', 'product', 'taxonomy', 'page_type', 'asset', 'full' );

	/** @var string */ private $level;
	/** @var string */ private $identifier;
	/** @var string */ private $reason;
	/** @var array */ private $warm_urls;

	private function __construct( string $level, string $identifier, string $reason, array $warm_urls ) {
		$this->level      = $level;
		$this->identifier = $identifier;
		$this->reason     = $reason;
		$this->warm_urls  = $warm_urls;
	}

	public static function create( string $level, $identifier, string $reason, array $warm_urls = array() ): self {
		$level = sanitize_key( $level );
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			throw new InvalidArgumentException( 'Unsupported purge level.' );
		}
		if ( 'url' === $level ) {
			$identifier = esc_url_raw( (string) $identifier );
		} elseif ( in_array( $level, array( 'page', 'product' ), true ) ) {
			$identifier = (string) absint( $identifier );
		} elseif ( 'taxonomy' === $level ) {
			$identifier = strtolower( trim( (string) $identifier ) );
			$identifier = preg_match( '/^[a-z0-9_-]+:[1-9][0-9]*$/', $identifier ) ? $identifier : '';
		} elseif ( 'full' === $level ) {
			$identifier = 'all';
		} else {
			$identifier = sanitize_key( (string) $identifier );
		}
		if ( '' === $identifier || '0' === $identifier ) {
			throw new InvalidArgumentException( 'Purge requests require a valid identifier.' );
		}
		$reason = substr( sanitize_text_field( $reason ), 0, 160 );
		$warm   = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $warm_urls ) ) ) );

		return new self( $level, $identifier, $reason ?: 'unspecified', array_slice( $warm, 0, 100 ) );
	}

	public static function from_array( array $data ): self {
		return self::create( (string) ( $data['level'] ?? '' ), $data['identifier'] ?? '', (string) ( $data['reason'] ?? '' ), (array) ( $data['warm_urls'] ?? array() ) );
	}

	public function level(): string { return $this->level; }
	public function identifier(): string { return $this->identifier; }
	public function reason(): string { return $this->reason; }
	public function warm_urls(): array { return $this->warm_urls; }
	public function to_array(): array { return array( 'level' => $this->level, 'identifier' => $this->identifier, 'reason' => $this->reason, 'warm_urls' => $this->warm_urls ); }
	public function fingerprint(): string { return hash( 'sha256', (string) wp_json_encode( array( $this->level, $this->identifier ) ) ); }
}
