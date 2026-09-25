<?php
/** Page-cache lookup result. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class CacheLookup {
	/** @var string */ private $status; /** @var string */ private $reason; /** @var CacheEntry|null */ private $entry; /** @var string */ private $key; /** @var string */ private $url;
	public function __construct( string $status, string $reason, ?CacheEntry $entry = null, string $key = '', string $url = '' ) { $this->status = $status; $this->reason = $reason; $this->entry = $entry; $this->key = $key; $this->url = $url; }
	public function is_hit(): bool { return in_array( $this->status, array( 'hit', 'stale' ), true ); }
	public function status(): string { return $this->status; }
	public function reason(): string { return $this->reason; }
	public function entry(): ?CacheEntry { return $this->entry; }
	public function key(): string { return $this->key; }
	public function url(): string { return $this->url; }
}
