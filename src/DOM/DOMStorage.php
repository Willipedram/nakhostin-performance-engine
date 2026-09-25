<?php
/**
 * Persist only the latest compact DOM manifest.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMStorage implements DOMStorageInterface {
	public const OPTION = 'npe_dom_manifest';

	public function save( DOMManifest $manifest ): bool {
		return update_option( self::OPTION, $manifest->to_array(), false );
	}

	public function latest(): ?DOMManifest {
		$data = get_option( self::OPTION, array() );

		return is_array( $data ) && ! empty( $data['dom_signature'] ) ? new DOMManifest( $data ) : null;
	}

	public function delete(): bool {
		return delete_option( self::OPTION );
	}
}
