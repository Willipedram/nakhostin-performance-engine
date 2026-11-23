<?php
/** NPE administration overview dashboard. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CacheOperationsState;
use Nakhostin\PerformanceEngine\Cache\PageCacheStoreInterface;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\DOM\DOMAnalysisQueue;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;

final class DashboardPage {
	/** @var Settings */ private $settings;
	/** @var PageCacheStoreInterface */ private $cache_store;
	/** @var CacheMetrics */ private $cache_metrics;
	/** @var CacheOperationsState */ private $operations;
	/** @var PerformanceStorage */ private $performance;
	/** @var PerformanceAggregator */ private $aggregator;
	/** @var JavaScriptStorage */ private $javascript;
	/** @var Capabilities */ private $capabilities;
	/** @var DOMAnalysisQueue|null */ private $dom_queue;

	public function __construct( Settings $settings, PageCacheStoreInterface $cache_store, CacheMetrics $cache_metrics, CacheOperationsState $operations, PerformanceStorage $performance, PerformanceAggregator $aggregator, JavaScriptStorage $javascript, Capabilities $capabilities, ?DOMAnalysisQueue $dom_queue = null ) {
		$this->settings = $settings; $this->cache_store = $cache_store; $this->cache_metrics = $cache_metrics; $this->operations = $operations; $this->performance = $performance; $this->aggregator = $aggregator; $this->javascript = $javascript; $this->capabilities = $capabilities;
		$this->dom_queue = $dom_queue;
	}

	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ), 5 ); }
	public function add_menu(): void {
		AdminShell::menu( __( 'Performance Engine', 'nakhostin-performance-engine' ), __( 'NPE', 'nakhostin-performance-engine' ), Capabilities::MANAGE, AdminPage::SLUG, array( $this, 'render' ), 'dashicons-dashboard', 80 );
		AdminShell::submenu( AdminPage::SLUG, __( 'Overview', 'nakhostin-performance-engine' ), __( 'Overview', 'nakhostin-performance-engine' ), Capabilities::MANAGE, AdminPage::SLUG, array( $this, 'render' ) );
	}

	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to view the NPE dashboard.', 'nakhostin-performance-engine' ) ); }
		$cache       = $this->cache_metrics->all();
		$storage     = $this->cache_store->statistics();
		$performance = $this->aggregator->summarize( $this->performance->all() );
		$requests    = (int) $cache['hits'] + (int) $cache['stale_hits'] + (int) $cache['misses'];
		$hit_ratio   = $requests ? round( 100 * ( (int) $cache['hits'] + (int) $cache['stale_hits'] ) / $requests, 1 ) : null;
		$php_average = $performance['metrics']['backend_generation_ms']['average'];
		$js          = $this->javascript->latest();
		$js_data     = $js ? $js->to_array() : array();
		$sizes       = $js_data;
		$state       = $this->operations->all();
		$dom         = $this->dom_queue ? $this->dom_queue->report( (int) $this->settings->get( 'dom.batch_size', 3 ), (int) $this->settings->get( 'dom.scan_interval', 10 ) ) : array( 'completed' => 0, 'progress' => 0, 'estimated_seconds' => 0 );
		?>
		<div class="wrap npe-admin npe-dashboard" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<p class="npe-page-intro"><?php echo esc_html__( 'A safe, measurable performance control center for WordPress.', 'nakhostin-performance-engine' ); ?></p>
			<?php $this->render_warnings(); ?>
			<div class="npe-metric-grid" role="list" aria-label="<?php echo esc_attr( __( 'Performance summary', 'nakhostin-performance-engine' ) ); ?>">
				<?php AdminComponents::metric( __( 'Cache hit ratio', 'nakhostin-performance-engine' ), null === $hit_ratio ? '—' : $hit_ratio . '%', __( 'Successful public cache responses', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'Cache size', 'nakhostin-performance-engine' ), size_format( (int) ( $storage['size'] ?? 0 ) ), sprintf( __( '%s cached entries', 'nakhostin-performance-engine' ), number_format_i18n( (int) ( $storage['entries'] ?? 0 ) ) ) ); ?>
				<?php AdminComponents::metric( __( 'Average backend time', 'nakhostin-performance-engine' ), null === $php_average ? '—' : number_format_i18n( $php_average ) . ' ms', __( 'PHP generation; not network TTFB', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'Page types', 'nakhostin-performance-engine' ), number_format_i18n( count( $performance['breakdown']['page_type'] ) ), __( 'Observed performance groups', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'CSS reduction', 'nakhostin-performance-engine' ), '—', __( 'Not measured in this phase', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'JavaScript reduction', 'nakhostin-performance-engine' ), $this->reduction( $sizes ), __( 'Measured local JavaScript sources', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'Asset requests', 'nakhostin-performance-engine' ), number_format_i18n( (int) ( $sizes['measured_files'] ?? 0 ) ), __( 'Files in the latest JavaScript analysis', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'Cache warmup', 'nakhostin-performance-engine' ), empty( $state['last_warmup'] ) ? __( 'Not run', 'nakhostin-performance-engine' ) : __( 'Completed', 'nakhostin-performance-engine' ), __( 'Latest asynchronous warmup status', 'nakhostin-performance-engine' ) ); ?>
				<?php AdminComponents::metric( __( 'Pages analyzed', 'nakhostin-performance-engine' ), number_format_i18n( (int) $dom['completed'] ), sprintf( __( 'Automatic DOM scan progress: %d%%', 'nakhostin-performance-engine' ), (int) $dom['progress'] ) ); ?>
			</div>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . DOMAdminPage::SLUG ) ); ?>"><?php echo esc_html__( 'Analyze Page Assets', 'nakhostin-performance-engine' ); ?></a></p>
		</div><?php
	}


	private function reduction( array $sizes ): string { $original = (int) ( $sizes['original_size'] ?? 0 ); $optimized = (int) ( $sizes['optimized_size'] ?? 0 ); return $original > 0 ? round( max( 0, $original - $optimized ) * 100 / $original, 1 ) . '%' : '—'; }
	private function render_warnings(): void { $warnings = array(); if ( ! $this->settings->get( 'cache.enabled', false ) ) { $warnings[] = __( 'The NPE page cache is disabled.', 'nakhostin-performance-engine' ); } if ( ! $this->settings->get( 'performance.enabled', false ) ) { $warnings[] = __( 'Performance sampling is disabled, so backend averages are unavailable.', 'nakhostin-performance-engine' ); } if ( ! $warnings ) { return; } ?><div class="notice notice-warning npe-notice" role="status"><p><strong><?php echo esc_html__( 'Attention required', 'nakhostin-performance-engine' ); ?></strong></p><ul><?php foreach ( $warnings as $warning ) : ?><li><?php echo esc_html( $warning ); ?></li><?php endforeach; ?></ul></div><?php }
}
