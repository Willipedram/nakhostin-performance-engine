<?php
namespace Nakhostin\PerformanceEngine\Optimization;
final class OptimizationContext { private $data; public function __construct( array $data ) { $this->data = $data; } public function get( string $key, $default = null ) { return $this->data[ $key ] ?? $default; } public function to_array(): array { return $this->data; } public function is_protected(): bool { return ! empty( $this->data['logged_in'] ) || ! empty( $this->data['admin'] ) || in_array( $this->data['page_type'] ?? '', array( 'cart', 'checkout', 'account' ), true ); } }
