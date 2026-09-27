<?php
/** Immutable page-cache ownership decision. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
final class CacheRuntimeMode {
	public const NPE_EARLY = 'npe_early'; public const NPE_APPLICATION = 'npe_application'; public const LITESPEED = 'litespeed'; public const EXTERNAL = 'external'; public const DISABLED = 'disabled'; public const CONFLICT = 'conflict';
	/** @var string */ private $owner; /** @var string */ private $reason;
	public function __construct( string $owner, string $reason ) { $allowed = array( self::NPE_EARLY, self::NPE_APPLICATION, self::LITESPEED, self::EXTERNAL, self::DISABLED, self::CONFLICT ); $this->owner = in_array( $owner, $allowed, true ) ? $owner : self::DISABLED; $this->reason = $reason; }
	public function owner(): string { return $this->owner; }
	public function reason(): string { return $this->reason; }
	public function is( string $owner ): bool { return $this->owner === $owner; }
}
