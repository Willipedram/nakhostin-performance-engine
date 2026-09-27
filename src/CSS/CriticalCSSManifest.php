<?php
namespace Nakhostin\PerformanceEngine\CSS;
final class CriticalCSSManifest { private $data; public function __construct( array $data ) { $this->data = $data; } public function to_array(): array { return $this->data; } public function is_valid(): bool { return ! empty( $this->data['page_signature'] ) && ! empty( $this->data['generated_at'] ) && is_string( $this->data['css'] ?? null ) && strlen( $this->data['css'] ) <= 50000; } }
