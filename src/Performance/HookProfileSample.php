<?php
/** Debug-only bounded hook timing value object. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;
final class HookProfileSample {
	/** @var array */ private $durations;
	public function __construct( array $durations ) { $this->durations = array(); foreach ( array_slice( $durations, 0, 20, true ) as $hook => $milliseconds ) { $this->durations[ sanitize_key( $hook ) ] = round( max( 0, (float) $milliseconds ), 3 ); } }
	public function to_array(): array { return $this->durations; }
}
