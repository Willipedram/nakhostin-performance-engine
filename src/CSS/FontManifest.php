<?php
/** Immutable per-page font usage manifest. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\CSS;

final class FontManifest {
	/** @var array<string, mixed> */ private $data;
	public function __construct( array $data ) { $this->data = $data; }
	public function to_array(): array { return $this->data; }
}
