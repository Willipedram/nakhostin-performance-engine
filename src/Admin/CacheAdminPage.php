<?php
/** Cache Overview administration screen. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Admin;

use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use Nakhostin\PerformanceEngine\Cache\CacheOperationsState;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheWarmer;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupManager;
use Nakhostin\PerformanceEngine\Cache\FragmentStoreInterface;
use Nakhostin\PerformanceEngine\Cache\ObjectCacheDetector;
use Nakhostin\PerformanceEngine\Cache\PageCacheStoreInterface;
use Nakhostin\PerformanceEngine\Cache\PurgeRequest;
use Nakhostin\PerformanceEngine\Cache\SmartPurgeManager;
use Nakhostin\PerformanceEngine\Core\Capabilities;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedAdapter;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedDetector;

final class CacheAdminPage {
	public const SLUG = 'npe-cache-overview';
	public const PURGE_ACTION = 'npe_purge_page_cache';
	public const REBUILD_ACTION = 'npe_rebuild_page_cache';
	/** @var PageCacheStoreInterface */ private $store; /** @var CacheMetrics */ private $metrics; /** @var CachePurger */ private $purger; /** @var CacheWarmer */ private $warmer; /** @var Settings */ private $settings; /** @var Capabilities */ private $capabilities;
	/** @var FragmentStoreInterface|null */ private $fragments;
	/** @var ObjectCacheDetector|null */ private $object_cache;
	/** @var SmartPurgeManager|null */ private $smart_purge;
	/** @var CacheWarmupManager|null */ private $warmup_manager;
	/** @var CacheDependencyGraph|null */ private $dependency_graph;
	/** @var CacheOperationsState|null */ private $operations_state;
	/** @var LiteSpeedDetector|null */ private $litespeed_detector;
	/** @var LiteSpeedAdapter|null */ private $litespeed_adapter;
	public function __construct( PageCacheStoreInterface $store, CacheMetrics $metrics, CachePurger $purger, CacheWarmer $warmer, Settings $settings, Capabilities $capabilities, ?FragmentStoreInterface $fragments = null, ?ObjectCacheDetector $object_cache = null, ?SmartPurgeManager $smart_purge = null, ?CacheWarmupManager $warmup_manager = null, ?CacheDependencyGraph $dependency_graph = null, ?CacheOperationsState $operations_state = null, ?LiteSpeedDetector $litespeed_detector = null, ?LiteSpeedAdapter $litespeed_adapter = null ) { $this->store = $store; $this->metrics = $metrics; $this->purger = $purger; $this->warmer = $warmer; $this->settings = $settings; $this->capabilities = $capabilities; $this->fragments = $fragments; $this->object_cache = $object_cache; $this->smart_purge = $smart_purge; $this->warmup_manager = $warmup_manager; $this->dependency_graph = $dependency_graph; $this->operations_state = $operations_state; $this->litespeed_detector = $litespeed_detector; $this->litespeed_adapter = $litespeed_adapter; }
	public function register(): void { add_action( 'admin_menu', array( $this, 'add_menu' ) ); add_action( 'admin_post_' . self::PURGE_ACTION, array( $this, 'handle_purge' ) ); add_action( 'admin_post_' . self::REBUILD_ACTION, array( $this, 'handle_rebuild' ) ); }
	public function add_menu(): void { add_submenu_page( AdminPage::SLUG, __( 'Cache Overview', 'nakhostin-performance-engine' ), __( 'Cache', 'nakhostin-performance-engine' ), Capabilities::MANAGE, self::SLUG, array( $this, 'render' ) ); }
	public function handle_purge(): void { $this->authorize( self::PURGE_ACTION ); if ( $this->smart_purge ) { $this->smart_purge->request( PurgeRequest::create( 'full', 'all', 'manual_admin_purge' ) ); } else { $this->purger->purge_all(); } $this->redirect( 'purged' ); }
	public function handle_rebuild(): void { $this->authorize( self::REBUILD_ACTION ); if ( $this->smart_purge ) { $this->smart_purge->request( PurgeRequest::create( 'full', 'all', 'manual_admin_rebuild', array( home_url( '/' ) ) ) ); } else { $urls = $this->store->cached_urls(); $urls[] = home_url( '/' ); $this->purger->purge_all(); $this->warmer->schedule( $urls ); } $this->redirect( 'scheduled' ); }
	public function render(): void {
		if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to manage the page cache.', 'nakhostin-performance-engine' ) ); }
		$storage = $this->store->statistics(); $metrics = $this->metrics->all(); $requests = (int) $metrics['hits'] + (int) $metrics['misses'] + (int) $metrics['stale_hits']; $rate = $requests ? round( 100 * ( (int) $metrics['hits'] + (int) $metrics['stale_hits'] ) / $requests, 1 ) : 0;
		?>
		<div class="wrap npe-admin" dir="<?php echo esc_attr( is_rtl() ? 'rtl' : 'ltr' ); ?>">
			<h1><?php echo esc_html__( 'Cache Overview', 'nakhostin-performance-engine' ); ?></h1>
			<p><?php echo esc_html__( 'NPE provides an application-level cache. Generic hosting cannot bypass the WordPress bootstrap without a separately configured server adapter.', 'nakhostin-performance-engine' ); ?></p>
			<table class="widefat striped"><tbody>
			<tr><th><?php echo esc_html__( 'Status', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $this->settings->get( 'cache.enabled', false ) ? __( 'Enabled', 'nakhostin-performance-engine' ) : __( 'Disabled', 'nakhostin-performance-engine' ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'Backend', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( (string) $storage['backend'] ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'Entries', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $storage['entries'] ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'Size', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( size_format( (int) $storage['size'] ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'TTL', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( sprintf( __( '%d seconds', 'nakhostin-performance-engine' ), (int) $this->settings->get( 'cache.ttl', 300 ) ) ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'Hit rate', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $rate . '%' ); ?></td></tr>
			<tr><th><?php echo esc_html__( 'Hits / misses', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( (int) $metrics['hits'] + (int) $metrics['stale_hits'] ); ?> / <?php echo esc_html( (int) $metrics['misses'] ); ?></td></tr>
			</tbody></table>
			<?php $this->render_fragment_diagnostics(); ?>
			<?php $this->render_operations(); ?>
			<?php $this->render_litespeed(); ?>
			<div class="npe-actions"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::PURGE_ACTION ); ?>"><?php wp_nonce_field( self::PURGE_ACTION ); ?><?php submit_button( __( 'Purge cache', 'nakhostin-performance-engine' ) ); ?></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="<?php echo esc_attr( self::REBUILD_ACTION ); ?>"><?php wp_nonce_field( self::REBUILD_ACTION ); ?><?php submit_button( __( 'Purge and schedule rebuild', 'nakhostin-performance-engine' ) ); ?></form></div>
		</div><?php
	}
	private function authorize( string $action ): void { if ( ! $this->capabilities->can_manage() ) { wp_die( esc_html__( 'You are not allowed to manage the page cache.', 'nakhostin-performance-engine' ) ); } check_admin_referer( $action ); }
	private function redirect( string $notice ): void { wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'npe_notice' => sanitize_key( $notice ) ), admin_url( 'admin.php' ) ) ); exit; }
	private function render_fragment_diagnostics(): void {
		$object = $this->object_cache ? $this->object_cache->detect() : array( 'available' => false, 'persistent' => false, 'backend' => 'unknown', 'redis' => false, 'memcached' => false );
		$stats  = $this->fragments ? $this->fragments->statistics() : array( 'backend' => 'unavailable', 'entries' => 0, 'hits' => 0, 'misses' => 0 );
		$total  = (int) $stats['hits'] + (int) $stats['misses'];
		$ratio  = $total ? round( 100 * (int) $stats['hits'] / $total, 1 ) : 0;
		?>
		<h2><?php echo esc_html__( 'Fragment and Object Cache', 'nakhostin-performance-engine' ); ?></h2>
		<table class="widefat striped"><tbody>
		<tr><th><?php echo esc_html__( 'Object cache API', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $object['available'] ? __( 'Available', 'nakhostin-performance-engine' ) : __( 'Unavailable', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Persistent object cache', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $object['persistent'] ? __( 'Yes', 'nakhostin-performance-engine' ) : __( 'No', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Detected object-cache backend', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( (string) $object['backend'] ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Redis extension', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $object['redis'] ? __( 'Available', 'nakhostin-performance-engine' ) : __( 'Unavailable', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Memcached extension', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $object['memcached'] ? __( 'Available', 'nakhostin-performance-engine' ) : __( 'Unavailable', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Fragment backend', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( (string) $stats['backend'] ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Fragment entries', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) $stats['entries'] ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Fragment hit ratio', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $ratio . '%' ); ?></td></tr>
		</tbody></table>
		<h3><?php echo esc_html__( 'Top fragments', 'nakhostin-performance-engine' ); ?></h3>
		<ul>
		<?php foreach ( $this->fragments ? $this->fragments->top( 10 ) : array() as $fragment ) : ?>
			<li><?php echo esc_html( (string) $fragment['name'] ); ?> — <?php echo esc_html( number_format_i18n( (int) $fragment['hits'] ) ); ?></li>
		<?php endforeach; ?>
		</ul>
		<?php
	}
	private function render_operations(): void {
		$purge_counts = $this->smart_purge ? $this->smart_purge->queue()->counts() : array( 'pending' => 0, 'running' => 0, 'failed' => 0 );
		$warm_counts  = $this->warmup_manager ? $this->warmup_manager->queue()->counts() : array( 'pending' => 0, 'running' => 0, 'failed' => 0 );
		$graph        = $this->dependency_graph ? $this->dependency_graph->summary() : array( 'nodes' => 0, 'edges' => 0 );
		$state        = $this->operations_state ? $this->operations_state->all() : array();
		$last_purge   = (array) ( $state['last_purge'] ?? array() );
		$last_warmup  = (array) ( $state['last_warmup'] ?? array() );
		?>
		<h2><?php echo esc_html__( 'Smart Purge and Warmup', 'nakhostin-performance-engine' ); ?></h2>
		<table class="widefat striped"><tbody>
		<tr><th><?php echo esc_html__( 'Purge queue', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( sprintf( __( '%1$d pending, %2$d running, %3$d failed', 'nakhostin-performance-engine' ), $purge_counts['pending'], $purge_counts['running'], $purge_counts['failed'] ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Warmup queue', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( sprintf( __( '%1$d pending, %2$d running, %3$d failed', 'nakhostin-performance-engine' ), $warm_counts['pending'], $warm_counts['running'], $warm_counts['failed'] ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Dependency graph', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( sprintf( __( '%1$d nodes, %2$d relationships', 'nakhostin-performance-engine' ), $graph['nodes'], $graph['edges'] ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Last purge reason', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( (string) ( $last_purge['reason'] ?? __( 'None', 'nakhostin-performance-engine' ) ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Last warmup result', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( isset( $last_warmup['result'] ) ? (string) wp_json_encode( $last_warmup['result'] ) : __( 'None', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Failed jobs', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( number_format_i18n( $purge_counts['failed'] + $warm_counts['failed'] ) ); ?></td></tr>
		</tbody></table>
		<h3><?php echo esc_html__( 'Dependency relationships', 'nakhostin-performance-engine' ); ?></h3>
		<ul>
		<?php foreach ( array_slice( $this->dependency_graph ? $this->dependency_graph->all() : array(), 0, 20, true ) as $node => $metadata ) : ?>
			<li><?php echo esc_html( (string) $node . ' → ' . implode( ', ', (array) ( $metadata['dependents'] ?? array() ) ) ); ?></li>
		<?php endforeach; ?>
		</ul>
		<h3><?php echo esc_html__( 'Queued jobs', 'nakhostin-performance-engine' ); ?></h3>
		<ul>
		<?php foreach ( $this->operation_jobs( array( 'pending', 'running' ) ) as $job ) : ?>
			<li><?php echo esc_html( (string) $job['queue'] . ': ' . (string) $job['type'] . ' — ' . (string) $job['reason'] ); ?></li>
		<?php endforeach; ?>
		</ul>
		<h3><?php echo esc_html__( 'Failed jobs', 'nakhostin-performance-engine' ); ?></h3>
		<ul>
		<?php foreach ( $this->operation_jobs( array( 'failed' ) ) as $job ) : ?>
			<li><?php echo esc_html( (string) $job['queue'] . ': ' . (string) $job['reason'] . ' — ' . (string) $job['last_error'] ); ?></li>
		<?php endforeach; ?>
		</ul>
		<?php
	}
	private function operation_jobs( array $statuses ): array {
		$jobs = array();
		$queues = array( 'purge' => $this->smart_purge ? $this->smart_purge->queue() : null, 'warmup' => $this->warmup_manager ? $this->warmup_manager->queue() : null );
		foreach ( $queues as $queue_name => $queue ) {
			if ( ! $queue ) { continue; }
			foreach ( $queue->all() as $job ) {
				if ( in_array( $job['status'] ?? '', $statuses, true ) ) {
					$job['queue'] = $queue_name;
					$jobs[]       = $job;
				}
			}
		}
		return array_slice( $jobs, -10 );
	}
	private function render_litespeed(): void {
		$detection = $this->litespeed_detector ? $this->litespeed_detector->detect() : array( 'installed' => false, 'active' => false, 'cache_capable' => false, 'version' => '' );
		$mode      = $this->litespeed_adapter ? $this->litespeed_adapter->mode() : 'independent';
		$owner     = $this->litespeed_adapter ? $this->litespeed_adapter->page_cache_owner() : 'npe';
		$mode_labels = array(
			LiteSpeedAdapter::MODE_INDEPENDENT => __( 'Independent', 'nakhostin-performance-engine' ),
			LiteSpeedAdapter::MODE_COMPATIBLE  => __( 'Compatible', 'nakhostin-performance-engine' ),
			LiteSpeedAdapter::MODE_COOPERATIVE => __( 'Cooperative', 'nakhostin-performance-engine' ),
		);
		$owner_label = 'litespeed' === $owner ? __( 'LiteSpeed Cache', 'nakhostin-performance-engine' ) : __( 'NPE', 'nakhostin-performance-engine' );
		?>
		<h2><?php echo esc_html__( 'LiteSpeed Cache Integration', 'nakhostin-performance-engine' ); ?></h2>
		<table class="widefat striped"><tbody>
		<tr><th><?php echo esc_html__( 'Installed', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $detection['installed'] ? __( 'Yes', 'nakhostin-performance-engine' ) : __( 'No', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Active', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $detection['active'] ? __( 'Yes', 'nakhostin-performance-engine' ) : __( 'No', 'nakhostin-performance-engine' ) ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Integration mode', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $mode_labels[ $mode ] ?? $mode_labels[ LiteSpeedAdapter::MODE_INDEPENDENT ] ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Public page-cache owner', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( $owner_label ); ?></td></tr>
		<tr><th><?php echo esc_html__( 'Scoped purge forwarding', 'nakhostin-performance-engine' ); ?></th><td><?php echo esc_html( LiteSpeedAdapter::MODE_COOPERATIVE === $mode && $detection['active'] ? __( 'Enabled', 'nakhostin-performance-engine' ) : __( 'Disabled', 'nakhostin-performance-engine' ) ); ?></td></tr>
		</tbody></table>
		<?php
	}
}
