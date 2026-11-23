<?php
/** Tests atomic updates of the WordPress bootstrap configuration. @package NakhostinPerformanceEngine */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\EarlyCache\WpCacheConfiguration;
use PHPUnit\Framework\TestCase;

final class WpCacheConfigurationTest extends TestCase {
	public function test_adds_constant_before_bootstrap_and_is_idempotent(): void {
		$source = "<?php\r\n// WP_CACHE is optional.\r\nrequire_once ABSPATH . 'wp-settings.php';\r\n";
		$this->check_update(
			$source,
			"<?php\r\n// WP_CACHE is optional.\r\ndefine( 'WP_CACHE', true );\r\n\r\nrequire_once ABSPATH . 'wp-settings.php';\r\n"
		);
	}

	public function test_updates_a_literal_false_without_changing_other_bytes(): void {
		$this->check_update( "<?php\ndefine('WP_CACHE', FALSE);\nrequire_once ABSPATH . 'wp-settings.php';\n", "<?php\ndefine('WP_CACHE', true);\nrequire_once ABSPATH . 'wp-settings.php';\n" );
	}

	public function test_preserves_managed_and_ambiguous_configuration(): void {
		foreach ( array(
			"define('WP_CACHE', getenv('WP_CACHE') === '1');",
			"if (getenv('WP_CACHE')) define('WP_CACHE', true);",
			"require '/managed/config.php';",
			"define('WP_CACHE', false);\ndefine('WP_CACHE', false);",
		) as $managed ) {
			$this->check_update( "<?php\n" . $managed . "\nrequire_once ABSPATH . 'wp-settings.php';\n", null );
		}
	}

	private function check_update( string $source, ?string $expected ): void {
		$path = tempnam( sys_get_temp_dir(), 'npe-wp-config-' );
		try {
			file_put_contents( $path, $source );
			chmod( $path, 0640 );
			$editor = new WpCacheConfiguration();
			self::assertSame( null !== $expected, $editor->enable( $path ) );
			self::assertSame( null === $expected ? 'config-format' : null, $editor->failure() );
			self::assertSame( $expected ?? $source, file_get_contents( $path ) );
			self::assertSame( 0640, fileperms( $path ) & 0777 );
			if ( null !== $expected ) {
				self::assertTrue( $editor->enable( $path ) );
				self::assertSame( $expected, file_get_contents( $path ) );
			}
		} finally {
			unlink( $path );
		}
	}
}
