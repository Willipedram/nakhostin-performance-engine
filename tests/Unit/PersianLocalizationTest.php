<?php
/** Persian catalog and RTL presentation tests. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class PersianLocalizationTest extends TestCase {
	public function test_persian_catalog_contains_professional_core_terms(): void {
		$catalog = (string) file_get_contents( dirname( __DIR__, 2 ) . '/languages/nakhostin-performance-engine-fa_IR.po' );
		$this->assertStringContainsString( 'برخورد موفق با کش', $catalog );
		$this->assertStringContainsString( 'عدم وجود در کش', $catalog );
		$this->assertStringContainsString( 'پیش‌گرم‌سازی کش', $catalog );
		$this->assertStringContainsString( 'پاک‌سازی هوشمند', $catalog );
		$this->assertStringContainsString( 'مانیفست ساختار DOM', $catalog );
		$this->assertStringContainsString( 'بسته منابع', $catalog );
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/tools/build-translations.php' );
	}
	public function test_rtl_styles_preserve_technical_values_as_ltr(): void { $css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/admin/admin-rtl.css' ); $this->assertStringContainsString( 'direction: rtl', $css ); $this->assertStringContainsString( '.npe-technical', $css ); $this->assertStringContainsString( 'unicode-bidi: isolate', $css ); }
}
