<?php
/** Reports observed response compression; it never enables PHP output compression. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
final class CompressionHealthCheck { public function run( array $headers = array() ): array { $encoding = strtolower( (string) ( $headers['content-encoding'] ?? $headers['Content-Encoding'] ?? '' ) ); return array( 'brotli_active' => false !== strpos( $encoding, 'br' ), 'gzip_active' => false !== strpos( $encoding, 'gzip' ), 'html_compressed' => '' !== $encoding, 'status' => '' === $encoding ? 'not_detected' : 'active' ); } }
