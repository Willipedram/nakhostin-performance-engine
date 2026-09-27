<?php
/**
 * Selects persistent object cache when present, otherwise filesystem fallback.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class FragmentStoreFactory {
	/** @var ObjectCacheDetector */
	private $detector;

	/** @var string */
	private $fallback_directory;

	public function __construct( ObjectCacheDetector $detector, string $fallback_directory ) {
		$this->detector           = $detector;
		$this->fallback_directory = $fallback_directory;
	}

	public function create(): FragmentStoreInterface {
		$capabilities = $this->detector->detect();
		if ( $capabilities['available'] && $capabilities['persistent'] ) {
			return new WordPressObjectCacheFragmentStore();
		}

		return new FilesystemFragmentStore( $this->fallback_directory );
	}
}
