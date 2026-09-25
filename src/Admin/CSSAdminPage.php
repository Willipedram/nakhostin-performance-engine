<?php
/**
 * Explicit, review-only CSS usage diagnostics.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\CSS\CSSAnalyzer;
use Nakhostin\PerformanceEngine\CSS\CSSManifest;
use Nakhostin\PerformanceEngine\CSS\CSSStorage;
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

	public function __construct( CSSAnalyzer $analyzer, CSSStorage $storage, DOMStorageInterface $dom_storage, Capabilities $capabilities ) {
		$this->analyzer     = $analyzer;
		$this->storage      = $storage;
		$this->dom_storage  = $dom_storage;
		$this->capabilities = $capabilities;
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_analysis' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
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
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'CSS Intelligence', 'nakhostin-performance-engine' ); ?></h1>
			<div class="notice notice-info inline"><p><?php echo esc_html__( 'NPE compares selectors with the latest DOM manifest. It only reports review candidates and never removes or rewrites CSS automatically.', 'nakhostin-performance-engine' ); ?></p></div>
			<div class="npe-card">
				<h2><?php echo esc_html__( 'Analyze stylesheet usage', 'nakhostin-performance-engine' ); ?></h2>
				<p><?php echo esc_html__( 'First analyze the target page under DOM Intelligence, then paste the corresponding stylesheet below. Dynamic selectors and uncertain rules are preserved.', 'nakhostin-performance-engine' ); ?></p>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
					<?php wp_nonce_field( self::ACTION ); ?>
					<label for="npe-css-source"><strong><?php echo esc_html__( 'Stylesheet source', 'nakhostin-performance-engine' ); ?></strong></label>
					<textarea id="npe-css-source" class="large-text code npe-code-input" dir="ltr" rows="12" maxlength="1048576" name="npe_css_source" required></textarea>
					<p class="description"><?php echo esc_html__( 'The submitted CSS is processed in memory and is not stored. Only selector counts and the compact analysis manifest are saved.', 'nakhostin-performance-engine' ); ?></p>
					<?php submit_button( __( 'Analyze CSS usage', 'nakhostin-performance-engine' ) ); ?>
				</form>
			</div>
			<?php $this->render_manifest( $this->storage->latest() ); ?>
		</div>
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
