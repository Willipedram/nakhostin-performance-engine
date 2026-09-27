<?php
/** Safe cache response headers. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class CacheHeaderManager {
	public function for_lookup( CacheLookup $lookup, ?int $now = null ): array {
		$entry = $lookup->entry(); if ( ! $entry ) { return array( 'X-NPE-Cache' => strtoupper( $lookup->status() ) ); }
		$now = $now ?? time(); $headers = $entry->headers(); $headers['X-NPE-Cache'] = strtoupper( $lookup->status() ); $headers['Age'] = (string) max( 0, $now - $entry->created_at() );
		$headers['Cache-Control'] = 'public, max-age=' . max( 0, $entry->expires_at() - $now );
		return $headers;
	}
}
