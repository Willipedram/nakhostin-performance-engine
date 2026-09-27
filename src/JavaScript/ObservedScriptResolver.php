<?php
/** Match rendered script source paths to WordPress script handles. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class ObservedScriptResolver {
	public function resolve( array $assets, array $scripts ): array {
		$paths = array();
		foreach ( $scripts as $script ) {
			$source = is_array( $script ) ? (string) ( $script['src'] ?? '' ) : '';
			$path   = wp_parse_url( $source, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$paths[ '/' . ltrim( $path, '/' ) ] = true;
			}
		}

		$handles = array();
		foreach ( $assets as $asset ) {
			if ( ! $asset instanceof ScriptAsset ) {
				continue;
			}
			$data = $asset->to_array();
			if ( $data['enqueued'] && '' === $asset->source() && $asset->has_runtime_data() ) {
				$handles[] = $asset->handle();
				continue;
			}
			$path = wp_parse_url( $asset->source(), PHP_URL_PATH );
			if ( is_string( $path ) && isset( $paths[ '/' . ltrim( $path, '/' ) ] ) ) {
				$handles[] = $asset->handle();
			}
		}

		return array_values( array_unique( $handles ) );
	}
}
