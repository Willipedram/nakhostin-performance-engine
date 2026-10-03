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
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/tools/build-php-translations.php' );
	}
	public function test_text_php_catalog_is_deployable_without_a_binary_mo_file(): void {
		$catalog = require dirname( __DIR__, 2 ) . '/languages/nakhostin-performance-engine-fa_IR.l10n.php';
		$this->assertSame( 'fa_IR', $catalog['language'] );
		$this->assertSame( 'مرکز کنترل امن و سنجش‌پذیر عملکرد وردپرس.', $catalog['messages']['A safe, measurable performance control center for WordPress.'] );
		$this->assertSame( 'نرخ برخورد موفق با کش', $catalog['messages']['Cache hit ratio'] );
		$this->assertSame( 'دسترسی سریع', $catalog['messages']['Quick navigation'] );
		$this->assertSame( 'کاهش حجم CSS', $catalog['messages']['CSS reduction'] );
		$this->assertSame( 'تحلیل منابع صفحه', $catalog['messages']['Analyze Page Assets'] );
	}
	public function test_rtl_styles_preserve_technical_values_as_ltr(): void { $css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/admin/admin-rtl.css' ); $this->assertStringContainsString( 'direction: rtl', $css ); $this->assertStringContainsString( '.npe-technical', $css ); $this->assertStringContainsString( 'unicode-bidi: isolate', $css ); }
}
