<?php
/**
 * Explicit DOM Intelligence diagnostic screen.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMAnalysisQueue;
use Nakhostin\PerformanceEngine\DOM\DOMManifest;
use Nakhostin\PerformanceEngine\DOM\DOMSnapshot;
use Nakhostin\PerformanceEngine\DOM\DOMStorageInterface;
use Nakhostin\PerformanceEngine\DOM\PageAnalysisCoordinator;

final class DOMAdminPage {
	public const SLUG = 'npe-dom-intelligence';
	public const ACTION = 'npe_run_dom_analysis';

	/** @var DOMAnalyzer */
	private $analyzer;

	/** @var DOMStorageInterface */
	private $storage;

	/** @var Capabilities */
	private $capabilities;

	/** @var ComponentRegistry|null */
	private $components;

	/** @var PageAnalysisCoordinator|null */
	private $coordinator;
	/** @var DOMAnalysisQueue|null */
	private $queue;

	public function __construct(
		DOMAnalyzer $analyzer,
		DOMStorageInterface $storage,
		Capabilities $capabilities,
		?ComponentRegistry $components = null,
		?PageAnalysisCoordinator $coordinator = null,
		?DOMAnalysisQueue $queue = null
	) {
		$this->analyzer     = $analyzer;
		$this->storage      = $storage;
		$this->capabilities = $capabilities;
		$this->components   = $components;
		$this->coordinator  = $coordinator;
		$this->queue        = $queue;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_analysis' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			AdminPage::SLUG,
			__( 'DOM Intelligence', 'nakhostin-performance-engine' ),
			__( 'DOM Intelligence', 'nakhostin-performance-engine' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function handle_analysis(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to run DOM analysis.', 'nakhostin-performance-engine' ) );
		}

		check_admin_referer( self::ACTION );
		if ( ! class_exists( 'DOMDocument' ) ) {
			$this->redirect( 'dom-unavailable' );
		}

		$submitted_url = isset( $_POST['source_url'] )
			? esc_url_raw( wp_unslash( $_POST['source_url'] ) )
			: '';

		if ( ! $this->is_allowed_url( $submitted_url ) ) {
			$this->redirect( 'invalid-url' );
		}

		if ( null !== $this->coordinator && $this->coordinator->request( $submitted_url ) ) {
			$this->redirect( 'success' );
		}

		$response = wp_safe_remote_get(
			$submitted_url,
			array(
				'timeout'             => 10,
				'redirection'         => 3,
				'limit_response_size' => DOMSnapshot::MAX_BYTES,
				'user-agent'          => 'NPE DOM Intelligence/' . NPE_VERSION,
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->redirect( 'fetch-failed' );
		}

		$html = wp_remote_retrieve_body( $response );
		if ( '' === trim( $html ) ) {
			$this->redirect( 'empty-response' );
		}
		if ( null !== $this->coordinator ) {
			$this->coordinator->capture( $html, '', $submitted_url );
			$this->redirect( $this->coordinator->succeeded() ? 'success' : 'analysis-failed' );
		}

		$manifest = $this->analyzer->create_manifest(
			new DOMSnapshot(
				$html,
				array(
					'source_url' => $submitted_url,
					'versions'   => $this->environment_versions(),
				)
			)
		);
		$this->storage->save( $manifest );
		if ( null !== $this->components ) {
			$this->components->ingest_manifest( $manifest->to_array() );
		}
		$this->redirect( 'success' );
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view DOM diagnostics.', 'nakhostin-performance-engine' ) );
		}

		$manifest = $this->storage->latest();
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'DOM Intelligence', 'nakhostin-performance-engine' ); ?></h1>
			<p>
				<?php
				echo esc_html__(
					'One click analyzes the rendered DOM, detected components, same-origin stylesheets, and registered JavaScript dependencies. Full page HTML and submitted source code are not stored.',
					'nakhostin-performance-engine'
				);
				?>
			</p>
			<?php $this->render_notice(); ?>
			<?php $this->render_queue(); ?>
			<div class="npe-card">
				<h2><?php echo esc_html__( 'Analyze Page Assets', 'nakhostin-performance-engine' ); ?></h2>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<label for="npe-source-url"><?php echo esc_html__( 'Public page URL', 'nakhostin-performance-engine' ); ?></label>
					<input class="regular-text code" id="npe-source-url" name="source_url" type="url" required
						value="<?php echo esc_attr( home_url( '/' ) ); ?>">
					<?php submit_button( __( 'Analyze DOM, CSS, JavaScript, and Components', 'nakhostin-performance-engine' ) ); ?>
				</form>
			</div>
			<?php $this->render_manifest( $manifest ); ?>
		</div>
		<?php
	}

	private function render_queue(): void {
		if ( null === $this->queue ) {
			return;
		}
		$counts = array( 'pending' => 0, 'running' => 0, 'failed' => 0 );
		foreach ( $this->queue->all() as $job ) {
			$status = (string) ( $job['status'] ?? '' );
			if ( isset( $counts[ $status ] ) ) {
				++$counts[ $status ];
			}
		}
		?>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Automatic learning queue', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Eligible visitor requests are deduplicated and analyzed asynchronously, one page per cron run.', 'nakhostin-performance-engine' ); ?></p>
			<ul>
				<li><?php echo esc_html__( 'Pending', 'nakhostin-performance-engine' ); ?>: <strong><?php echo esc_html( (string) $counts['pending'] ); ?></strong></li>
				<li><?php echo esc_html__( 'Running', 'nakhostin-performance-engine' ); ?>: <strong><?php echo esc_html( (string) $counts['running'] ); ?></strong></li>
				<li><?php echo esc_html__( 'Failed', 'nakhostin-performance-engine' ); ?>: <strong><?php echo esc_html( (string) $counts['failed'] ); ?></strong></li>
			</ul>
		</div>
		<?php
	}

	private function render_manifest( ?DOMManifest $manifest ): void {
		if ( null === $manifest ) {
			echo '<div class="npe-card"><p>';
			echo esc_html__( 'No DOM manifest has been generated yet.', 'nakhostin-performance-engine' );
			echo '</p></div>';
			return;
		}

		$data = $manifest->to_array();
		$rows = array(
			__( 'Page type', 'nakhostin-performance-engine' )             => $data['page_type'],
			__( 'DOM signature', 'nakhostin-performance-engine' )         => $data['dom_signature'],
			__( 'Classes', 'nakhostin-performance-engine' )               => count( $data['classes'] ),
			__( 'IDs', 'nakhostin-performance-engine' )                   => count( $data['ids'] ),
			__( 'Attributes', 'nakhostin-performance-engine' )            => count( $data['attributes'] ),
			__( 'Components', 'nakhostin-performance-engine' )            => implode( ', ', $data['components'] ),
			__( 'WooCommerce structures', 'nakhostin-performance-engine' ) => $this->yes_no(
				in_array( 'woocommerce', $data['integrations'], true )
			),
			__( 'Elementor structures', 'nakhostin-performance-engine' )   => $this->yes_no(
				in_array( 'elementor', $data['integrations'], true )
			),
		);
		?>
		<div class="npe-card">
			<h2><?php echo esc_html__( 'Latest Manifest', 'nakhostin-performance-engine' ); ?></h2>
			<table class="widefat striped"><tbody>
			<?php foreach ( $rows as $label => $value ) : ?>
				<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( (string) $value ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php
	}

	private function render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted notice status.
		$status = isset( $_GET['npe_dom_status'] ) ? sanitize_key( wp_unslash( $_GET['npe_dom_status'] ) ) : '';
		$notices = array(
			'success'         => __( 'Page asset analysis completed.', 'nakhostin-performance-engine' ),
			'invalid-url'     => __( 'Enter a valid URL on this WordPress site.', 'nakhostin-performance-engine' ),
			'fetch-failed'    => __( 'The page could not be fetched safely.', 'nakhostin-performance-engine' ),
			'empty-response'  => __( 'The page returned an empty response.', 'nakhostin-performance-engine' ),
			'dom-unavailable' => __( 'The PHP DOM extension is required for analysis.', 'nakhostin-performance-engine' ),
			'analysis-failed' => __( 'The page loaded, but one or more asset manifests could not be created safely.', 'nakhostin-performance-engine' ),
		);

		if ( ! isset( $notices[ $status ] ) ) {
			return;
		}

		$class = 'success' === $status ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %1$s"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $notices[ $status ] )
		);
	}

	private function is_allowed_url( string $url ): bool {
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$requested_host   = wp_parse_url( $url, PHP_URL_HOST );
		$site_host        = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$requested_port   = wp_parse_url( $url, PHP_URL_PORT );
		$site_port        = wp_parse_url( home_url( '/' ), PHP_URL_PORT );
		$requested_scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$site_scheme      = wp_parse_url( home_url( '/' ), PHP_URL_SCHEME );

		return is_string( $requested_host )
			&& is_string( $site_host )
			&& strtolower( $requested_host ) === strtolower( $site_host )
			&& $requested_port === $site_port
			&& $requested_scheme === $site_scheme;
	}

	private function environment_versions(): array {
		$theme = wp_get_theme();

		return array(
			'wordpress'   => get_bloginfo( 'version' ),
			'theme'       => $theme->get( 'Version' ),
			'woocommerce' => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'elementor'   => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'npe'         => NPE_VERSION,
		);
	}

	private function yes_no( bool $value ): string {
		return $value
			? __( 'Yes', 'nakhostin-performance-engine' )
			: __( 'No', 'nakhostin-performance-engine' );
	}

	private function redirect( string $status ): void {
		$url = add_query_arg(
			array(
				'page'           => self::SLUG,
				'npe_dom_status' => $status,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
