<?php
/**
 * Privacy-safe JavaScript analysis manifest.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class JavaScriptManifest {
	/** @var array<string, mixed> */
	private $data;

	public function __construct( array $data ) {
		$this->data = $data;
	}

	public function signature(): string {
		return (string) $this->data['signature'];
	}

	public function to_array(): array {
		return $this->data;
	}
}
