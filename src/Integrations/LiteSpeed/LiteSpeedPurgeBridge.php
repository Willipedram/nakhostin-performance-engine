<?php
/**
 * Forwards scoped NPE invalidation through LiteSpeed's public purge actions.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;

final class LiteSpeedPurgeBridge {
	public function register(): void {
		add_action( 'npe/cache/purged', array( $this, 'forward' ), 10, 2 );
	}

	public function forward( string $scope, array $values ): void {
		if ( 'all' === $scope ) {
			// Public LiteSpeed purge action; used only for an actual full NPE purge.
			do_action( 'litespeed_purge_all' );
			return;
		}
		if ( 'urls' === $scope ) {
			foreach ( array_values( array_unique( $values ) ) as $url ) {
				$url = esc_url_raw( (string) $url );
				if ( '' !== $url ) {
					do_action( 'litespeed_purge_url', $url );
				}
			}
			return;
		}
		if ( 'dependencies' === $scope ) {
			foreach ( array_values( array_unique( array_filter( array_map( 'sanitize_key', $values ) ) ) ) as $tag ) {
				do_action( 'litespeed_purge_tag', 'npe-' . $tag );
			}
		}
	}
}
