<?php
/**
 * Latest JavaScript analysis manifest storage.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class JavaScriptStorage {
	public const OPTION = 'npe_javascript_manifest';

	public function save( JavaScriptManifest $manifest ): bool {
		return update_option( self::OPTION, $manifest->to_array(), false );
	}

	public function latest(): ?JavaScriptManifest {
		$data = get_option( self::OPTION, array() );

		return is_array( $data ) && ! empty( $data['signature'] ) ? new JavaScriptManifest( $data ) : null;
	}

	public function invalidate(): bool {
		return delete_option( self::OPTION );
	}
}
