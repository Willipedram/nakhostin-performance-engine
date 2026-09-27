<?php
/** Central read-only runtime capability detector. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Environment;

final class EnvironmentDetector {
	/** @var string */ private $content_directory;
	/** @var ServerDetector */ private $servers;
	/** @var DropInDetector */ private $dropins;
	public function __construct( string $content_directory, ?ServerDetector $servers = null, ?DropInDetector $dropins = null ) { $this->content_directory = rtrim( $content_directory, '/\\' ); $this->servers = $servers ?: new ServerDetector(); $this->dropins = $dropins ?: new DropInDetector( $content_directory ); }
	public function detect( ?array $server = null ): EnvironmentReport {
		$server = $server ?? $_SERVER;
		$dropin = $this->dropins->detect();
		$cache = ( new CacheCapabilityDetector() )->detect( $this->content_directory, $dropin );
		$compression = ( new CompressionDetector() )->detect( $server );
		$proxy = ( new ReverseProxyDetector() )->detect( $server );
		$type = $this->servers->detect( $server );
		$owner = $dropin['exists'] ? ( $dropin['npe_owned'] ? 'npe_early' : $dropin['owner'] ) : 'none';
		$installable = $this->dropins->can_install() || $dropin['npe_owned'];
		return new EnvironmentReport( array_merge( $cache, $compression, array( 'server' => $type, 'reverse_proxy' => $proxy, 'dropin' => $dropin, 'early_cache_installable' => $installable, 'supports_early_cache' => ! empty( $cache['wp_cache'] ) && $installable, 'page_cache_owner' => $owner ) ) );
	}
}
