<?php
/**
 * Coordinates request cacheability through LiteSpeed's public action API.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\LiteSpeed;

use Nakhostin\PerformanceEngine\Cache\CacheDependencyCollector;
use Nakhostin\PerformanceEngine\Cache\CachePolicy;
use Nakhostin\PerformanceEngine\Cache\CacheRequestFactory;
use Nakhostin\PerformanceEngine\Infrastructure\Settings;

final class LiteSpeedCacheBridge {
	/** @var CacheRequestFactory */ private $requests;
	/** @var CachePolicy */ private $policy;
	/** @var CacheDependencyCollector */ private $dependencies;
	/** @var Settings */ private $settings;
	/** @var string */ private $mode = LiteSpeedAdapter::MODE_COMPATIBLE;

	public function __construct( CacheRequestFactory $requests, CachePolicy $policy, CacheDependencyCollector $dependencies, Settings $settings ) {
		$this->requests     = $requests;
		$this->policy       = $policy;
		$this->dependencies = $dependencies;
		$this->settings     = $settings;
	}

	public function register( string $mode ): void {
		$this->mode = $mode;
		add_action( 'template_redirect', array( $this, 'coordinate_request' ), -1001 );
	}

	public function coordinate_request(): void {
		$request  = $this->requests->from_globals();
		$decision = $this->policy->classify_request( $request );
		if ( ! $decision->is_cacheable() ) {
			// Public LiteSpeed action: prevent storage of a private/unsafe response.
			do_action( 'litespeed_control_set_nocache', 'npe_' . $decision->reason() );
			return;
		}
		if ( LiteSpeedAdapter::MODE_COOPERATIVE !== $this->mode ) {
			return;
		}

		// Public LiteSpeed actions: cacheability, TTL, tags, and vary dimensions.
		do_action( 'litespeed_control_set_cacheable' );
		do_action( 'litespeed_control_set_ttl', (int) $this->settings->get( 'cache.ttl', 300 ) );
		foreach ( $this->dependencies->collect() as $tag ) {
			do_action( 'litespeed_tag_add', 'npe-' . sanitize_key( $tag ) );
		}
		$context = $request->context();
		if ( $this->settings->get( 'cache.vary_device', false ) && ! empty( $context['device'] ) ) {
			do_action( 'litespeed_vary_add', 'npe-device-' . sanitize_key( (string) $context['device'] ) );
		}
		if ( $this->settings->get( 'cache.vary_currency', false ) && ! empty( $context['currency'] ) ) {
			do_action( 'litespeed_vary_add', 'npe-currency-' . sanitize_key( (string) $context['currency'] ) );
		}
	}
}
