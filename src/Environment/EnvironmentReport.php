<?php
/** Immutable runtime capability report. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;

final class EnvironmentReport {
	/** @var array<string,mixed> */ private $data;
	public function __construct( array $data ) { $this->data = $data; }
	public function server(): string { return (string) ( $this->data['server'] ?? 'unknown' ); }
	public function supports_early_cache(): bool { return ! empty( $this->data['supports_early_cache'] ); }
	public function has_persistent_object_cache(): bool { return ! empty( $this->data['persistent_object_cache'] ); }
	public function existing_page_cache_owner(): string { return (string) ( $this->data['page_cache_owner'] ?? 'none' ); }
	public function get( string $key, $default = null ) { return $this->data[ $key ] ?? $default; }
	public function to_array(): array { return $this->data; }
}
