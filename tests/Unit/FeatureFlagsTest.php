<?php
/**
 * Feature flag safety tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Core\FeatureFlags;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use PHPUnit\Framework\TestCase;

final class FeatureFlagsTest extends TestCase {
	protected function setUp(): void {
		$GLOBALS['npe_test_options'] = array(
			Settings::OPTION => array( 'css' => array( 'enabled' => true ) ),
		);
	}

	public function test_unavailable_feature_cannot_be_enabled(): void {
		$flags = new FeatureFlags( new Settings() );

		$this->assertFalse( $flags->is_available( 'css' ) );
		$this->assertFalse( $flags->is_enabled( 'css' ) );
	}

	public function test_available_feature_honors_validated_setting(): void {
		$flags = new FeatureFlags( new Settings(), array( 'css' => true ) );

		$this->assertTrue( $flags->is_available( 'css' ) );
		$this->assertTrue( $flags->is_enabled( 'css' ) );
		$this->assertFalse( $flags->is_enabled( 'unknown' ) );
	}
}
