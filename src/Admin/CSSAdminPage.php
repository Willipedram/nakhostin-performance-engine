<?php
/**
 * Explicit, review-only CSS usage diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\CSS\ApprovedCSSRules;
use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use Nakhostin\PerformanceEngine\CSS\CSSStorage;
use Nakhostin\PerformanceEngine\CSS\FontStorage;
use Nakhostin\PerformanceEngine\Optimization\OptimizationManifestStorage;
use Nakhostin\PerformanceEngine\DOM\DOMStorageInterface;

final class CSSAdminPage {
	public const SLUG = 'npe-css-intelligence';
	public const ACTION = 'npe_analyze_css';
	private const MAX_SOURCE_BYTES = 1048576;

	/** @var CSSAnalyzer */
	private $analyzer;
	/** @var CSSStorage */
	private $storage;
	/** @var DOMStorageInterface */
	private $dom_storage;
	/** @var Capabilities */
	private $capabilities;
	/** @var FontStorage|null */ private $font_storage;

	public function __construct( CSSAnalyzer $analyzer, CSSStorage $storage, DOMStorageInterface $dom_storage, Capabilities $capabilities, ?FontStorage $font_storage = null ) {
		$this->analyzer     = $analyzer;
		$this->storage      = $storage;
		$this->dom_storage  = $dom_storage;
		$this->capabilities = $capabilities;
		$this->font_storage = $font_storage;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_analysis' ) );
	}

	public function add_menu(): void {
		AdminShell::submenu(
			AdminPage::SLUG,
			__( 'CSS Intelligence', 'nakhostin-performance-engine' ),
			__( 'CSS', 'nakhostin-performance-engine' ),
			Capabilities::MANAGE,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function handle_analysis(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to analyze stylesheets.', 'nakhostin-performance-engine' ) );
		}
		check_admin_referer( self::ACTION );
		$source = isset( $_POST['npe_css_source'] ) ? wp_unslash( $_POST['npe_css_source'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSS is parsed, never rendered or retained.
		$source = is_string( $source ) ? $source : '';
		if ( '' === trim( $source ) || strlen( $source ) > self::MAX_SOURCE_BYTES ) {
			wp_die( esc_html__( 'The stylesheet is empty or exceeds the one megabyte analysis limit.', 'nakhostin-performance-engine' ) );
		}
		$dom      = $this->dom_storage->latest();
		$manifest = $this->analyzer->analyze_stylesheet( $source, $dom ? $dom->to_array() : array() );
		$this->storage->save( $manifest );
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view CSS diagnostics.', 'nakhostin-performance-engine' ) );
		}
		$page = isset( $_GET['npe_css_page'] ) && is_string( $_GET['npe_css_page'] ) ? esc_url_raw( wp_unslash( $_GET['npe_css_page'] ) ) : home_url( '/' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only local manifest lookup.
		$queued = isset( $_GET['npe_css_queued'] ) && '1' === $_GET['npe_css_queued']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display feedback only.
		$plan = ( new OptimizationManifestStorage() )->for_url( $page );
		$summary = $plan ? $plan->section( 'summary' ) : array();
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<section class="npe-card">
				<h2><?php echo esc_html__( 'Optimize a public page', 'nakhostin-performance-engine' ); ?></h2>
				<p><?php echo esc_html__( 'Combine compatible stylesheets in the background. Stylesheet order and dynamic rules are preserved.', 'nakhostin-performance-engine' ); ?></p>
				<?php if ( $summary ) : ?>
					<?php if ( ! empty( $summary['learning']['required'] ) ) : ?>
						<p><?php echo esc_html( sprintf( __( 'Learning observations: %1$d of %2$d · JavaScript bundle: %3$s', 'nakhostin-performance-engine' ), $summary['learning']['samples'], $summary['learning']['required'], $summary['javascript']['bundle_status'] ?? 'unknown' ) ); ?></p>
					<?php endif; ?>
					<p><?php echo esc_html( sprintf( __( 'Last CSS plan: %1$s · %2$d source files in the bundle', 'nakhostin-performance-engine' ), $summary['css']['bundle_status'] ?? 'unknown', (int) ( $summary['css']['bundled_files'] ?? 0 ) ) ); ?></p>
					<?php foreach ( array( 'css' => 'CSS', 'javascript' => 'JavaScript' ) as $type => $label ) : ?>
						<?php if ( 'generated' === ( $summary[ $type ]['bundle_status'] ?? '' ) ) : ?>
							<p><?php echo esc_html( sprintf( __( '%1$s uncompressed bundle: %2$s → %3$s', 'nakhostin-performance-engine' ), $label, size_format( $summary[ $type ]['source_bytes'] ?? 0, 1 ), size_format( $summary[ $type ]['bundle_bytes'] ?? 0, 1 ) ) ); ?></p>
						<?php endif; ?>
					<?php endforeach; ?>
					<p><?php echo esc_html( sprintf( __( 'Stylesheet collection: %d ms', 'nakhostin-performance-engine' ), (int) ( $summary['css']['collection_ms'] ?? 0 ) ) ); ?>
					<?php if ( ! empty( $summary['css']['skipped'] ) ) : ?><code dir="ltr"><?php echo esc_html( wp_json_encode( $summary['css']['skipped'] ) ); ?></code><?php endif; ?></p>
				<?php elseif ( $queued ) : ?>
					<div class="notice notice-info inline"><p><?php echo esc_html__( 'The page is queued. Refresh this screen after WordPress finishes the background scan.', 'nakhostin-performance-engine' ); ?></p></div>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( DOMAdminPage::ACTION ); ?>">
					<input type="hidden" name="npe_css_quick" value="1">
					<?php wp_nonce_field( DOMAdminPage::ACTION ); ?>
					<p><label for="npe-css-page-url"><?php echo esc_html__( 'Public page URL', 'nakhostin-performance-engine' ); ?></label>
					<input class="regular-text" dir="ltr" type="url" id="npe-css-page-url" name="source_url" value="<?php echo esc_attr( $page ); ?>" required></p>
					<p><label><input type="checkbox" name="npe_page_cache" value="1"> <?php echo esc_html__( 'Enable public page cache. My public pages are not personalized for anonymous visitors.', 'nakhostin-performance-engine' ); ?></label></p>
					<p><label><input type="checkbox" name="npe_learning" value="1"> <?php echo esc_html__( 'Learn this page before bundling CSS and JavaScript', 'nakhostin-performance-engine' ); ?></label></p>
					<p><label><input type="checkbox" name="npe_lazy" value="1"> <?php echo esc_html__( 'Also enable native image and iframe lazy loading', 'nakhostin-performance-engine' ); ?></label></p>
					<details class="npe-advanced"><summary><?php echo esc_html__( 'Approve unused CSS for this page', 'nakhostin-performance-engine' ); ?></summary>
						<p><label><input type="checkbox" name="npe_css_update_approvals" value="1"> <?php echo esc_html__( 'Update the approved list for the URL above', 'nakhostin-performance-engine' ); ?></label></p>
						<p><label for="npe-css-approved"><?php echo esc_html__( 'Confirmed unused class selectors', 'nakhostin-performance-engine' ); ?></label>
						<input type="text" id="npe-css-approved" name="npe_css_approved" dir="ltr" maxlength="1300" size="36" value="<?php echo esc_attr( implode( ', ', ApprovedCSSRules::for_page( $page ) ) ); ?>"></p>
						<p class="description"><?php echo esc_html__( 'Enter up to 20 simple classes, such as .old-banner. Approve only after checking mobile, desktop, and interactive states. Nested and grouped rules are kept.', 'nakhostin-performance-engine' ); ?></p>
					</details>
					<?php submit_button( __( 'Queue page optimization', 'nakhostin-performance-engine' ), 'primary', 'submit', false ); ?>
				</form>
			</section>
			<details class="npe-card npe-advanced"><summary><?php echo esc_html__( 'Advanced settings', 'nakhostin-performance-engine' ); ?></summary>
				<h2><?php echo esc_html__( 'Manual stylesheet analysis (advanced)', 'nakhostin-performance-engine' ); ?></h2>
				<p><?php echo esc_html__( 'First analyze the target page under DOM Intelligence, then paste the corresponding stylesheet below. Dynamic selectors and uncertain rules are preserved.', 'nakhostin-performance-engine' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<label for="npe-css-source"><strong><?php echo esc_html__( 'Stylesheet source', 'nakhostin-performance-engine' ); ?></strong></label>
					<textarea id="npe-css-source" class="large-text code npe-code-input" dir="ltr" rows="12" maxlength="1048576" name="npe_css_source" required></textarea>
					<p class="description"><?php echo esc_html__( 'The submitted CSS is processed in memory and is not stored. Only selector counts and the compact analysis manifest are saved.', 'nakhostin-performance-engine' ); ?></p>
					<?php submit_button( __( 'Analyze CSS usage', 'nakhostin-performance-engine' ) ); ?>
				</form>
			</details>
			<?php $this->render_manifest( $this->storage->latest() ); ?>
			<?php $this->render_fonts(); ?>
		</div>
		<?php
	}

	private function render_fonts(): void {
		$manifests = $this->font_storage ? $this->font_storage->all() : array();
		if ( ! $manifests ) { return; }
		?>
		<div class="npe-card"><h2><?php echo esc_html__( 'Detected web fonts', 'nakhostin-performance-engine' ); ?></h2>
		<p><?php echo esc_html__( 'This site-wide report lists font families, variants, source addresses, and every analyzed page that may require them.', 'nakhostin-performance-engine' ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Page URL', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Font family', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Weight and style', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Format', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Source URL', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Required on page', 'nakhostin-performance-engine' ); ?></th></tr></thead><tbody>
		<?php $shown = 0; foreach ( $manifests as $manifest ) : $data = $manifest->to_array(); foreach ( (array) ( $data['families'] ?? array() ) as $family ) : foreach ( (array) ( $family['faces'] ?? array() ) as $face ) : foreach ( (array) ( $face['sources'] ?? array() ) as $source ) : if ( $shown++ >= 500 ) { break 4; } ?>
		<tr><td><code dir="ltr"><?php echo esc_html( (string) ( $data['source_url'] ?? '' ) ); ?></code></td><td><?php echo esc_html( (string) ( $family['family'] ?? '' ) ); ?></td><td class="npe-technical" dir="ltr"><?php echo esc_html( (string) ( $face['weight'] ?? '' ) . ' / ' . (string) ( $face['style'] ?? '' ) ); ?></td><td class="npe-technical" dir="ltr"><?php echo esc_html( (string) ( $source['format'] ?? '' ) ); ?></td><td><code dir="ltr"><?php echo esc_html( (string) ( $source['url'] ?? '' ) ); ?></code></td><td><?php echo ! empty( $face['required'] ) ? esc_html__( 'Yes', 'nakhostin-performance-engine' ) : esc_html__( 'No', 'nakhostin-performance-engine' ); ?></td></tr>
		<?php endforeach; endforeach; endforeach; endforeach; ?>
		</tbody></table></div>
		<?php
	}

	private function render_manifest( ?CSSManifest $manifest ): void {
		if ( null === $manifest ) {
			echo '<div class="npe-card"><p>' . esc_html__( 'No CSS usage analysis has been recorded.', 'nakhostin-performance-engine' ) . '</p></div>';
			return;
		}
		$data   = $manifest->to_array();
		$groups = array(
			'used'              => __( 'Matched selectors', 'nakhostin-performance-engine' ),
			'unused_candidates' => __( 'Possible unused selectors', 'nakhostin-performance-engine' ),
			'preserved'         => __( 'Preserved dynamic or uncertain selectors', 'nakhostin-performance-engine' ),
		);
		?>
		<div class="npe-card"><h2><?php echo esc_html__( 'Latest CSS analysis', 'nakhostin-performance-engine' ); ?></h2>
		<p><?php echo esc_html__( 'Possible unused does not mean safe to delete. Verify interactive states and templates before making any manual change.', 'nakhostin-performance-engine' ); ?></p>
		<table class="widefat striped"><tbody>
		<tr><th><?php echo esc_html__( 'Stylesheet size', 'nakhostin-performance-engine' ); ?></th><td class="npe-technical" dir="ltr"><?php echo esc_html( size_format( (int) $data['source_bytes'] ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Selectors analyzed', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $data['selector_count'] ) ); ?></td></tr>
		<?php foreach ( $groups as $key => $label ) : ?>
			<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( number_format_i18n( count( $data[ $key ] ) ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table></div>
		<?php foreach ( $groups as $key => $label ) : ?>
			<details class="npe-card"><summary><strong><?php echo esc_html( $label ); ?></strong></summary><div class="npe-token-list" dir="ltr">
			<?php foreach ( $data[ $key ] as $selector ) : ?>
				<code><?php echo esc_html( $selector ); ?></code>
			<?php endforeach; ?>
			</div></details>
		<?php endforeach;
	}
}
