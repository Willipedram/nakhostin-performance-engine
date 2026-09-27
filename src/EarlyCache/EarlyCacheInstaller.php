<?php
/** Atomic ownership-safe advanced-cache.php installer. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
use Nakhostin\PerformanceEngine\Environment\DropInDetector;

final class EarlyCacheInstaller {
	private $detector; private $template; private $plugin_path;
	public function __construct( DropInDetector $detector, string $template, string $plugin_path ) { $this->detector = $detector; $this->template = $template; $this->plugin_path = rtrim( $plugin_path, '/\\' ) . '/'; }
	public function install(): array {
		$state = $this->detector->detect();
		if ( $state['exists'] && ! $state['npe_owned'] ) { return array( 'success' => false, 'status' => 'external_dropin', 'owner' => $state['owner'] ); }
		if ( ! is_readable( $this->template ) || ! is_dir( dirname( $state['path'] ) ) || ! is_writable( dirname( $state['path'] ) ) ) { return array( 'success' => false, 'status' => 'unwritable', 'owner' => $state['owner'] ); }
		$contents = file_get_contents( $this->template ); if ( ! is_string( $contents ) ) { return array( 'success' => false, 'status' => 'template_unreadable', 'owner' => 'none' ); }
		$contents = str_replace( '__NPE_PLUGIN_PATH__', var_export( $this->plugin_path, true ), $contents );
		$temp = tempnam( dirname( $state['path'] ), '.npe-dropin-' );
		$written = false !== $temp && false !== file_put_contents( $temp, $contents, LOCK_EX ) && rename( $temp, $state['path'] );
		if ( false !== $temp && file_exists( $temp ) ) { unlink( $temp ); }
		$verified = $written && is_readable( $state['path'] ) && hash_equals( hash( 'sha256', $contents ), hash_file( 'sha256', $state['path'] ) );
		if ( ! $verified && $written && $this->detector->detect()['npe_owned'] ) { unlink( $state['path'] ); }
		return array( 'success' => $verified, 'status' => $verified ? 'installed' : 'verification_failed', 'owner' => $verified ? 'npe' : 'none' );
	}
	public function remove(): bool { $state = $this->detector->detect(); return ! $state['exists'] || ( $state['npe_owned'] && unlink( $state['path'] ) ); }
}
