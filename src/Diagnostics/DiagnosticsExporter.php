<?php
/** Strict allowlist diagnostic support export. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
final class DiagnosticsExporter {
	private const SECTIONS = array( 'server', 'php', 'cache', 'object_cache', 'performance', 'optimization', 'integrations', 'security_headers' );
	public function export( array $report ): array { $result = array(); foreach ( self::SECTIONS as $section ) { $result[ $section ] = $this->sanitize( is_array( $report[ $section ] ?? null ) ? $report[ $section ] : array() ); } return $result; }
	private function sanitize( array $data ): array { $safe = array(); foreach ( $data as $key => $value ) { $key = sanitize_key( (string) $key ); if ( '' === $key || preg_match( '/password|passwd|token|secret|nonce|authorization|cookie|session|sql|query|customer|email|user/i', $key ) ) { continue; } if ( is_array( $value ) ) { $safe[ $key ] = $this->sanitize( $value ); } elseif ( is_bool( $value ) || is_numeric( $value ) || is_string( $value ) || null === $value ) { $safe[ $key ] = is_string( $value ) ? substr( sanitize_text_field( $value ), 0, 500 ) : $value; } } return $safe; }
}
