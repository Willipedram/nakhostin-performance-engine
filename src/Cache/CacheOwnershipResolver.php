<?php
/** Resolves one page-cache owner without coupling to integrations. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Cache;
use Nakhostin\PerformanceEngine\Contracts\PageCacheProviderInterface;
use Nakhostin\PerformanceEngine\Environment\EnvironmentReport;

final class CacheOwnershipResolver {
	/** @var PageCacheProviderInterface[] */ private $providers;
	public function __construct( array $providers ) { $this->providers = array_values( array_filter( $providers, static function ( $provider ): bool { return $provider instanceof PageCacheProviderInterface; } ) ); }
	public function resolve( EnvironmentReport $environment, bool $cache_enabled, bool $early_enabled ): CacheRuntimeMode {
		$dropin = (array) $environment->get( 'dropin', array() );
		if ( ! $cache_enabled ) { return new CacheRuntimeMode( CacheRuntimeMode::DISABLED, 'cache_disabled' ); }
		if ( ! empty( $dropin['npe_owned'] ) && ! $early_enabled ) { return new CacheRuntimeMode( CacheRuntimeMode::CONFLICT, 'orphaned_npe_dropin' ); }
		usort( $this->providers, static function ( PageCacheProviderInterface $a, PageCacheProviderInterface $b ): int { return $b->priority() <=> $a->priority(); } );
		foreach ( $this->providers as $provider ) {
			if ( ! $provider->is_available() ) { continue; }
			$id = $provider->id();
			if ( CacheRuntimeMode::LITESPEED === $id && $provider->can_serve_early() ) { return new CacheRuntimeMode( $id, 'litespeed_server_cache_available' ); }
			if ( CacheRuntimeMode::EXTERNAL === $id ) { return new CacheRuntimeMode( $id, ! empty( $dropin['exists'] ) ? 'foreign_advanced_cache_dropin' : 'external_cache_plugin_active' ); }
			if ( CacheRuntimeMode::NPE_EARLY === $id && $early_enabled && $provider->can_serve_early() ) { return new CacheRuntimeMode( $id, 'npe_early_cache_available' ); }
		}
		foreach ( $this->providers as $provider ) { if ( CacheRuntimeMode::NPE_APPLICATION === $provider->id() && $provider->is_available() ) { return new CacheRuntimeMode( CacheRuntimeMode::NPE_APPLICATION, 'application_cache_fallback' ); } }
		return new CacheRuntimeMode( CacheRuntimeMode::DISABLED, 'no_safe_provider' );
	}
}
