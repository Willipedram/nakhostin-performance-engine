<?php
/**
 * Explicit JavaScript Intelligence diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptManifest;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;

final class JavaScriptAdminPage {
	public const SLUG = 'npe-javascript-intelligence';
	public const ACTION = 'npe_analyze_javascript';

	/** @var ScriptDiscovery */
	private $discovery;

	/** @var JavaScriptAnalyzer */
	private $analyzer;

	/** @var JavaScriptStorage */
	private $storage;

	/** @var LocalScriptSourceProvider */
	private $sources;

	/** @var ComponentRegistry */
	private $components;

	/** @var Settings */
	private $settings;

	/** @var Capabilities */
	private $capabilities;

	public function __construct(
		ScriptDiscovery $discovery,
		JavaScriptAnalyzer $analyzer,
		JavaScriptStorage $storage,
		LocalScriptSourceProvider $sources,
		ComponentRegistry $components,
		Settings $settings,
		Capabilities $capabilities
	) {
		$this->discovery    = $discovery;
		$this->analyzer     = $analyzer;
		$this->storage      = $storage;
		$this->sources      = $sources;
		$this->components   = $components;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_analysis' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			AdminPage::SLUG,
			__( 'JavaScript Intelligence', 'nakhostin-performance-engine' ),
			__( 'JavaScript', 'nakhostin-performance-engine' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function handle_analysis(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to analyze scripts.', 'nakhostin-performance-engine' ) );
		}

		check_admin_referer( self::ACTION );
		/**
		 * Supplies public script-module registration snapshots for analysis.
		 *
		 * WordPress does not expose its complete module registry through a stable
		 * getter, so integrations can provide handle/src/dependency metadata here.
		 *
		 * @param array $modules Script module snapshots.
		 */
		$modules = apply_filters( 'npe/javascript/module_snapshots', array() );
		$assets  = $this->discovery->discover( null, is_array( $modules ) ? $modules : array() );
		$sources = $this->sources->load( $assets );
		$context = array(
			'is_admin'      => true,
			'page_type'     => 'admin-diagnostic',
			'source_hashes' => $sources['hashes'],
			'settings_hash' => hash( 'sha256', (string) wp_json_encode( $this->settings->all() ) ),
			'versions'      => $this->versions(),
		);
		$manifest = $this->analyzer->analyze_assets(
			$assets,
			array_values( $this->components->all() ),
			$context,
			array(),
			$sources['contents']
		);
		$this->storage->save( $manifest );
		$this->redirect();
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view JavaScript diagnostics.', 'nakhostin-performance-engine' ) );
		}

		$manifest = $this->storage->latest();
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'JavaScript Intelligence', 'nakhostin-performance-engine' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'Analysis is explicit and uncertain scripts always retain their original loading behavior.',
					'nakhostin-performance-engine'
				);
				?>
			</p>
			<div class="npe-card">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php submit_button( __( 'Analyze Registered Scripts', 'nakhostin-performance-engine' ) ); ?>
				</form>
			</div>
			<?php $this->render_manifest( $manifest ); ?>
		</div>
		<?php
	}

	private function render_manifest( ?JavaScriptManifest $manifest ): void {
		if ( null === $manifest ) {
			echo '<div class="npe-card"><p>';
			echo esc_html__( 'No JavaScript analysis has been recorded.', 'nakhostin-performance-engine' );
			echo '</p></div>';
			return;
		}

		$data = $manifest->to_array();
		$rows = array(
			__( 'Original JavaScript size', 'nakhostin-performance-engine' )  => size_format( $data['original_size'] ),
			__( 'Optimized JavaScript size', 'nakhostin-performance-engine' ) => size_format( $data['optimized_size'] ),
			__( 'Number of files', 'nakhostin-performance-engine' )          => $data['file_count'],
			__( 'Files with measured size', 'nakhostin-performance-engine' ) => $data['measured_files'],
			__( 'Bundles', 'nakhostin-performance-engine' )                  => $this->bundle_summary( $data['bundles'] ),
			__( 'Deferred scripts', 'nakhostin-performance-engine' )         => implode( ', ', $data['deferred'] ),
			__( 'Delayed scripts', 'nakhostin-performance-engine' )          => implode( ', ', $data['delayed'] ),
			__( 'Warnings', 'nakhostin-performance-engine' )                 => implode( ' | ', $data['warnings'] ),
		);
		?>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Latest Analysis', 'nakhostin-performance-engine' ); ?></h2>
			<table class="widefat striped"><tbody>
			<?php foreach ( $rows as $label => $value ) : ?>
				<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Dependencies', 'nakhostin-performance-engine' ); ?></h2>
			<table class="widefat striped"><thead><tr>
				<th><?php echo esc_html__( 'Handle', 'nakhostin-performance-engine' ); ?></th>
				<th><?php echo esc_html__( 'Dependencies', 'nakhostin-performance-engine' ); ?></th>
				<th><?php echo esc_html__( 'Strategy', 'nakhostin-performance-engine' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $data['assets'] as $handle => $asset ) : ?>
				<tr>
					<td><code><?php echo esc_html( $handle ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', $asset['dependencies'] ) ); ?></td>
					<td><?php echo esc_html( $asset['strategy'] ?: __( 'Original', 'nakhostin-performance-engine' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function versions(): array {
		$theme = wp_get_theme();

		return array(
			'wordpress'  => get_bloginfo( 'version' ),
			'theme'      => $theme->get( 'Version' ),
			'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'elementor'   => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'npe'         => NPE_VERSION,
		);
	}

	private function bundle_summary( array $bundles ): string {
		$summary = array();
		foreach ( $bundles as $bundle ) {
			$summary[] = sprintf(
				'%1$s (%2$s): %3$s',
				sanitize_key( (string) ( $bundle['layer'] ?? '' ) ),
				sanitize_key( (string) ( $bundle['type'] ?? '' ) ),
				implode( ', ', $bundle['handles'] ?? array() )
			);
		}

		return implode( ' | ', $summary );
	}

	private function redirect(): void {
		$url = add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
