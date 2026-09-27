<?php
/** Performance monitoring administration screen. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;

final class PerformanceAdminPage {
	public const SLUG = 'npe-performance-overview';
	public const CLEAR_ACTION = 'npe_clear_performance_samples';
	/** @var PerformanceStorage */ private $storage;
	/** @var PerformanceAggregator */ private $aggregator;
	/** @var Capabilities */ private $capabilities;

	public function __construct( PerformanceStorage $storage, PerformanceAggregator $aggregator, Capabilities $capabilities ) { $this->storage = $storage; $this->aggregator = $aggregator; $this->capabilities = $capabilities; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); add_action( 'admin_post_' . self::CLEAR_ACTION, array( $this, 'handle_clear' ) ); }
	public function add_menu(): void { add_submenu_page( AdminPage::SLUG, __( 'Performance Overview', 'nakhostin-performance-engine' ), __( 'Performance', 'nakhostin-performance-engine' ), Capabilities::MANAGE, self::SLUG, array( $this, 'render' ) ); }
	public function handle_clear(): void { $this->authorize(); $this->storage->clear(); wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) ); exit; }

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view performance diagnostics.', 'nakhostin-performance-engine' ) ); }
		$summary = $this->aggregator->summarize( $this->storage->all() );
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
		<h1><?php echo esc_html__( 'Performance Overview', 'nakhostin-performance-engine' ); ?></h1>
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

	private function authorize(): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to clear performance diagnostics.', 'nakhostin-performance-engine' ) ); } check_admin_referer( self::CLEAR_ACTION ); }
	private function format( $value, string $metric ): string { if ( null === $value ) { return __( 'Not available', 'nakhostin-performance-engine' ); } return 'memory_peak_bytes' === $metric ? size_format( (int) $value ) : ( false !== strpos( $metric, '_ms' ) ? number_format_i18n( $value ) . ' ms' : number_format_i18n( $value ) ); }
	private function metric_labels(): array { return array( 'backend_generation_ms' => __( 'Backend PHP generation', 'nakhostin-performance-engine' ), 'wordpress_bootstrap_ms' => __( 'WordPress bootstrap observed at plugins_loaded', 'nakhostin-performance-engine' ), 'plugin_loading_ms' => __( 'NPE plugin loading', 'nakhostin-performance-engine' ), 'theme_loading_ms' => __( 'Theme setup', 'nakhostin-performance-engine' ), 'response_generation_ms' => __( 'Response generation after template_redirect', 'nakhostin-performance-engine' ), 'database_query_count' => __( 'Database query count', 'nakhostin-performance-engine' ), 'database_query_time_ms' => __( 'Database query time when SAVEQUERIES is available', 'nakhostin-performance-engine' ), 'memory_peak_bytes' => __( 'Peak PHP memory', 'nakhostin-performance-engine' ) ); }
	private function breakdown_labels(): array { return array( 'page_type' => __( 'Page-type breakdown', 'nakhostin-performance-engine' ), 'template' => __( 'Template breakdown', 'nakhostin-performance-engine' ), 'component' => __( 'Component breakdown', 'nakhostin-performance-engine' ), 'cache_state' => __( 'Cache-state breakdown', 'nakhostin-performance-engine' ) ); }
}
