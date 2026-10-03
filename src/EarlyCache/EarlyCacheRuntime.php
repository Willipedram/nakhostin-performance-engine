<?php
/** Standalone advanced-cache.php hit runtime. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\EarlyCache;
final class EarlyCacheRuntime {
	private $config;
	public function __construct( array $config ) { $this->config = $config; }
	public function lookup( EarlyCacheRequest $request, ?int $now = null ): EarlyCacheResponse {
		if ( empty( $this->config['enabled'] ) ) { return new EarlyCacheResponse( 'BYPASS' ); }
		$reason = ( new EarlyCachePolicy( (array) ( $this->config['excluded_paths'] ?? array() ) ) )->classify( $request );
		if ( 'public' !== $reason ) { return new EarlyCacheResponse( 'BYPASS' ); }
		$key = ( new EarlyCacheKeyGenerator() )->generate( $request, $this->config );
		if ( empty( $key['cacheable'] ) ) { return new EarlyCacheResponse( 'BYPASS' ); }
		return ( new EarlyCacheReader( (string) $this->config['cache_directory'] ) )->read( $key['key'], $now );
	}
	public function serve( EarlyCacheRequest $request ): bool {
		$response = $this->lookup( $request ); if ( ! $response->is_servable() ) { return false; }
		foreach ( $response->headers() as $name => $value ) { if ( ! headers_sent() && preg_match( '/^[A-Za-z0-9-]+$/', (string) $name ) ) { header( $name . ': ' . str_replace( array( "\r", "\n" ), '', (string) $value ), true ); } }
		if ( 'HEAD' !== $request->method() ) { echo $response->content(); }
		return true;
	}
}
