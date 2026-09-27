<?php
/**
 * Explicit, bounded probe for LiteSpeed Cache's public Guest Vary endpoint.
 *
 * The probe is never run during a frontend request. It uses no visitor cookies,
 * follows no redirects, and stores only status/timing/content-type diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */
namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;

final class LiteSpeedGuestVaryProbe {
	public const OPTION = 'npe_litespeed_guest_vary_probe';
	private const SLOW_THRESHOLD_MS = 2000.0;

	/** @var callable|null */
	private $transport;

	/** @var string */
	private $endpoint;

	/** @var callable */
	private $clock;

	public function __construct( string $endpoint = '', ?callable $transport = null, ?callable $clock = null ) {
		$this->endpoint  = $endpoint;
		$this->transport = $transport;
		$this->clock     = $clock ?? 'microtime';
	}

	public function probe(): array {
		$url = $this->endpoint;
		if ( '' === $url && function_exists( 'plugins_url' ) ) {
			$url = plugins_url( 'litespeed-cache/guest.vary.php' );
		}

		if ( '' === $url || ! $this->is_same_origin( $url ) ) {
			return $this->save( $this->report( 'unavailable', 0, 0.0, '', 'Guest Vary endpoint is unavailable or not same-origin.' ) );
		}

		$started  = $this->now();
		$response = $this->request( $url );
		$elapsed  = round( ( $this->now() - $started ) * 1000, 1 );

		if ( is_wp_error( $response ) ) {
			return $this->save( $this->report( 'failed', 0, $elapsed, '', 'The request failed before an HTTP response was received.' ) );
		}

		$status       = wp_remote_retrieve_response_code( $response );
		$body         = wp_remote_retrieve_body( $response );
		$content_type = $this->header( $response, 'content-type' );
		$json         = json_decode( $body, true );
		$valid_json   = is_array( $json );

		if ( 403 === $status ) {
			$message = 'HTTP 403: investigate the web server, WAF, ModSecurity, or LiteSpeed configuration.';
			$state   = 'failed';
		} elseif ( 200 !== $status ) {
			$message = 'Guest Vary returned a non-success HTTP status.';
			$state   = 'failed';
		} elseif ( ! $valid_json ) {
			$message = 'Guest Vary returned HTML or another non-JSON response.';
			$state   = 'failed';
		} elseif ( $elapsed >= self::SLOW_THRESHOLD_MS ) {
			$message = 'Guest Vary is responding slowly; inspect PHP workers, WAF rules, DNS/loopback, and LiteSpeed Guest Mode.';
			$state   = 'slow';
		} else {
			$message = 'Guest Vary responded normally.';
			$state   = 'healthy';
		}

		return $this->save( $this->report( $state, $status, $elapsed, $content_type, $message ) );
	}

	public function last(): array {
		$report = get_option( self::OPTION, array() );
		return is_array( $report ) ? $report : array();
	}

	private function request( string $url ) {
		$args = array(
			'timeout'     => 3,
			'redirection' => 0,
			'cookies'     => array(),
			'headers'     => array( 'Accept' => 'application/json' ),
			'user-agent'  => 'NPE-Guest-Vary-Diagnostic/' . ( defined( 'NPE_VERSION' ) ? NPE_VERSION : 'unknown' ),
		);
		return $this->transport ? call_user_func( $this->transport, $url, $args ) : wp_remote_get( $url, $args );
	}

	private function is_same_origin( string $url ): bool {
		$target = wp_parse_url( $url );
		$home   = wp_parse_url( home_url( '/' ) );
		return is_array( $target ) && is_array( $home ) && strtolower( (string) ( $target['host'] ?? '' ) ) === strtolower( (string) ( $home['host'] ?? '' ) );
	}

	private function header( $response, string $name ): string {
		if ( function_exists( 'wp_remote_retrieve_header' ) ) {
			return sanitize_text_field( (string) wp_remote_retrieve_header( $response, $name ) );
		}
		return sanitize_text_field( (string) ( $response['headers'][ $name ] ?? '' ) );
	}

	private function report( string $state, int $status, float $elapsed, string $content_type, string $message ): array {
		return array(
			'state'        => $state,
			'http_status'  => $status,
			'duration_ms'  => $elapsed,
			'content_type' => substr( $content_type, 0, 100 ),
			'message'      => $message,
			'checked_at'   => time(),
			'npe_action'   => 'diagnostic_only',
		);
	}

	private function save( array $report ): array {
		update_option( self::OPTION, $report, false );
		return $report;
	}

	private function now(): float {
		return 'microtime' === $this->clock ? microtime( true ) : (float) call_user_func( $this->clock );
	}
}
