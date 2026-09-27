<?php
/** Bounded storage for explicitly enabled hook profiles. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Performance;
final class HookProfileStorage {
	public const OPTION = 'npe_hook_profiles';
	public function save( HookProfileSample $sample ): void { $items = get_option( self::OPTION, array() ); $items = is_array( $items ) ? $items : array(); $items[] = array( 'timestamp' => time(), 'hooks' => $sample->to_array() ); update_option( self::OPTION, array_slice( $items, -25 ), false ); }
	public function all(): array { $items = get_option( self::OPTION, array() ); return is_array( $items ) ? $items : array(); }
}
