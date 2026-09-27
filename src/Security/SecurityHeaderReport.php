<?php
/** Immutable security-header audit result. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Security;
final class SecurityHeaderReport { /** @var array */ private $data; public function __construct( array $data ) { $this->data = $data; } public function get( string $key, $default = null ) { return $this->data[ $key ] ?? $default; } public function to_array(): array { return $this->data; } }
