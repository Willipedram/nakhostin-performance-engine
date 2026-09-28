<?php
/** Font-display rewriting tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\CSS\FontDisplayOptimizer;
use PHPUnit\Framework\TestCase;
final class FontDisplayOptimizerTest extends TestCase {
	public function test_adds_swap_when_font_display_is_missing(): void { $css = ( new FontDisplayOptimizer() )->rewrite( '@font-face{font-family:x;src:url(x.woff2)}' ); self::assertStringContainsString( 'font-display:swap', $css ); }
	public function test_replaces_block_but_preserves_explicit_optional(): void { $optimizer = new FontDisplayOptimizer(); self::assertStringContainsString( 'font-display:swap', $optimizer->rewrite( '@font-face{font-display:block!important;src:url(x.woff)}' ) ); self::assertStringContainsString( 'font-display:optional', $optimizer->rewrite( '@font-face{font-display:optional;src:url(x.woff2)}' ) ); }
}
