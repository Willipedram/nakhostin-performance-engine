<?php
/** Detects advanced-cache.php ownership without executing it. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;

final class DropInDetector {
	public const MARKER = 'NPE_ADVANCED_CACHE_DROPIN';
	/** @var string */ private $content_directory;

	public function __construct( string $content_directory ) { $this->content_directory = rtrim( $content_directory, '/\\' ); }
	public function path(): string { return $this->content_directory . '/advanced-cache.php'; }

	public function detect(): array {
		$path = $this->path();
		if ( ! is_file( $path ) ) { return $this->result( false, 'none', false, $path, 'missing' ); }
		$contents = is_readable( $path ) ? file_get_contents( $path, false, null, 0, 131072 ) : false;
		if ( ! is_string( $contents ) ) { return $this->result( true, 'unknown', false, $path, 'unreadable' ); }

		if ( false !== stripos( $contents, self::MARKER ) ) {
			return $this->result( true, 'npe', true, $path, 'ownership-marker' );
		}

		// Releases predating the ownership marker used both of these unique NPE
		// runtime signatures. Requiring the pair avoids adopting a foreign file
		// merely because it mentions this plugin in a comment or compatibility rule.
		$legacy_npe = false !== stripos( $contents, 'Nakhostin\\PerformanceEngine\\EarlyCache\\EarlyCacheRuntime' )
			&& false !== stripos( $contents, 'cache/nakhostin-performance-engine/early-config.json' );
		if ( $legacy_npe ) {
			return $this->result( true, 'npe', true, $path, 'legacy-npe-signatures' );
		}

		$owners = array(
			'litespeed'       => array( 'LITESPEED' ),
			'wp_rocket'       => array( 'WP ROCKET' ),
			'w3_total_cache'  => array( 'W3TC' ),
			'wp_super_cache'  => array( 'WPCACHEHOME' ),
			'cache_enabler'   => array( 'CACHE ENABLER', 'CACHE_ENABLER' ),
		);
		foreach ( $owners as $owner => $markers ) {
			foreach ( $markers as $marker ) {
				if ( false !== stripos( $contents, $marker ) ) { return $this->result( true, $owner, false, $path, 'known-owner-marker' ); }
			}
		}
		return $this->result( true, 'external', false, $path, 'unrecognized-file' );
	}

	public function can_install(): bool {
		$state = $this->detect();
		return $state['npe_owned'] || ( ! $state['exists'] && is_dir( $this->content_directory ) && is_writable( $this->content_directory ) );
	}

	private function result( bool $exists, string $owner, bool $npe_owned, string $path, string $reason ): array {
		return array( 'exists' => $exists, 'owner' => $owner, 'npe_owned' => $npe_owned, 'path' => $path, 'reason' => $reason );
	}
}
