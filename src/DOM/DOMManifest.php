<?php
/**
 * Serializable DOM usage manifest.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

final class DOMManifest {
	/** @var array<string, mixed> */
	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function signature(): string {
		return (string) $this->data['dom_signature'];
	}

	public function to_array(): array {
		return $this->data;
	}
}
