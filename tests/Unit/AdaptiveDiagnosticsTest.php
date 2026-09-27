<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Diagnostics\BFCacheAnalyzer;
use Nakhostin\PerformanceEngine\Diagnostics\DiagnosticsExporter;
use Nakhostin\PerformanceEngine\Diagnostics\OptimizationConflictDetector;
use Nakhostin\PerformanceEngine\Security\SecurityHeaderAnalyzer;
use PHPUnit\Framework\TestCase;
final class AdaptiveDiagnosticsTest extends TestCase {
	public function test_public_no_store_is_investigated_but_checkout_is_expected(): void { $analyzer = new BFCacheAnalyzer(); self::assertSame( 'investigate', $analyzer->analyze( array( 'Cache-Control' => 'no-store' ), 'public' )['status'] ); self::assertSame( 'expected', $analyzer->analyze( array( 'Cache-Control' => 'private, no-store' ), 'checkout' )['status'] ); }
	public function test_security_headers_distinguish_report_only(): void { $report = ( new SecurityHeaderAnalyzer() )->analyze( array( 'Content-Security-Policy-Report-Only' => "default-src 'self'", 'Strict-Transport-Security' => 'max-age=60' ) ); self::assertSame( 'report_only', $report->get( 'csp' )['status'] ); self::assertTrue( $report->get( 'hsts' )['present'] ); }
	public function test_export_removes_sensitive_fields(): void { $export = ( new DiagnosticsExporter() )->export( array( 'server' => array( 'type' => 'nginx', 'token' => 'secret' ), 'performance' => array( 'query_count' => 4, 'full_sql' => 'SELECT secret' ) ) ); self::assertSame( 'nginx', $export['server']['type'] ); self::assertArrayNotHasKey( 'token', $export['server'] ); self::assertArrayNotHasKey( 'full_sql', $export['performance'] ); }
	public function test_conflicts_are_explained_not_mutated(): void { $conflicts = ( new OptimizationConflictDetector() )->detect( array( 'autoptimize/autoptimize.php' ), array( 'javascript' => true ) ); self::assertSame( 'javascript', $conflicts[0]['feature'] ); self::assertStringContainsString( 'will not disable', $conflicts[0]['action'] ); }
}
