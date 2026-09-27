<?php
namespace Nakhostin\PerformanceEngine\Assets;
final class AssetDependencyGraph { private $assets; public function __construct( array $assets ) { $this->assets = $assets; } public function dependents( string $handle ): array { $result = array(); foreach ( $this->assets as $candidate => $asset ) { if ( in_array( $handle, (array) ( $asset['dependencies'] ?? array() ), true ) ) { $result[] = $candidate; } } return $result; } public function can_unload( string $handle, array $kept ): bool { return empty( array_intersect( $this->dependents( $handle ), $kept ) ); } }
