<?php
/**
 * Immutable request data used by the page-cache policy.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheRequest {
	/** @var string */ private $method;
	/** @var string */ private $url;
	/** @var array */ private $headers;
	/** @var array */ private $cookies;
	/** @var array */ private $context;

	public function __construct( string $method, string $url, array $headers = array(), array $cookies = array(), array $context = array() ) {
		$this->method  = strtoupper( $method );
		$this->url     = $url;
		$this->headers = $headers;
		$this->cookies = $cookies;
		$this->context = $context;
	}

	public function method(): string { return $this->method; }
	public function url(): string { return $this->url; }
	public function headers(): array { return $this->headers; }
	public function cookies(): array { return $this->cookies; }
	public function context(): array { return $this->context; }
	public function context_value( string $key, $default = null ) { return $this->context[ $key ] ?? $default; }
}
