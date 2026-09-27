<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\DOM\DOMComplexityAnalyzer;
use PHPUnit\Framework\TestCase;
final class DOMComplexityAnalyzerTest extends TestCase { public function test_reports_structure_without_mutating_markup(): void { $html = '<main><div class="elementor-widget"><span></span></div><div class="wd-product"></div></main>'; $result = ( new DOMComplexityAnalyzer() )->analyze( $html ); self::assertGreaterThanOrEqual( 5, $result['nodes'] ); self::assertSame( 1, $result['elementor_components'] ); self::assertSame( 1, $result['woodmart_components'] ); } }
