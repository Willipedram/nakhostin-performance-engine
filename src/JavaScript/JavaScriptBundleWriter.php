<?php
/**
 * Atomic content-hashed JavaScript bundle writer.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

use RuntimeException;

final class JavaScriptBundleWriter {
	/** @var ConservativeMinifier */
	private $minifier;
	/** @var string */
	private $trusted_directory;

	public function __construct( ConservativeMinifier $minifier, string $trusted_directory ) {
		$this->minifier         = $minifier;
		$this->trusted_directory = rtrim( $trusted_directory, '/\\' );
	}

	public function build( string $layer, array $ordered_assets, array $source_contents, string $directory ): array {
		$layer = sanitize_key( $layer );
		if ( ! in_array( $layer, array( 'core', 'component', 'page' ), true ) ) {
			throw new RuntimeException( 'Unknown JavaScript bundle layer.' );
		}

		$contents = array();
		$handles  = array();
		$graph    = new DependencyGraph( $ordered_assets );
		$order    = $graph->closure(
			array_map(
				static function ( $asset ): string {
					return $asset instanceof ScriptAsset ? $asset->handle() : '';
				},
				$ordered_assets
			)
		);

		foreach ( $order as $handle ) {
			$asset = $graph->asset( $handle );
			if ( null === $asset || ! isset( $source_contents[ $asset->handle() ] ) ) {
				throw new RuntimeException( 'Every bundled script requires trusted source content.' );
			}
			if ( $asset->to_array()['module'] ) {
				throw new RuntimeException( 'Script modules retain their original loading behavior.' );
			}
			if ( $asset->has_runtime_data() ) {
				throw new RuntimeException( 'Scripts with runtime data must retain their original handle.' );
			}
			$source = (string) $source_contents[ $asset->handle() ];
			if ( strlen( $source ) > LocalScriptSourceProvider::MAX_SOURCE_BYTES || false !== stripos( $source, '<?php' ) ) {
				throw new RuntimeException( 'Bundle source failed the trusted JavaScript safety policy.' );
			}

			$handles[]  = $asset->handle();
			$contents[] = '/* ' . $asset->handle() . ' */' . "\n"
				. $this->minifier->minify( $source );
		}

		$content  = implode( "\n", $contents );
		$hash     = substr( hash( 'sha256', $content ), 0, 20 );
		$filename = $layer . '-' . $hash . '.js';
		$directory = trailingslashit( $directory );

		if ( ! wp_mkdir_p( $directory ) ) {
			throw new RuntimeException( 'Unable to create the JavaScript bundle directory.' );
		}
		$real_directory = realpath( $directory );
		$trusted        = realpath( $this->trusted_directory );
		if ( false === $real_directory || false === $trusted || $real_directory !== $trusted || ! is_writable( $real_directory ) ) {
			throw new RuntimeException( 'The JavaScript bundle directory is outside the trusted writable location.' );
		}
		if ( ! $this->protect_directory( $real_directory ) ) {
			throw new RuntimeException( 'Unable to protect the JavaScript bundle directory.' );
		}

		$temporary = $directory . $filename . '.tmp-' . wp_generate_password( 8, false, false );
		$target    = $directory . $filename;
		// Atomic write is isolated to the plugin-owned generated asset directory.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === file_put_contents( $temporary, $content, LOCK_EX ) ) {
			throw new RuntimeException( 'Unable to write the temporary JavaScript bundle.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Atomic replacement on the same filesystem.
		if ( ! rename( $temporary, $target ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup of the owned temporary file.
			unlink( $temporary );
			throw new RuntimeException( 'Unable to publish the JavaScript bundle.' );
		}

		return array(
			'filename'       => $filename,
			'content_hash'   => $hash,
			'handles'        => $handles,
			'original_size'  => array_sum(
				array_map( 'strlen', array_intersect_key( $source_contents, array_flip( $handles ) ) )
			),
			'optimized_size' => strlen( $content ),
		);
	}

	private function protect_directory( string $directory ): bool {
		$index = trailingslashit( $directory ) . 'index.php';
		if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\n// Silence is golden.\n", LOCK_EX ) ) { return false; }
		$rules = trailingslashit( $directory ) . '.htaccess';
		$content = "Options -ExecCGI\n" . '<FilesMatch "\.(php|phtml|phar|cgi|pl|py|sh)$">' . "\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n</FilesMatch>\n";
		return file_exists( $rules ) || false !== file_put_contents( $rules, $content, LOCK_EX );
	}
}
