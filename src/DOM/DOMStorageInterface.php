<?php
/**
 * DOM manifest persistence boundary.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

interface DOMStorageInterface {
	public function save( DOMManifest $manifest ): bool;

	public function latest(): ?DOMManifest;

	public function delete(): bool;
}
