<?php
/**
 * Ephemeral rendered-document input.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

use InvalidArgumentException;

final class DOMSnapshot {
	public const MAX_BYTES = 2097152;

	/** @var string */
	private $html;

	/** @var array<string, mixed> */
	private $context;

	public function __construct( string $html, array $context = array() ) {
		if ( strlen( $html ) > self::MAX_BYTES ) {
			throw new InvalidArgumentException( 'DOM snapshots cannot exceed 2 MiB.' );
		}

		$this->html    = $html;
		$this->context = $context;
	}

	public function html(): string {
		return $this->html;
	}

	public function context(): array {
		return $this->context;
	}
}
