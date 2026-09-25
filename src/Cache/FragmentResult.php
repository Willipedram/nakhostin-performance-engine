<?php
/**
 * Fragment lookup result preserving cached null/false values.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class FragmentResult {
	/** @var string */ private $status;
	/** @var mixed */ private $value;
	/** @var FragmentEntry|null */ private $entry;

	public function __construct( string $status, $value = null, ?FragmentEntry $entry = null ) {
		$this->status = $status;
		$this->value  = $value;
		$this->entry  = $entry;
	}

	public function is_hit(): bool { return 'hit' === $this->status; }
	public function status(): string { return $this->status; }
	public function value() { return $this->value; }
	public function entry(): ?FragmentEntry { return $this->entry; }
}
