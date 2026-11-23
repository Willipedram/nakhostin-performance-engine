<?php
/**
 * Extracts bounded, numeric laboratory measurements from a Lighthouse report.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Performance;

final class LighthouseReport {
	public const MAX_BYTES = 4194304;
	private const METRICS = array(
		'lcp_ms' => 'largest-contentful-paint',
		'cls'    => 'cumulative-layout-shift',
		'tbt_ms' => 'total-blocking-time',
		'fcp_ms' => 'first-contentful-paint',
		'si_ms'  => 'speed-index',
	);
	private const OPPORTUNITIES = array(
		'lcp-discovery-insight',
		'render-blocking-insight',
		'image-delivery-insight',
		'font-display-insight',
		'duplicated-javascript-insight',
		'unused-javascript',
		'unused-css-rules',
	);

	public static function parse( string $json, string $site, string $phase ): ?array {
		if ( strlen( $json ) > self::MAX_BYTES || ! in_array( $phase, array( 'baseline', 'candidate' ), true ) ) {
			return null;
		}
		$report = json_decode( $json, true, 48 );
		if ( ! is_array( $report ) || ! empty( $report['runtimeError'] ) ) {
			return null;
		}
		$profile = $report['configSettings']['formFactor'] ?? null;
		$url = $report['finalDisplayedUrl'] ?? $report['finalUrl'] ?? null;
		$score = $report['categories']['performance']['score'] ?? null;
		if ( ! in_array( $profile, array( 'mobile', 'desktop' ), true ) || ! is_string( $url ) || ! self::number( $score, 1 ) || strlen( $url ) > 2048 ) {
			return null;
		}
		$page = wp_parse_url( $url );
		$home = wp_parse_url( $site );
		if ( ! is_array( $page ) || ! is_array( $home ) || ! in_array( $page['scheme'] ?? '', array( 'http', 'https' ), true ) || isset( $page['fragment'] ) || isset( $page['user'] ) || isset( $page['pass'] ) ) {
			return null;
		}
		$default_port = 'https' === $page['scheme'] ? 443 : 80;
		if ( $page['scheme'] !== ( $home['scheme'] ?? '' ) || strtolower( $page['host'] ?? '' ) !== strtolower( $home['host'] ?? '' ) || ( $page['port'] ?? $default_port ) !== ( $home['port'] ?? $default_port ) || isset( $page['query'] ) ) {
			return null;
		}
		$path = $page['path'] ?? '/';
		$site_path = rtrim( $home['path'] ?? '', '/' ) . '/';
		if ( 0 !== strpos( trailingslashit( $path ), $site_path ) || strlen( $path ) > 512 ) {
			return null;
		}

		$metrics = array();
		foreach ( self::METRICS as $name => $audit ) {
			$value = $report['audits'][ $audit ]['numericValue'] ?? null;
			if ( ! self::number( $value, 'cls' === $name ? 100 : 3600000 ) ) {
				return null;
			}
			$metrics[ $name ] = round( $value, 3 );
		}
		$opportunities = array();
		foreach ( self::OPPORTUNITIES as $id ) {
			$audit = $report['audits'][ $id ] ?? array();
			$savings = $audit['metricSavings']['LCP'] ?? $audit['details']['overallSavingsMs'] ?? null;
			if ( self::number( $savings, 3600000 ) && $savings > 0 ) {
				$opportunities[ $id ] = round( $savings );
			}
		}
		arsort( $opportunities, SORT_NUMERIC );
		$version = $report['lighthouseVersion'] ?? null;
		$timestamp = $report['fetchTime'] ?? null;
		$recorded = is_string( $timestamp ) && strlen( $timestamp ) <= 40 ? strtotime( $timestamp ) : false;
		if ( ! is_string( $version ) || ! preg_match( '/^\d{1,3}\.\d{1,3}\.\d{1,3}$/D', $version ) || false === $recorded || $recorded <= 0 || $recorded > time() + 3600 ) {
			return null;
		}

		return array(
			'schema'        => 1,
			'engine'        => $version,
			'configuration' => hash( 'sha256', (string) wp_json_encode( $report['configSettings'] ) ),
			'profile'       => $profile,
			'phase'         => $phase,
			'path'          => $path,
			'measured_at'   => $recorded,
			'score'         => round( 100 * $score, 1 ),
			'metrics'       => $metrics,
			'opportunities' => array_slice( $opportunities, 0, 5, true ),
		);
	}

	private static function number( $value, float $maximum ): bool {
		return ( is_float( $value ) || is_int( $value ) ) && is_finite( (float) $value ) && $value >= 0 && $value <= $maximum;
	}
}
