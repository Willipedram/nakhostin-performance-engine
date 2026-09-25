<?php
/**
 * Main plugin coordinator.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Core;

use Nakhostin\PerformanceEngine\Admin\AdminPage;
use Nakhostin\PerformanceEngine\Admin\ComponentsAdminPage;
use Nakhostin\PerformanceEngine\Admin\DOMAdminPage;
use Nakhostin\PerformanceEngine\Admin\JavaScriptAdminPage;
use Nakhostin\PerformanceEngine\Admin\CacheAdminPage;
use Nakhostin\PerformanceEngine\Admin\PerformanceAdminPage;
use Nakhostin\PerformanceEngine\Admin\DashboardPage;
use Nakhostin\PerformanceEngine\Admin\DiagnosticsAdminPage;
use Nakhostin\PerformanceEngine\Admin\LogsAdminPage;
use Nakhostin\PerformanceEngine\Admin\SectionAdminPage;
use Nakhostin\PerformanceEngine\Cache\CacheHeaderManager;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyBuilder;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyCollector;
use Nakhostin\PerformanceEngine\Cache\CacheDependencyGraph;
use Nakhostin\PerformanceEngine\Cache\CacheInvalidationSubscriber;
use Nakhostin\PerformanceEngine\Cache\CacheJobQueue;
use Nakhostin\PerformanceEngine\Cache\CacheMaintenanceWorker;
use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Cache\CacheOperationsState;
use Nakhostin\PerformanceEngine\Cache\CacheWarmer;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupManager;
use Nakhostin\PerformanceEngine\Cache\CacheWarmupTargets;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\FragmentCache;
use Nakhostin\PerformanceEngine\Cache\FragmentStoreFactory;
use Nakhostin\PerformanceEngine\Cache\FragmentStoreInterface;
use Nakhostin\PerformanceEngine\Cache\ObjectCacheDetector;
use Nakhostin\PerformanceEngine\Cache\PageCache;
use Nakhostin\PerformanceEngine\Cache\PageCacheRuntime;
use Nakhostin\PerformanceEngine\Cache\PageCacheStoreInterface;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\SmartPurgeManager;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use Nakhostin\PerformanceEngine\Components\BundleAssembler;
use Nakhostin\PerformanceEngine\Components\BundleIndex;
use Nakhostin\PerformanceEngine\Components\ComponentRegistry;
use Nakhostin\PerformanceEngine\Components\ComponentStorage;
use Nakhostin\PerformanceEngine\Contracts\LoggerInterface;
use Nakhostin\PerformanceEngine\Contracts\FragmentCacheInterface;
use Nakhostin\PerformanceEngine\Contracts\PerformanceMonitorInterface;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use Nakhostin\PerformanceEngine\DOM\DOMStorage;
use Nakhostin\PerformanceEngine\DOM\DOMStorageInterface;
use Nakhostin\PerformanceEngine\Infrastructure\DebugLogger;
use Nakhostin\PerformanceEngine\Infrastructure\Diagnostics;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\Integrations\Elementor\ElementorComponentAdapter;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedAdapter;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedCacheBridge;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedDetector;
use Nakhostin\PerformanceEngine\Integrations\LiteSpeed\LiteSpeedPurgeBridge;
use Nakhostin\PerformanceEngine\Integrations\WooCommerce\WooCommerceComponentAdapter;
use Nakhostin\PerformanceEngine\Integrations\WoodMart\WoodMartComponentAdapter;
use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptAnalyzer;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptBundleWriter;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptPlanner;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptStorage;
use Nakhostin\PerformanceEngine\JavaScript\LocalScriptSourceProvider;
use Nakhostin\PerformanceEngine\JavaScript\ScriptDiscovery;
use Nakhostin\PerformanceEngine\JavaScript\ScriptSafetyPolicy;
use Nakhostin\PerformanceEngine\JavaScript\ScriptStrategyApplier;
use Nakhostin\PerformanceEngine\Performance\PageContextResolver;
use Nakhostin\PerformanceEngine\Performance\PerformanceAggregator;
use Nakhostin\PerformanceEngine\Performance\PerformanceMonitor;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;

final class Plugin {
	/** @var self|null */
	private static $instance;

	/** @var ServiceRegistry */
	private $services;

	/** @var bool */
	private $registered = false;

	private function __construct() {
		$this->services = new ServiceRegistry();
		$this->register_services();
	}

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register(): void {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	public function boot(): void {
		$this->load_textdomain();

		$this->services->get( VersionManager::class )->maybe_upgrade();

		if ( is_admin() ) {
			$this->services->get( DashboardPage::class )->register();
			$this->services->get( CacheAdminPage::class )->register();
			$this->services->get( DOMAdminPage::class )->register();
			$this->services->get( 'npe.admin.css' )->register();
			$this->services->get( JavaScriptAdminPage::class )->register();
			$this->services->get( ComponentsAdminPage::class )->register();
			$this->services->get( 'npe.admin.woocommerce' )->register();
			$this->services->get( 'npe.admin.elementor' )->register();
			$this->services->get( 'npe.admin.litespeed' )->register();
			$this->services->get( PerformanceAdminPage::class )->register();
			$this->services->get( DiagnosticsAdminPage::class )->register();
			$this->services->get( AdminPage::class )->register();
			$this->services->get( LogsAdminPage::class )->register();
		}
		$this->services->get( PerformanceMonitor::class )->register();

		$this->services->get( CacheWarmer::class )->register();
		$this->services->get( CacheWarmupManager::class )->register();
		$this->services->get( CacheMaintenanceWorker::class )->register();
		$this->services->get( CacheInvalidationSubscriber::class )->register();
		$this->services->get( LiteSpeedAdapter::class )->register();
		if ( $this->services->get( Settings::class )->get( 'cache.enabled', false ) && ! is_admin() && $this->services->get( LiteSpeedAdapter::class )->should_run_npe_page_cache() ) {
			$this->services->get( PageCacheRuntime::class )->register();
		}

		/**
		 * Fires once the lightweight NPE foundation is ready.
		 *
		 * @param ServiceRegistry $services The plugin service registry.
		 */
		do_action( 'npe/loaded', $this->services );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'nakhostin-performance-engine',
			false,
			dirname( NPE_BASENAME ) . '/languages'
		);
	}

	public function services(): ServiceRegistry {
		return $this->services;
	}

	private function register_services(): void {
		$this->services->set( Settings::class, new Settings() );
		$this->services->set( Diagnostics::class, new Diagnostics() );
		$this->services->set( Capabilities::class, new Capabilities() );
		$this->services->set( MigrationManager::class, new MigrationManager() );
		$this->services->set(
			VersionManager::class,
			static function ( ServiceRegistry $services ): VersionManager {
				return new VersionManager( $services->get( MigrationManager::class ) );
			}
		);
		$this->services->set(
			FeatureFlags::class,
			static function ( ServiceRegistry $services ): FeatureFlags {
				return new FeatureFlags(
					$services->get( Settings::class ),
					array( 'dom' => true, 'javascript' => true, 'cache' => true, 'litespeed' => true, 'performance' => true )
				);
			}
		);
		$this->services->set(
			LoggerInterface::class,
			static function ( ServiceRegistry $services ): LoggerInterface {
				return new DebugLogger( $services->get( Settings::class ) );
			}
		);
		$this->services->set(
			AdminPage::class,
			static function ( ServiceRegistry $services ): AdminPage {
				return new AdminPage(
					$services->get( Settings::class ),
					$services->get( Diagnostics::class ),
					$services->get( Capabilities::class ),
					$services->get( FeatureFlags::class )
				);
			}
		);
		$this->services->set(
			DashboardPage::class,
			static function ( ServiceRegistry $services ): DashboardPage {
				return new DashboardPage( $services->get( Settings::class ), $services->get( PageCacheStoreInterface::class ), $services->get( CacheMetrics::class ), $services->get( CacheOperationsState::class ), $services->get( PerformanceStorage::class ), $services->get( PerformanceAggregator::class ), $services->get( JavaScriptStorage::class ), $services->get( Capabilities::class ) );
			}
		);
		$this->services->set( DiagnosticsAdminPage::class, static function ( ServiceRegistry $services ): DiagnosticsAdminPage { return new DiagnosticsAdminPage( $services->get( Diagnostics::class ), $services->get( Capabilities::class ) ); } );
		$this->services->set( LogsAdminPage::class, static function ( ServiceRegistry $services ): LogsAdminPage { return new LogsAdminPage( $services->get( Settings::class ), $services->get( Capabilities::class ) ); } );
		$this->services->set( 'npe.admin.css', static function ( ServiceRegistry $services ): SectionAdminPage { return new SectionAdminPage( 'npe-css', __( 'CSS', 'nakhostin-performance-engine' ), __( 'CSS Intelligence is reserved for a later phase. No stylesheet optimization is active.', 'nakhostin-performance-engine' ), 'css', $services->get( Settings::class ), $services->get( Capabilities::class ), $services->get( FeatureFlags::class ) ); } );
		$this->services->set( 'npe.admin.woocommerce', static function ( ServiceRegistry $services ): SectionAdminPage { return new SectionAdminPage( 'npe-woocommerce', __( 'WooCommerce', 'nakhostin-performance-engine' ), __( 'WooCommerce compatibility is defensive and does not publicly cache customer-specific data.', 'nakhostin-performance-engine' ), 'woocommerce', $services->get( Settings::class ), $services->get( Capabilities::class ), $services->get( FeatureFlags::class ) ); } );
		$this->services->set( 'npe.admin.elementor', static function ( ServiceRegistry $services ): SectionAdminPage { return new SectionAdminPage( 'npe-elementor', __( 'Elementor', 'nakhostin-performance-engine' ), __( 'Elementor detection is available without changing Elementor settings or generated files.', 'nakhostin-performance-engine' ), 'elementor', $services->get( Settings::class ), $services->get( Capabilities::class ), $services->get( FeatureFlags::class ) ); } );
		$this->services->set( 'npe.admin.litespeed', static function ( ServiceRegistry $services ): SectionAdminPage { return new SectionAdminPage( 'npe-litespeed', __( 'LiteSpeed', 'nakhostin-performance-engine' ), __( 'LiteSpeed cooperation uses public hooks and never changes LiteSpeed Cache configuration.', 'nakhostin-performance-engine' ), 'litespeed', $services->get( Settings::class ), $services->get( Capabilities::class ), $services->get( FeatureFlags::class ) ); } );
		$this->services->set( DOMComponentDetector::class, new DOMComponentDetector() );
		$this->services->set( DOMStateRegistry::class, new DOMStateRegistry() );
		$this->services->set( DOMSignature::class, new DOMSignature() );
		$this->services->set( DOMStorageInterface::class, new DOMStorage() );
		$this->services->set( ComponentStorage::class, new ComponentStorage() );
		$this->services->set( BundleIndex::class, new BundleIndex() );
		$this->services->set( WooCommerceComponentAdapter::class, new WooCommerceComponentAdapter() );
		$this->services->set( ElementorComponentAdapter::class, new ElementorComponentAdapter() );
		$this->services->set( WoodMartComponentAdapter::class, new WoodMartComponentAdapter() );
		$this->services->set( ScriptDiscovery::class, new ScriptDiscovery() );
		$this->services->set( ScriptSafetyPolicy::class, new ScriptSafetyPolicy() );
		$this->services->set( ConservativeMinifier::class, new ConservativeMinifier() );
		$this->services->set( JavaScriptStorage::class, new JavaScriptStorage() );
		$this->services->set( LocalScriptSourceProvider::class, new LocalScriptSourceProvider() );
		$this->services->set( ScriptStrategyApplier::class, new ScriptStrategyApplier() );
		$this->services->set( PerformanceStorage::class, new PerformanceStorage() );
		$this->services->set( PerformanceAggregator::class, new PerformanceAggregator() );
		$this->services->set( PageContextResolver::class, new PageContextResolver() );
		$this->services->set(
			PerformanceMonitor::class,
			static function ( ServiceRegistry $services ): PerformanceMonitor {
				return new PerformanceMonitor( $services->get( Settings::class ), $services->get( PerformanceStorage::class ), $services->get( PageContextResolver::class ) );
			}
		);
		$this->services->set( PerformanceMonitorInterface::class, static function ( ServiceRegistry $services ): PerformanceMonitorInterface { return $services->get( PerformanceMonitor::class ); } );
		$this->services->set(
			PerformanceAdminPage::class,
			static function ( ServiceRegistry $services ): PerformanceAdminPage {
				return new PerformanceAdminPage( $services->get( PerformanceStorage::class ), $services->get( PerformanceAggregator::class ), $services->get( Capabilities::class ) );
			}
		);
		$this->register_cache_services();
		$this->services->set(
			JavaScriptPlanner::class,
			static function ( ServiceRegistry $services ): JavaScriptPlanner {
				return new JavaScriptPlanner(
					$services->get( ScriptSafetyPolicy::class ),
					$services->get( ConservativeMinifier::class )
				);
			}
		);
		$this->services->set(
			JavaScriptAnalyzer::class,
			static function ( ServiceRegistry $services ): JavaScriptAnalyzer {
				return new JavaScriptAnalyzer( $services->get( JavaScriptPlanner::class ) );
			}
		);
		$this->services->set(
			JavaScriptBundleWriter::class,
			static function ( ServiceRegistry $services ): JavaScriptBundleWriter {
				$root = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : NPE_PATH . 'cache-data';
				return new JavaScriptBundleWriter( $services->get( ConservativeMinifier::class ), trailingslashit( $root ) . 'cache/nakhostin-performance-engine/assets/javascript' );
			}
		);
		$this->services->set(
			ComponentRegistry::class,
			static function ( ServiceRegistry $services ): ComponentRegistry {
				return new ComponentRegistry(
					$services->get( ComponentStorage::class ),
					$services->get( BundleIndex::class ),
					array(
						$services->get( WooCommerceComponentAdapter::class ),
						$services->get( ElementorComponentAdapter::class ),
						$services->get( WoodMartComponentAdapter::class )
					)
				);
			}
		);
		$this->services->set(
			BundleAssembler::class,
			static function ( ServiceRegistry $services ): BundleAssembler {
				return new BundleAssembler( $services->get( BundleIndex::class ) );
			}
		);
		$this->services->set(
			DOMAnalyzer::class,
			static function ( ServiceRegistry $services ): DOMAnalyzer {
				return new DOMAnalyzer(
					$services->get( DOMComponentDetector::class ),
					$services->get( DOMStateRegistry::class ),
					$services->get( DOMSignature::class )
				);
			}
		);
		$this->services->set(
			DOMAdminPage::class,
			static function ( ServiceRegistry $services ): DOMAdminPage {
				return new DOMAdminPage(
					$services->get( DOMAnalyzer::class ),
					$services->get( DOMStorageInterface::class ),
					$services->get( Capabilities::class ),
					$services->get( ComponentRegistry::class )
				);
			}
		);
		$this->services->set(
			ComponentsAdminPage::class,
			static function ( ServiceRegistry $services ): ComponentsAdminPage {
				return new ComponentsAdminPage(
					$services->get( ComponentRegistry::class ),
					$services->get( Capabilities::class )
				);
			}
		);
		$this->services->set(
			JavaScriptAdminPage::class,
			static function ( ServiceRegistry $services ): JavaScriptAdminPage {
				return new JavaScriptAdminPage(
					$services->get( ScriptDiscovery::class ),
					$services->get( JavaScriptAnalyzer::class ),
					$services->get( JavaScriptStorage::class ),
					$services->get( LocalScriptSourceProvider::class ),
					$services->get( ComponentRegistry::class ),
					$services->get( Settings::class ),
					$services->get( Capabilities::class )
				);
			}
		);
	}

	private function register_cache_services(): void {
		$this->services->set( URLNormalizer::class, new URLNormalizer() );
		$this->services->set( CacheRequestFactory::class, new CacheRequestFactory() );
		$this->services->set( CacheHeaderManager::class, new CacheHeaderManager() );
		$this->services->set( ObjectCacheDetector::class, new ObjectCacheDetector() );
		$this->services->set( LiteSpeedDetector::class, new LiteSpeedDetector() );
		$this->services->set( LiteSpeedPurgeBridge::class, new LiteSpeedPurgeBridge() );
		$this->services->set( CacheDependencyGraph::class, new CacheDependencyGraph() );
		$this->services->set( CacheDependencyCollector::class, new CacheDependencyCollector() );
		$this->services->set( CacheOperationsState::class, new CacheOperationsState() );
		$this->services->set( 'npe.cache.purge_queue', new CacheJobQueue( 'npe_cache_purge_queue' ) );
		$this->services->set( 'npe.cache.warmup_queue', new CacheJobQueue( 'npe_cache_warmup_queue' ) );
		$this->services->set(
			PageCacheStoreInterface::class,
			static function (): PageCacheStoreInterface {
				$root = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : NPE_PATH . 'cache-data';
				return new FilesystemCacheStore( trailingslashit( $root ) . 'cache/nakhostin-performance-engine' );
			}
		);
		$this->services->set(
			CacheMetrics::class,
			static function (): CacheMetrics {
				$root = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : NPE_PATH . 'cache-data';
				return new CacheMetrics( trailingslashit( $root ) . 'cache/nakhostin-performance-engine/metrics.json' );
			}
		);
		$this->services->set( CacheWarmer::class, static function ( ServiceRegistry $services ): CacheWarmer { return new CacheWarmer( $services->get( URLNormalizer::class ) ); } );
		$this->services->set( CachePurger::class, static function ( ServiceRegistry $services ): CachePurger { $config = $services->get( Settings::class )->get( 'cache', array() ); return new CachePurger( $services->get( PageCacheStoreInterface::class ), $services->get( URLNormalizer::class ), $services->get( CacheMetrics::class ), new QueryPolicy( $config['allowed_query_parameters'] ?? array() ) ); } );
		$this->services->set(
			LiteSpeedCacheBridge::class,
			static function ( ServiceRegistry $services ): LiteSpeedCacheBridge {
				$config = $services->get( Settings::class )->get( 'cache', array() );
				return new LiteSpeedCacheBridge(
					$services->get( CacheRequestFactory::class ),
					new CachePolicy( $config['excluded_paths'] ?? array() ),
					$services->get( CacheDependencyCollector::class ),
					$services->get( Settings::class )
				);
			}
		);
		$this->services->set(
			LiteSpeedAdapter::class,
			static function ( ServiceRegistry $services ): LiteSpeedAdapter {
				return new LiteSpeedAdapter(
					$services->get( LiteSpeedDetector::class ),
					$services->get( LiteSpeedCacheBridge::class ),
					$services->get( LiteSpeedPurgeBridge::class ),
					$services->get( Settings::class )
				);
			}
		);
		$this->services->set(
			FragmentStoreInterface::class,
			static function ( ServiceRegistry $services ): FragmentStoreInterface {
				$root    = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : NPE_PATH . 'cache-data';
				$factory = new FragmentStoreFactory(
					$services->get( ObjectCacheDetector::class ),
					trailingslashit( $root ) . 'cache/nakhostin-performance-engine/fragments'
				);
				return $factory->create();
			}
		);
		$this->services->set(
			FragmentCacheInterface::class,
			static function ( ServiceRegistry $services ): FragmentCacheInterface {
				return new FragmentCache( $services->get( FragmentStoreInterface::class ) );
			}
		);
		$this->services->set(
			CacheInvalidationSubscriber::class,
			static function ( ServiceRegistry $services ): CacheInvalidationSubscriber {
				return new CacheInvalidationSubscriber(
					$services->get( SmartPurgeManager::class ),
					$services->get( CacheDependencyBuilder::class ),
					$services->get( ComponentStorage::class )
				);
			}
		);
		$this->services->set( CacheDependencyBuilder::class, static function ( ServiceRegistry $services ): CacheDependencyBuilder { return new CacheDependencyBuilder( $services->get( CacheDependencyGraph::class ) ); } );
		$this->services->set( CacheWarmupTargets::class, static function ( ServiceRegistry $services ): CacheWarmupTargets { return new CacheWarmupTargets( $services->get( Settings::class ) ); } );
		$this->services->set( CacheWarmupManager::class, static function ( ServiceRegistry $services ): CacheWarmupManager { return new CacheWarmupManager( $services->get( 'npe.cache.warmup_queue' ), $services->get( URLNormalizer::class ) ); } );
		$this->services->set(
			SmartPurgeManager::class,
			static function ( ServiceRegistry $services ): SmartPurgeManager {
				return new SmartPurgeManager( $services->get( 'npe.cache.purge_queue' ), $services->get( CacheDependencyGraph::class ), $services->get( CachePurger::class ), $services->get( FragmentCacheInterface::class ), $services->get( CacheWarmupManager::class ), $services->get( BundleIndex::class ), $services->get( CacheWarmupTargets::class ) );
			}
		);
		$this->services->set( CacheMaintenanceWorker::class, static function ( ServiceRegistry $services ): CacheMaintenanceWorker { return new CacheMaintenanceWorker( $services->get( SmartPurgeManager::class ), $services->get( CacheWarmupManager::class ), $services->get( CacheWarmer::class ), $services->get( CacheOperationsState::class ) ); } );
		$this->services->set(
			PageCache::class,
			static function ( ServiceRegistry $services ): PageCache {
				$settings = $services->get( Settings::class ); $config = $settings->get( 'cache', array() );
				$keys = new CacheKeyGenerator( $services->get( URLNormalizer::class ), new QueryPolicy( $config['allowed_query_parameters'] ?? array() ), $config );
				return new PageCache( $services->get( PageCacheStoreInterface::class ), $keys, new CachePolicy( $config['excluded_paths'] ?? array() ), $services->get( CacheMetrics::class ), (int) ( $config['ttl'] ?? 300 ), (int) ( $config['stale_ttl'] ?? 60 ) );
			}
		);
		$this->services->set( PageCacheRuntime::class, static function ( ServiceRegistry $services ): PageCacheRuntime { return new PageCacheRuntime( $services->get( PageCache::class ), $services->get( CacheRequestFactory::class ), $services->get( CacheHeaderManager::class ), $services->get( CacheDependencyCollector::class ) ); } );
		$this->services->set(
			CacheAdminPage::class,
			static function ( ServiceRegistry $services ): CacheAdminPage {
				return new CacheAdminPage(
					$services->get( PageCacheStoreInterface::class ),
					$services->get( CacheMetrics::class ),
					$services->get( CachePurger::class ),
					$services->get( CacheWarmer::class ),
					$services->get( Settings::class ),
					$services->get( Capabilities::class ),
					$services->get( FragmentStoreInterface::class ),
					$services->get( ObjectCacheDetector::class ),
					$services->get( SmartPurgeManager::class ),
					$services->get( CacheWarmupManager::class ),
					$services->get( CacheDependencyGraph::class ),
					$services->get( CacheOperationsState::class ),
					$services->get( LiteSpeedDetector::class ),
					$services->get( LiteSpeedAdapter::class )
				);
			}
		);
	}
}
