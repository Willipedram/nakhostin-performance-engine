<?php
/**
 * Same-origin cache warmup HTTP client.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

use Nakhostin\PerformanceEngine\Contracts\CacheWarmupInterface;

final class CacheWarmer implements CacheWarmupInterface {
	public const HOOK = 'npe/cache/run_warmup';

	/** @var URLNormalizer */
	private $normalizer;

	public function __construct( URLNormalizer $normalizer ) {
		$this->normalizer = $normalizer;
	}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	public function schedule( array $urls ): bool {
		$urls = $this->safe_urls( $urls );
		if ( empty( $urls ) || wp_next_scheduled( self::HOOK ) ) {
			return ! empty( $urls );
		}
		return false !== wp_schedule_single_event( time() + 5, self::HOOK, array( $urls ) );
	}

	public function cancel(): bool {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( CacheWarmupManager::CRON_HOOK );
		return true;
	}

	public function run( array $urls ): void {
		foreach ( $this->safe_urls( $urls ) as $url ) {
			$this->warm( $url );
		}
	}

	public function warm( string $url ): array {
		$urls = $this->safe_urls( array( $url ) );
		if ( empty( $urls ) ) {
			return array( 'success' => false, 'url' => '', 'error' => 'unsafe_url' );
		}
		$response = wp_safe_remote_get(
			$urls[0],
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'headers'     => array( 'X-NPE-Warmup' => '1' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'url' => $urls[0], 'error' => $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		return array( 'success' => $status >= 200 && $status < 400, 'url' => $urls[0], 'status' => $status, 'error' => $status >= 400 ? 'http_error' : '' );
	}

	private function safe_urls( array $urls ): array {
		$safe      = array();
		$home_host = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		foreach ( $urls as $url ) {
			$result = $this->normalizer->normalize( (string) $url, new QueryPolicy() );
			if ( $result['cacheable'] && $home_host === strtolower( (string) wp_parse_url( $result['url'], PHP_URL_HOST ) ) ) {
				$safe[] = $result['url'];
			}
		}
		return array_slice( array_values( array_unique( $safe ) ), 0, 100 );
	}
}
