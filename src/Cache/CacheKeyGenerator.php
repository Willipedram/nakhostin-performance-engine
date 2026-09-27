<?php
/**
 * Creates bounded, deterministic page-cache keys.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheKeyGenerator {
	/** @var URLNormalizer */ private $normalizer;
	/** @var QueryPolicy */ private $query_policy;
	/** @var array */ private $configuration;

	public function __construct( URLNormalizer $normalizer, QueryPolicy $query_policy, array $configuration = array() ) {
		$this->normalizer    = $normalizer;
		$this->query_policy  = $query_policy;
		$this->configuration = $configuration;
	}

	/** @return array{cacheable:bool,key:string,url:string,rejected:array,dimensions:array} */
	public function generate( CacheRequest $request ): array {
		$normalized = $this->normalizer->normalize( $request->url(), $this->query_policy );
		$dimensions = array(
			'site'     => max( 1, (int) $request->context_value( 'site_id', 1 ) ),
			'language' => substr( sanitize_key( (string) $request->context_value( 'language', 'default' ) ), 0, 20 ),
		);
		if ( ! empty( $this->configuration['vary_device'] ) ) {
			$device = (string) $request->context_value( 'device', 'desktop' );
			$dimensions['device'] = in_array( $device, array( 'desktop', 'tablet', 'mobile' ), true ) ? $device : 'desktop';
		}
		if ( ! empty( $this->configuration['vary_currency'] ) ) {
			$currency = strtoupper( preg_replace( '/[^A-Z]/', '', (string) $request->context_value( 'currency', '' ) ) );
			if ( '' !== $currency ) {
				$dimensions['currency'] = substr( $currency, 0, 8 );
			}
		}
		$allowed_variations = isset( $this->configuration['variation_dimensions'] ) && is_array( $this->configuration['variation_dimensions'] ) ? $this->configuration['variation_dimensions'] : array();
		$variations         = $request->context_value( 'variations', array() );
		if ( is_array( $variations ) ) {
			foreach ( $allowed_variations as $name ) {
				$name = sanitize_key( (string) $name );
				if ( isset( $variations[ $name ] ) && is_scalar( $variations[ $name ] ) ) {
					$dimensions['variation:' . $name] = substr( sanitize_text_field( (string) $variations[ $name ] ), 0, 64 );
				}
			}
		}
		ksort( $dimensions, SORT_STRING );
		$payload = array( 'version' => 1, 'url' => $normalized['url'], 'dimensions' => $dimensions );

		return array(
			'cacheable'  => $normalized['cacheable'],
			'key'        => hash( 'sha256', (string) wp_json_encode( $payload ) ),
			'url'        => $normalized['url'],
			'rejected'   => $normalized['rejected'],
			'dimensions' => $dimensions,
		);
	}
}
