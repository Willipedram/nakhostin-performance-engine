<?php
/**
 * Public/private request classification result.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheDecision {
	/** @var bool */ private $cacheable;
	/** @var string */ private $reason;

	public function __construct( bool $cacheable, string $reason ) {
		$this->cacheable = $cacheable;
		$this->reason    = sanitize_key( $reason );
	}

	public function is_cacheable(): bool { return $this->cacheable; }
	public function reason(): string { return $this->reason; }
}
