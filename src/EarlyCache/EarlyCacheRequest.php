<?php
/** Plain-PHP early request value. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCacheRequest {
	private $method; private $scheme; private $host; private $uri; private $headers; private $cookies;
	public function __construct( string $method, string $scheme, string $host, string $uri, array $headers = array(), array $cookies = array() ) { $this->method = strtoupper( $method ); $this->scheme = strtolower( $scheme ); $this->host = strtolower( $host ); $this->uri = $uri; $this->headers = $headers; $this->cookies = $cookies; }
	public static function from_globals( array $server, array $cookies ): self { $forwarded = strtolower( trim( explode( ',', (string) ( $server['HTTP_X_FORWARDED_PROTO'] ?? '' ) )[0] ) ); $https = ( ! empty( $server['HTTPS'] ) && 'off' !== strtolower( (string) $server['HTTPS'] ) ) || 'https' === $forwarded; $headers = array(); if ( ! empty( $server['HTTP_AUTHORIZATION'] ) || ! empty( $server['REDIRECT_HTTP_AUTHORIZATION'] ) ) { $headers['authorization'] = 'present'; } return new self( (string) ( $server['REQUEST_METHOD'] ?? 'GET' ), $https ? 'https' : 'http', (string) ( $server['HTTP_HOST'] ?? '' ), (string) ( $server['REQUEST_URI'] ?? '/' ), $headers, $cookies ); }
	public function method(): string { return $this->method; } public function scheme(): string { return $this->scheme; } public function host(): string { return $this->host; } public function uri(): string { return $this->uri; } public function headers(): array { return $this->headers; } public function cookies(): array { return $this->cookies; }
}
