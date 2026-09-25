<?php
/**
 * Immutable, serializable CSS usage manifest.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\CSS;

final class CSSManifest {
	/** @var array<string, mixed> */
	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function to_array(): array {
		return $this->data;
	}
}
