<?php
/**
 * Opt-in, redacting WordPress debug logger.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Infrastructure;

use Nakhostin\PerformanceEngine\Contracts\LoggerInterface;

final class DebugLogger implements LoggerInterface {
	/** @var Settings */
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function debug( string $message, array $context = array() ): void {
		$this->write( 'DEBUG', $message, $context );
	}

	public function warning( string $message, array $context = array() ): void {
		$this->write( 'WARNING', $message, $context );
	}

	public function error( string $message, array $context = array() ): void {
		$this->write( 'ERROR', $message, $context );
	}

	private function write( string $level, string $message, array $context ): void {
		if ( ! $this->settings->get( 'debugging.enabled', false ) || ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$safe_context = $this->redact( $context );
		$context_json = empty( $safe_context ) ? '' : ' ' . wp_json_encode( $safe_context );
		$line         = sprintf( '[NPE] [%s] %s%s', $level, $this->redact_message( $message ), $context_json );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Explicit opt-in debug destination.
		error_log( $line );
	}

	private function redact( array $context ): array {
		$safe = array();

		foreach ( $context as $key => $value ) {
			$key = sanitize_key( (string) $key );

			if ( preg_match( '/pass|secret|token|auth|cookie|email|user|personal|key/i', $key ) ) {
				$safe[ $key ] = '[redacted]';
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$safe[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $safe;
	}

	private function redact_message( string $message ): string {
		$message = sanitize_text_field( $message );

		return (string) preg_replace(
			'/(password|secret|token|authorization|cookie|api[_-]?key)\s*[:=]\s*[^\s,;]+/i',
			'$1=[redacted]',
			$message
		);
	}
}
