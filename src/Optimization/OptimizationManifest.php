<?php
/** Immutable, versioned precomputed optimization manifest. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Optimization;

final class OptimizationManifest {
	private const VERSION = 3;
	/** @var array<string,mixed> */ private $data;

	public function __construct( array $data ) { $data['manifest_version'] = self::VERSION; $this->data = $data; }

	public static function from_array( array $data ): ?self {
		$capture = $data['capture'] ?? null; $decisions = $data['assets']['decisions'] ?? null;
		if ( self::VERSION !== (int) ( $data['manifest_version'] ?? 0 ) || empty( $data['page_signature'] ) || empty( $data['generated_at'] ) || ! is_array( $capture ) || ! is_bool( $capture['complete'] ?? null ) || ! is_string( $capture['status'] ?? null ) || ! is_array( $decisions ) ) { return null; }
		foreach ( $decisions as $decision ) {
			if ( ! is_array( $decision ) || '' === sanitize_key( (string) ( $decision['handle'] ?? '' ) ) || ! in_array( $decision['decision'] ?? '', array( 'keep', 'defer', 'unload', 'unknown' ), true ) || ! is_string( $decision['reason'] ?? null ) || ! is_numeric( $decision['confidence'] ?? null ) ) { return null; }
			if ( 'unload' === $decision['decision'] && true !== $capture['complete'] ) { return null; }
		}
		return new self( $data );
	}

	public function to_array(): array { return $this->data; }
	public function section( string $name ): array { return is_array( $this->data[ $name ] ?? null ) ? $this->data[ $name ] : array(); }
	public function source_url(): string { return (string) ( $this->data['source_url'] ?? '' ); }
	public function is_stale( ?int $now = null, int $ttl = 604800 ): bool { return ( $now ?? time() ) - (int) $this->data['generated_at'] > $ttl; }
}
