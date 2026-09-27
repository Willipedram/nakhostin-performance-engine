<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;
use Nakhostin\PerformanceEngine\Cache\CacheOwnershipResolver;
use Nakhostin\PerformanceEngine\Cache\CacheRuntimeMode;
use Nakhostin\PerformanceEngine\Cache\Providers\ExternalCacheProvider;
use Nakhostin\PerformanceEngine\Cache\Providers\NpeApplicationCacheProvider;
use Nakhostin\PerformanceEngine\Cache\Providers\NpeEarlyCacheProvider;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;
use PHPUnit\Framework\TestCase;

final class CacheOwnershipResolverTest extends TestCase {
	private function litespeed( bool $capable ): PageCacheProviderInterface { return new class( $capable ) implements PageCacheProviderInterface { private $capable; public function __construct( bool $capable ) { $this->capable = $capable; } public function id(): string { return 'litespeed'; } public function is_available(): bool { return true; } public function can_serve_early(): bool { return $this->capable; } public function priority(): int { return 400; } }; }
	private function resolve( array $data, bool $early = true, ?PageCacheProviderInterface $ls = null ): CacheRuntimeMode { $environment = new EnvironmentReport( $data ); return ( new CacheOwnershipResolver( array( $ls ?: $this->litespeed( false ), new ExternalCacheProvider( $environment ), new NpeEarlyCacheProvider( $environment ), new NpeApplicationCacheProvider() ) ) )->resolve( $environment, true, $early ); }
	public function test_prefers_capable_litespeed(): void { self::assertSame( 'litespeed', $this->resolve( array( 'supports_early_cache' => true, 'dropin' => array( 'exists' => false ) ), true, $this->litespeed( true ) )->owner() ); }
	public function test_foreign_dropin_owns_cache(): void { self::assertSame( 'external', $this->resolve( array( 'supports_early_cache' => false, 'dropin' => array( 'exists' => true, 'npe_owned' => false ) ) )->owner() ); }
	public function test_known_external_plugin_prevents_duplicate_page_cache(): void { self::assertSame( 'external', $this->resolve( array( 'supports_early_cache' => true, 'cache_plugins' => array( 'wp-rocket/wp-rocket.php' ), 'dropin' => array( 'exists' => false ) ) )->owner() ); }
	public function test_litespeed_plugin_without_server_capability_does_not_block_npe_early(): void { self::assertSame( 'npe_early', $this->resolve( array( 'supports_early_cache' => true, 'cache_plugins' => array( 'litespeed-cache/litespeed-cache.php' ), 'dropin' => array( 'exists' => false ) ) )->owner() ); }
	public function test_selects_early_then_application_fallback(): void { self::assertSame( 'npe_early', $this->resolve( array( 'supports_early_cache' => true, 'dropin' => array( 'exists' => false ) ) )->owner() ); self::assertSame( 'npe_application', $this->resolve( array( 'supports_early_cache' => true, 'dropin' => array( 'exists' => false ) ), false )->owner() ); }
	public function test_disabled_cache_has_no_owner(): void { $environment = new EnvironmentReport( array( 'supports_early_cache' => true, 'dropin' => array( 'exists' => false ) ) ); $mode = ( new CacheOwnershipResolver( array( new NpeEarlyCacheProvider( $environment ), new NpeApplicationCacheProvider() ) ) )->resolve( $environment, false, true ); self::assertSame( 'disabled', $mode->owner() ); }
}
