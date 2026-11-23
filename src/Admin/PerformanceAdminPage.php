<?php
/** Performance monitoring administration screen. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Performance\LighthouseHistory;
use Nakhostin\PerformanceEngine\Performance\LighthouseReport;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;

final class PerformanceAdminPage {
	public const SLUG = 'npe-performance-overview';
	public const CLEAR_ACTION = 'npe_clear_performance_samples';
	public const IMPORT_ACTION = 'npe_import_lighthouse_report';
	/** @var PerformanceStorage */ private $storage;
	/** @var PerformanceAggregator */ private $aggregator;
	/** @var Capabilities */ private $capabilities;

	public function __construct( PerformanceStorage $storage, PerformanceAggregator $aggregator, Capabilities $capabilities ) { $this->storage = $storage; $this->aggregator = $aggregator; $this->capabilities = $capabilities; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'handle_clear' ) ); add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'import_lighthouse' ) ); }
	public function add_menu(): void { AdminShell::submenu( AdminPage::SLUG, __( 'Performance Overview', 'nakhostin-performance-engine' ), __( 'Performance', 'nakhostin-performance-engine' ), Capabilities::MANAGE, self::SLUG, array( $this, 'render' ) ); }
	public function handle_clear(): void { $this->authorize(); $this->storage->clear(); wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) ); exit; }

	public function import_lighthouse(): void {
		if ( ! $this->capabilities->can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to view performance diagnostics.', 'nakhostin-performance-engine' ) );
		}
		check_admin_referer( self::IMPORT_ACTION );
		$file = $_FILES['npe_lighthouse_report'] ?? array();
		$phase = isset( $_POST['npe_phase'] ) && is_string( $_POST['npe_phase'] ) ? sanitize_key( wp_unslash( $_POST['npe_phase'] ) ) : '';
		$row = null;
		if ( UPLOAD_ERR_OK === ( $file['error'] ?? null ) && is_string( $file['tmp_name'] ?? null ) && is_uploaded_file( $file['tmp_name'] ) ) {
			$json = file_get_contents( $file['tmp_name'], false, null, 0, LighthouseReport::MAX_BYTES + 1 );
			$row = is_string( $json ) ? LighthouseReport::parse( $json, home_url( '/' ), $phase ) : null;
		}
		$status = $row && ( new LighthouseHistory() )->add( $row ) ? 'saved' : 'rejected';
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'npe_lighthouse' => $status ), admin_url( 'admin.php' ) ) . '#npe-lighthouse' );
		exit;
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view performance diagnostics.', 'nakhostin-performance-engine' ) ); }
		$summary = $this->aggregator->summarize( $this->storage->all() );
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
		<?php $this->render_lighthouse(); ?>
		<p><?php echo esc_html__( 'These are backend PHP generation measurements, not browser-observed TTFB or network latency.', 'nakhostin-performance-engine' ); ?></p>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Metric', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Average', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Median', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Minimum', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Maximum', 'nakhostin-performance-engine' ); ?></th></tr></thead><tbody>
		<?php foreach ( $this->metric_labels() as $metric => $label ) : $stats = $summary['metrics'][ $metric ]; ?>
		<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $this->format( $stats['average'], $metric ) ); ?></td><td><?php echo esc_html( $this->format( $stats['median'], $metric ) ); ?></td><td><?php echo esc_html( $this->format( $stats['minimum'], $metric ) ); ?></td><td><?php echo esc_html( $this->format( $stats['maximum'], $metric ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p><strong><?php echo esc_html__( 'Samples', 'nakhostin-performance-engine' ); ?>:</strong> <?php echo esc_html( number_format_i18n( $summary['samples'] ) ); ?> &nbsp; <strong><?php echo esc_html__( 'Cache hit ratio', 'nakhostin-performance-engine' ); ?>:</strong> <?php echo esc_html( null === $summary['cache_hit_ratio'] ? __( 'Not available', 'nakhostin-performance-engine' ) : $summary['cache_hit_ratio'] . '%' ); ?></p>
		<?php foreach ( $this->breakdown_labels() as $dimension => $heading ) : ?>
		<h2><?php echo esc_html( $heading ); ?></h2>
		<table class="widefat striped"><thead><tr><th><?php echo esc_html__( 'Value', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Samples', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Cache hit ratio', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'PHP generation', 'nakhostin-performance-engine' ); ?></th><th><?php echo esc_html__( 'Database', 'nakhostin-performance-engine' ); ?></th></tr></thead><tbody>
		<?php foreach ( $summary['breakdown'][ $dimension ] as $value => $row ) : ?>
		<tr><th><?php echo esc_html( $value ); ?></th><td><?php echo esc_html( number_format_i18n( $row['samples'] ) ); ?></td><td><?php echo esc_html( null === $row['cache_hit_ratio'] ? __( 'Not available', 'nakhostin-performance-engine' ) : $row['cache_hit_ratio'] . '%' ); ?></td><td><?php echo esc_html( $this->format( $row['php_ms'], 'backend_generation_ms' ) ); ?></td><td><?php echo esc_html( $this->format( $row['database_ms'], 'database_query_time_ms' ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php endforeach; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_ACTION ); ?>"><?php wp_nonce_field( self::CLEAR_ACTION ); ?><?php submit_button( __( 'Clear performance history', 'nakhostin-performance-engine' ) ); ?></form>
		</div><?php
	}

	private function render_lighthouse(): void {
		$history = new LighthouseHistory();
		$rows = $history->all();
		$comparisons = $history->comparisons( $rows );
		$status = isset( $_GET['npe_lighthouse'] ) && is_string( $_GET['npe_lighthouse'] ) ? sanitize_key( wp_unslash( $_GET['npe_lighthouse'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status.
		$profiles = array( 'mobile' => __( 'Mobile', 'nakhostin-performance-engine' ), 'desktop' => __( 'Desktop', 'nakhostin-performance-engine' ) );
		$phases = array( 'baseline' => __( 'Baseline', 'nakhostin-performance-engine' ), 'candidate' => __( 'Candidate', 'nakhostin-performance-engine' ) );
		$opportunities = array(
			'lcp-discovery-insight'       => __( 'Discover the LCP resource earlier', 'nakhostin-performance-engine' ),
			'render-blocking-insight'     => __( 'Reduce render-blocking resources', 'nakhostin-performance-engine' ),
			'image-delivery-insight'      => __( 'Improve image delivery', 'nakhostin-performance-engine' ),
			'font-display-insight'        => __( 'Improve font display', 'nakhostin-performance-engine' ),
			'duplicated-javascript-insight' => __( 'Reduce duplicate JavaScript', 'nakhostin-performance-engine' ),
			'unused-javascript'           => __( 'Review unused JavaScript', 'nakhostin-performance-engine' ),
			'unused-css-rules'            => __( 'Review unused CSS', 'nakhostin-performance-engine' ),
		);
		?>
		<section id="npe-lighthouse" class="npe-card">
			<h2><?php echo esc_html__( 'Lighthouse laboratory history', 'nakhostin-performance-engine' ); ?></h2>
			<p><?php echo esc_html__( 'Import Lighthouse JSON reports for this site. The table shows laboratory runs. Real-user Core Web Vitals and INP require field data.', 'nakhostin-performance-engine' ); ?></p>
			<?php if ( 'saved' === $status || 'rejected' === $status ) : ?>
				<p role="status"><?php echo 'saved' === $status ? esc_html__( 'Lighthouse report saved.', 'nakhostin-performance-engine' ) : esc_html__( 'Report rejected. Check the site URL, file size, and required Lighthouse metrics.', 'nakhostin-performance-engine' ); ?></p>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
				<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
				<label><?php echo esc_html__( 'Lighthouse JSON', 'nakhostin-performance-engine' ); ?> <input type="file" name="npe_lighthouse_report" accept=".json,application/json" required></label>
				<label><?php echo esc_html__( 'Run type', 'nakhostin-performance-engine' ); ?>
					<select name="npe_phase"><?php foreach ( $phases as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				</label>
				<button class="button"><?php echo esc_html__( 'Import report', 'nakhostin-performance-engine' ); ?></button>
			</form>
			<?php if ( $comparisons ) : ?>
				<h3><?php echo esc_html__( 'Baseline and candidate medians', 'nakhostin-performance-engine' ); ?></h3>
				<p><?php echo esc_html__( 'Each median uses the three most recently imported runs per phase for the same URL, profile, Lighthouse version, and configuration.', 'nakhostin-performance-engine' ); ?></p>
				<table class="widefat striped">
					<thead><tr><th scope="col"><?php echo esc_html__( 'Page', 'nakhostin-performance-engine' ); ?></th><th scope="col"><?php echo esc_html__( 'Profile', 'nakhostin-performance-engine' ); ?></th><th scope="col"><?php echo esc_html__( 'Score', 'nakhostin-performance-engine' ); ?></th><th scope="col">LCP (ms)</th><th scope="col">CLS</th><th scope="col">TBT (ms)</th></tr></thead>
					<tbody><?php foreach ( $comparisons as $comparison ) : ?>
						<tr>
							<th scope="row" dir="ltr"><?php echo esc_html( $comparison['path'] ); ?></th>
							<td><?php echo esc_html( $profiles[ $comparison['profile'] ] . ' · ' . $comparison['engine'] ); ?></td>
							<?php foreach ( array( 'score', 'lcp_ms', 'cls', 'tbt_ms' ) as $metric ) : ?>
								<td dir="ltr"><?php echo esc_html( $comparison['baseline'][ $metric ] . ' → ' . $comparison['candidate'][ $metric ] ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?></tbody>
				</table>
			<?php endif; ?>
			<?php if ( $rows ) : ?>
				<table class="widefat striped">
					<thead><tr>
						<?php foreach ( array( __( 'Date', 'nakhostin-performance-engine' ), __( 'Page', 'nakhostin-performance-engine' ), __( 'Profile', 'nakhostin-performance-engine' ), __( 'Run type', 'nakhostin-performance-engine' ), __( 'Score', 'nakhostin-performance-engine' ), 'LCP (ms)', 'INP', 'CLS', 'TBT (ms)', 'FCP (ms)', __( 'Speed Index (ms)', 'nakhostin-performance-engine' ) ) as $heading ) : ?><th scope="col"><?php echo esc_html( $heading ); ?></th><?php endforeach; ?>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', $row['measured_at'] ) ); ?></td>
							<th scope="row" dir="ltr"><?php echo esc_html( $row['path'] ); ?></th>
							<td><?php echo esc_html( $profiles[ $row['profile'] ] ); ?></td>
							<td><?php echo esc_html( $phases[ $row['phase'] ] ); ?></td>
							<td><?php echo esc_html( $row['score'] ); ?></td>
							<td><?php echo esc_html( $row['metrics']['lcp_ms'] ); ?></td><td>—</td>
							<?php foreach ( array( 'cls', 'tbt_ms', 'fcp_ms', 'si_ms' ) as $metric ) : ?><td><?php echo esc_html( $row['metrics'][ $metric ] ); ?></td><?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ( $rows[0]['opportunities'] ) : ?>
					<h3><?php echo esc_html__( 'Estimated opportunities from the latest imported run', 'nakhostin-performance-engine' ); ?></h3>
					<ul>
						<?php foreach ( $rows[0]['opportunities'] as $key => $saving ) : ?><li><?php echo esc_html( $opportunities[ $key ] . ' · ' . number_format_i18n( $saving ) . ' ms' ); ?></li><?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	private function authorize(): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to clear performance diagnostics.', 'nakhostin-performance-engine' ) ); } check_admin_referer( self::CLEAR_ACTION ); }
	private function format( $value, string $metric ): string { if ( null === $value ) { return __( 'Not available', 'nakhostin-performance-engine' ); } return 'memory_peak_bytes' === $metric ? size_format( (int) $value ) : ( false !== strpos( $metric, '_ms' ) ? number_format_i18n( $value ) . ' ms' : number_format_i18n( $value ) ); }
	private function metric_labels(): array { return array( 'backend_generation_ms' => __( 'Backend PHP generation', 'nakhostin-performance-engine' ), 'total_backend_ms' => __( 'Total backend time', 'nakhostin-performance-engine' ), 'wordpress_bootstrap_ms' => __( 'WordPress bootstrap observed at plugins_loaded', 'nakhostin-performance-engine' ), 'plugin_bootstrap_ms' => __( 'Bootstrap through plugins_loaded', 'nakhostin-performance-engine' ), 'theme_bootstrap_ms' => __( 'Theme bootstrap', 'nakhostin-performance-engine' ), 'pre_template_ms' => __( 'Pre-template processing', 'nakhostin-performance-engine' ), 'response_generation_ms' => __( 'Template response generation', 'nakhostin-performance-engine' ), 'database_query_count' => __( 'Database query count', 'nakhostin-performance-engine' ), 'database_query_time_ms' => __( 'Database query time when SAVEQUERIES is available', 'nakhostin-performance-engine' ), 'database_slow_100_count' => __( 'Queries slower than 100 ms', 'nakhostin-performance-engine' ), 'database_slow_250_count' => __( 'Queries slower than 250 ms', 'nakhostin-performance-engine' ), 'database_max_query_ms' => __( 'Slowest query duration', 'nakhostin-performance-engine' ), 'memory_peak_bytes' => __( 'Peak PHP memory', 'nakhostin-performance-engine' ) ); }
	private function breakdown_labels(): array { return array( 'page_type' => __( 'Page-type breakdown', 'nakhostin-performance-engine' ), 'template' => __( 'Template breakdown', 'nakhostin-performance-engine' ), 'component' => __( 'Component breakdown', 'nakhostin-performance-engine' ), 'cache_state' => __( 'Cache-state breakdown', 'nakhostin-performance-engine' ) ); }
}
