<?php
/** Repeatable micro-benchmark for NPE subsystem overhead. @package NakhostinPerformanceEngine */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require dirname( __DIR__ ) . '/Fixtures/wordpress-stubs.php';
require dirname( __DIR__, 2 ) . '/nakhostin-performance-engine.php';

use Nakhostin\PerformanceEngine\Cache\CacheEntry;
use Nakhostin\PerformanceEngine\Cache\CacheKeyGenerator;
use Nakhostin\PerformanceEngine\Cache\CacheMetrics;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CachePurger;
use Nakhostin\PerformanceEngine\Cache\CacheRequest;
use Nakhostin\PerformanceEngine\Cache\FilesystemCacheStore;
use Nakhostin\PerformanceEngine\Cache\PageCache;
use Nakhostin\PerformanceEngine\Cache\QueryPolicy;
use Nakhostin\PerformanceEngine\Cache\URLNormalizer;
use Nakhostin\PerformanceEngine\DOM\DOMAnalyzer;
use Nakhostin\PerformanceEngine\DOM\DOMComponentDetector;
use Nakhostin\PerformanceEngine\DOM\DOMSignature;
use Nakhostin\PerformanceEngine\DOM\DOMSnapshot;
use Nakhostin\PerformanceEngine\DOM\DOMStateRegistry;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;
use Nakhostin\PerformanceEngine\JavaScript\ConservativeMinifier;
use Nakhostin\PerformanceEngine\JavaScript\JavaScriptBundleWriter;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use Nakhostin\PerformanceEngine\Performance\PageContextResolver;
use Nakhostin\PerformanceEngine\Performance\PerformanceMonitor;
use Nakhostin\PerformanceEngine\Performance\PerformanceStorage;

$root       = sys_get_temp_dir() . '/npe-benchmark-' . getmypid();
$store      = new FilesystemCacheStore( $root . '/pages' );
$metrics    = new CacheMetrics( $root . '/metrics.json' );
$normalizer = new URLNormalizer();
$cache      = new PageCache( $store, new CacheKeyGenerator( $normalizer, new QueryPolicy() ), new CachePolicy(), $metrics );
$request    = new CacheRequest( 'GET', 'https://example.test/benchmark/' );
$cache->store( $request, '<html><body>cached</body></html>' );

$measure = static function ( callable $operation, int $iterations = 100 ): float {
	$start = hrtime( true );
	for ( $index = 0; $index < $iterations; ++$index ) { $operation(); }
	return round( ( hrtime( true ) - $start ) / 1000 / $iterations, 3 );
};

$settings = new Settings();
$results  = array();
$results['disabled_monitor_us'] = $measure( static function () use ( $settings ): void { ( new PerformanceMonitor( $settings, new PerformanceStorage(), new PageContextResolver() ) )->register(); } );
$results['enabled_idle_settings_us'] = $measure( static function () use ( $settings ): void { $settings->all(); } );
$results['cache_hit_lookup_us'] = $measure( static function () use ( $cache, $request ): void { $cache->lookup( $request ); } );
$results['cache_purge_url_us'] = $measure( static function () use ( $store, $request, $normalizer, $metrics ): void { $store->write( CacheEntry::create( hash( 'sha256', 'benchmark' ), 'https://example.test/benchmark/', 'cached', array(), 300, 0 ) ); ( new CachePurger( $store, $normalizer, $metrics, new QueryPolicy() ) )->purge_urls( array( $request->url() ) ); }, 20 );

if ( class_exists( 'DOMDocument' ) ) {
	$analyzer = new DOMAnalyzer( new DOMComponentDetector(), new DOMStateRegistry(), new DOMSignature() );
	$html     = '<!doctype html><html><body class="home"><header><nav></nav></header><main><article class="product-card"><a href="/product">Product</a></article></main><footer></footer></body></html>';
	$results['dom_analysis_us'] = $measure( static function () use ( $analyzer, $html ): void { $analyzer->create_manifest( new DOMSnapshot( $html ) ); }, 20 );
}

$asset_directory = $root . '/assets';
$writer          = new JavaScriptBundleWriter( new ConservativeMinifier(), $asset_directory );
$asset           = new ScriptAsset( array( 'handle' => 'benchmark', 'src' => '/benchmark.js' ) );
$results['asset_build_us'] = $measure( static function () use ( $writer, $asset, $asset_directory ): void { $writer->build( 'page', array( $asset ), array( 'benchmark' => 'window.NPEBenchmark = true;' ), $asset_directory ); }, 20 );
$results['warmup_schedule_us'] = $measure( static function () use ( $normalizer ): void { ( new \Nakhostin\PerformanceEngine\Cache\CacheWarmer( $normalizer ) )->schedule( array( 'https://example.test/' ) ); } );

echo wp_json_encode( array( 'unit' => 'microseconds_per_operation', 'results' => $results ), JSON_PRETTY_PRINT ) . PHP_EOL;

if ( is_dir( $root ) ) {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $item ) { $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() ); }
	rmdir( $root );
}
