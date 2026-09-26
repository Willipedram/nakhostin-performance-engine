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
use Nakhostin\PerformanceEngine\DOM\DOMStorageInterface;
use Nakhostin\PerformanceEngine\DOM\PageAnalysisCoordinator;
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

	/** @var DOMStorageInterface */
	private $dom_storage;

	/** @var PageAnalysisCoordinator|null */
	private $coordinator;

	public function __construct(
		ScriptDiscovery $discovery,
		JavaScriptAnalyzer $analyzer,
		JavaScriptStorage $storage,
		LocalScriptSourceProvider $sources,
		ComponentRegistry $components,
		Settings $settings,
		Capabilities $capabilities,
		DOMStorageInterface $dom_storage,
		?PageAnalysisCoordinator $coordinator = null
	) {
		$this->discovery    = $discovery;
		$this->analyzer     = $analyzer;
		$this->storage      = $storage;
		$this->sources      = $sources;
		$this->components   = $components;
		$this->settings     = $settings;
		$this->capabilities = $capabilities;
		$this->dom_storage   = $dom_storage;
		$this->coordinator   = $coordinator;
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
		$latest_dom = $this->dom_storage->latest();
		$source_url = $latest_dom ? (string) ( $latest_dom->to_array()['source_url'] ?? '' ) : '';
		if ( null !== $this->coordinator && '' !== $source_url && $this->coordinator->request( $source_url ) ) {
			$this->redirect();
		}
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
		$dom     = $this->dom_storage->latest();
		$dom_data = $dom ? $dom->to_array() : array();
		$context = array(
			'is_admin'      => true,
			'page_type'     => sanitize_key( (string) ( $dom_data['page_type'] ?? 'admin-diagnostic' ) ),
			'template'      => sanitize_text_field( (string) ( $dom_data['template_identifier'] ?? '' ) ),
			'source_hashes' => $sources['hashes'],
			'settings_hash' => hash( 'sha256', (string) wp_json_encode( $this->settings->all() ) ),
			'versions'      => $this->versions(),
		);
		$options = array();
		if ( $dom ) {
			$options['page_handles'] = $this->observed_handles( $assets, $dom_data['scripts'] ?? array() );
		}
		$manifest = $this->analyzer->analyze_assets(
			$assets,
			$dom ? $this->components->ingest_manifest( $dom_data ) : array(),
			$context,
			$options,
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
			<div class="npe-card npe-explainer">
				<h2><?php echo esc_html__( 'How JavaScript decisions are made', 'nakhostin-performance-engine' ); ?></h2>
				<ul>
					<li><?php echo esc_html__( 'Required: the script is enqueued for this page, required by another script, or declared by a detected component.', 'nakhostin-performance-engine' ); ?></li>
					<li><?php echo esc_html__( 'Not required on this page: the script is registered but is outside the dependency closure of the current page.', 'nakhostin-performance-engine' ); ?></li>
					<li><?php echo esc_html__( 'Preserved: scripts with inline data, localization, sensitive integrations, missing dependencies, or uncertain behavior keep their original loading.', 'nakhostin-performance-engine' ); ?></li>
					<li><?php echo esc_html__( 'Running analysis does not change frontend scripts. Optimization strategies are applied only through separately enabled safe controls.', 'nakhostin-performance-engine' ); ?></li>
				</ul>
			</div>
			<div class="npe-card">
				<p><?php echo esc_html__( 'This action automatically revisits the latest analyzed public page and refreshes its DOM, component, CSS, and JavaScript manifests together.', 'nakhostin-performance-engine' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<?php submit_button( __( 'Refresh Page Asset Analysis', 'nakhostin-performance-engine' ) ); ?>
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
			__( 'Required scripts', 'nakhostin-performance-engine' )          => count( $data['required'] ?? array() ),
			__( 'Registered but not required on this page', 'nakhostin-performance-engine' ) => count( $data['unused_candidates'] ?? array() ),
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
				<th><?php echo esc_html__( 'Page requirement', 'nakhostin-performance-engine' ); ?></th>
				<th><?php echo esc_html__( 'Loading strategy', 'nakhostin-performance-engine' ); ?></th>
			</tr></thead><tbody>
			<?php foreach ( $data['assets'] as $handle => $asset ) : ?>
				<tr>
					<td><code><?php echo esc_html( $handle ); ?></code></td>
					<td><?php echo esc_html( implode( ', ', $asset['dependencies'] ) ); ?></td>
					<td><?php echo esc_html( in_array( $handle, $data['required'] ?? array(), true ) ? __( 'Required by this page or its dependencies', 'nakhostin-performance-engine' ) : __( 'Registered but not requested by this page', 'nakhostin-performance-engine' ) ); ?></td>
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

	private function observed_handles( array $assets, array $scripts ): array {
		$paths = array();
		foreach ( $scripts as $script ) {
			$source = is_array( $script ) ? (string) ( $script['src'] ?? '' ) : '';
			$path   = wp_parse_url( $source, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$paths[ '/' . ltrim( $path, '/' ) ] = true;
			}
		}

		$handles = array();
		foreach ( $assets as $asset ) {
			if ( ! is_object( $asset ) || ! method_exists( $asset, 'source' ) || ! method_exists( $asset, 'handle' ) ) {
				continue;
			}
			$path = wp_parse_url( $asset->source(), PHP_URL_PATH );
			if ( is_string( $path ) && isset( $paths[ '/' . ltrim( $path, '/' ) ] ) ) {
				$handles[] = $asset->handle();
			}
		}

		return array_values( array_unique( $handles ) );
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
