<?php
/** Early-cache lookup response. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCacheResponse { private $status; private $content; private $headers; public function __construct( string $status, string $content = '', array $headers = array() ) { $this->status = $status; $this->content = $content; $this->headers = $headers; } public function status(): string { return $this->status; } public function content(): string { return $this->content; } public function headers(): array { return $this->headers; } public function is_servable(): bool { return in_array( $this->status, array( 'HIT', 'STALE' ), true ); } }
