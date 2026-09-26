<?php
/** Explicitly enabled application-level WordPress cache runtime. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class PageCacheRuntime {
	/** @var PageCache */ private $cache; /** @var CacheRequestFactory */ private $requests; /** @var CacheHeaderManager */ private $headers; /** @var CacheRequest|null */ private $request; /** @var CacheDependencyCollector|null */ private $dependencies;
	public function __construct( PageCache $cache, CacheRequestFactory $requests, CacheHeaderManager $headers, ?CacheDependencyCollector $dependencies = null ) { $this->cache = $cache; $this->requests = $requests; $this->headers = $headers; $this->dependencies = $dependencies; }
	public function register(): void { add_action( 'template_redirect', array( $this, 'maybe_serve' ), -999 ); }
	public function maybe_serve(): void {
		try { $this->request = $this->requests->from_globals(); $lookup = $this->cache->lookup( $this->request ); } catch ( \Throwable $error ) { do_action( 'npe/cache/runtime_error', 'lookup' ); return; }
		if ( $lookup->is_hit() && $lookup->entry() ) { foreach ( $this->headers->for_lookup( $lookup ) as $name => $value ) { if ( ! headers_sent() ) { header( $name . ': ' . $value, true ); } } if ( 'HEAD' !== $this->request->method() ) { echo $lookup->entry()->content(); } exit; }
		if ( 'miss' === $lookup->status() ) { ob_start( array( $this, 'capture' ) ); }
	}
	public function capture( string $content ): string { if ( $this->request ) { try { $headers = array(); foreach ( headers_list() as $line ) { $parts = explode( ':', $line, 2 ); if ( 2 === count( $parts ) ) { $headers[ trim( $parts[0] ) ] = trim( $parts[1] ); } } $dependencies = $this->dependencies ? $this->dependencies->collect() : array(); $this->cache->store( $this->request, $content, $headers, http_response_code() ?: 200, $dependencies ); } catch ( \Throwable $error ) { do_action( 'npe/cache/runtime_error', 'store' ); } } return $content; }
}
