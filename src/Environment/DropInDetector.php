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
		if ( ! is_file( $path ) ) { return array( 'exists' => false, 'owner' => 'none', 'npe_owned' => false, 'path' => $path ); }
		$contents = is_readable( $path ) ? file_get_contents( $path, false, null, 0, 131072 ) : false;
		if ( ! is_string( $contents ) ) { return array( 'exists' => true, 'owner' => 'unknown', 'npe_owned' => false, 'path' => $path ); }
		$owners = array( 'npe' => self::MARKER, 'litespeed' => 'LITESPEED', 'wp_rocket' => 'WP ROCKET', 'w3_total_cache' => 'W3TC', 'wp_super_cache' => 'WPCACHEHOME' );
		foreach ( $owners as $owner => $marker ) { if ( false !== stripos( $contents, $marker ) ) { return array( 'exists' => true, 'owner' => $owner, 'npe_owned' => 'npe' === $owner, 'path' => $path ); } }
		return array( 'exists' => true, 'owner' => 'external', 'npe_owned' => false, 'path' => $path );
	}
	public function can_install(): bool {
		$state = $this->detect();
		return $state['npe_owned'] || ( ! $state['exists'] && is_dir( $this->content_directory ) && is_writable( $this->content_directory ) );
	}
}
