<?php
/** Regression tests for explicit rule approvals. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\CSS\ApprovedCSSRules;
use PHPUnit\Framework\TestCase;

final class ApprovedCSSRulesTest extends TestCase {
	public function test_prunes_only_a_complete_top_level_class_rule(): void {
		$kept = '.old:hover{display:block}.old,.menu{display:flex}@media (min-width:10px){.old{display:block}}.live{content:"}"}';
		self::assertSame( $kept, ApprovedCSSRules::prune( '.old{display:none}' . $kept, array( '.old' ) ) );
		self::assertSame( '.old{display:none', ApprovedCSSRules::prune( '.old{display:none', array( '.old' ) ) );
	}
}
