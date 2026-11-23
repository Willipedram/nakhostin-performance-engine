<?php
/** Regression tests for laboratory report validation and retention. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Performance\LighthouseHistory;
use Nakhostin\PerformanceEngine\Performance\LighthouseReport;
use PHPUnit\Framework\TestCase;

final class LighthouseReportTest extends TestCase {
	public function test_import_preserves_lab_profile_and_drops_raw_report_data(): void {
		$data = $this->report();
		$row = LighthouseReport::parse( json_encode( $data ), 'https://example.test:8443/', 'baseline' );
		self::assertSame( '/product/', $row['path'] );
		self::assertSame( 'mobile', $row['profile'] );
		self::assertSame( 94.0, $row['score'] );
		self::assertSame( 1300.0, $row['metrics']['lcp_ms'] );
		self::assertSame( array( 'image-delivery-insight' => 180.0, 'unused-javascript' => 40.0 ), $row['opportunities'] );
		self::assertArrayNotHasKey( 'inp', $row['metrics'] );
		self::assertArrayNotHasKey( 'audits', $row );
	}

	public function test_rejects_foreign_private_or_incomplete_measurements(): void {
		$data = $this->report();
		self::assertNull( LighthouseReport::parse( json_encode( $data ), 'https://example.test/', 'baseline' ) );
		$data['finalDisplayedUrl'] .= '?secret=sample';
		self::assertNull( LighthouseReport::parse( json_encode( $data ), 'https://example.test:8443/', 'baseline' ) );
		$data = $this->report();
		unset( $data['audits']['largest-contentful-paint'] );
		self::assertNull( LighthouseReport::parse( json_encode( $data ), 'https://example.test:8443/', 'candidate' ) );
	}

	public function test_history_is_bounded_and_idempotent(): void {
		$history = new LighthouseHistory();
		delete_option( LighthouseHistory::OPTION );
		$row = LighthouseReport::parse( json_encode( $this->report() ), 'https://example.test:8443/', 'candidate' );
		try {
			for ( $i = 0; $i < 32; ++$i ) {
				$row['measured_at'] += 1;
				self::assertTrue( $history->add( $row ) );
			}
			self::assertTrue( $history->add( $row ) );
			self::assertCount( 30, $history->all() );
			self::assertSame( $row, $history->all()[0] );
		} finally {
			delete_option( LighthouseHistory::OPTION );
		}
	}

	public function test_compares_only_matching_configs_with_three_complete_runs_per_phase(): void {
		$history = new LighthouseHistory();
		$baseline = LighthouseReport::parse( json_encode( $this->report() ), 'https://example.test:8443/', 'baseline' );
		$candidate = array_replace( $baseline, array( 'phase' => 'candidate' ) );
		$rows = array( $baseline, $baseline, $baseline, $candidate, $candidate );
		$rows[0]['metrics']['lcp_ms'] = 1400;
		$rows[1]['metrics']['lcp_ms'] = 1600;
		$rows[3]['metrics']['lcp_ms'] = 1500;
		$rows[4]['metrics']['lcp_ms'] = 1000;
		self::assertSame( array(), $history->comparisons( $rows ) );
		$rows[] = array_replace( $candidate, array( 'configuration' => 'other' ) );
		self::assertSame( array(), $history->comparisons( $rows ) );
		$rows[] = $candidate;
		$comparison = $history->comparisons( $rows )[0];
		self::assertSame( 1400, $comparison['baseline']['lcp_ms'] );
		self::assertSame( 1300.0, $comparison['candidate']['lcp_ms'] );
	}

	private function report(): array {
		return array(
			'configSettings'    => array( 'formFactor' => 'mobile' ),
			'finalDisplayedUrl' => 'https://example.test:8443/product/',
			'categories'        => array( 'performance' => array( 'score' => 0.94 ) ),
			'fetchTime'         => '2026-01-01T00:00:00.000Z',
			'lighthouseVersion' => '13.6.0',
			'audits'            => array(
				'largest-contentful-paint' => array( 'numericValue' => 1300 ),
				'cumulative-layout-shift'  => array( 'numericValue' => 0.02 ),
				'total-blocking-time'      => array( 'numericValue' => 90 ),
				'first-contentful-paint'   => array( 'numericValue' => 500 ),
				'speed-index'              => array( 'numericValue' => 600 ),
				'image-delivery-insight'   => array( 'metricSavings' => array( 'LCP' => 180 ) ),
				'unused-javascript'        => array( 'details' => array( 'overallSavingsMs' => 40 ) ),
			),
		);
	}
}
