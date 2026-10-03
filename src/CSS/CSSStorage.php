<?php
/**
 * Store only compact CSS analysis manifests, never submitted stylesheet source.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class CSSStorage {
	public const OPTION = 'npe_css_manifest';

	public function save( CSSManifest $manifest ): bool {
		return update_option( self::OPTION, $manifest->to_array(), false );
	}

	public function latest(): ?CSSManifest {
		$data = get_option( self::OPTION, null );

		return is_array( $data ) ? new CSSManifest( $data ) : null;
	}
}
