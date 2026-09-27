<?php
/**
 * Dependency-aware, conservative JavaScript plan builder.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

use Nakhostin\PerformanceEngine\Components\ComponentDefinition;

final class JavaScriptPlanner {
	/** @var ScriptSafetyPolicy */
	private $safety;

	/** @var ConservativeMinifier */
	private $minifier;

	public function __construct( ScriptSafetyPolicy $safety, ConservativeMinifier $minifier ) {
		$this->safety   = $safety;
		$this->minifier = $minifier;
	}

	public function plan(
		array $assets,
		array $components = array(),
		array $context = array(),
		array $options = array(),
		array $source_contents = array()
	): JavaScriptManifest {
		$graph       = new DependencyGraph( $assets );
		$diagnostics = $graph->diagnostics();
		$warnings    = $this->diagnostic_warnings( $diagnostics );
		$enqueued    = $this->enqueued_handles( $assets );
		$component   = $this->component_handles( $components, $graph );
		$page_roots  = isset( $options['page_handles'] ) && is_array( $options['page_handles'] )
			? $options['page_handles']
			: $enqueued;
		$core_roots  = isset( $options['core_handles'] ) && is_array( $options['core_handles'] )
			? $options['core_handles']
			: array();
		$has_cycle = ! empty( $diagnostics['cycles'] );
		$layers    = $has_cycle
			? array( 'core' => array(), 'component' => array(), 'page' => array() )
			: array(
				'core'      => $graph->closure( $core_roots ),
				'component' => $graph->closure( $component ),
				'page'      => $graph->closure( $page_roots ),
			);
		$assigned    = array();
		$bundles     = array();
		$retained    = array();
		if ( $has_cycle ) {
			foreach ( $enqueued as $handle ) {
				$retained[ $handle ] = array( 'dependency-cycle' );
			}
		}

		foreach ( $layers as $layer => $handles ) {
			$classics = array();
			$modules  = array();

			foreach ( $handles as $handle ) {
				if ( isset( $assigned[ $handle ] ) ) {
					continue;
				}
				$asset = $graph->asset( $handle );
				if ( null === $asset ) {
					continue;
				}

				$decision = $this->safety->evaluate( $asset, $context );
				if ( ! array_key_exists( $handle, $source_contents ) ) {
					$decision['bundle']    = false;
					$decision['reasons'][] = 'source-content-unavailable';
				}
				if ( isset( $diagnostics['missing'][ $handle ] ) ) {
					$decision['bundle']    = false;
					$decision['reasons'][] = 'missing-dependency';
				}
				if ( ! $decision['bundle'] ) {
					$retained[ $handle ] = $decision['reasons'];
					$assigned[ $handle ] = true;
					continue;
				}

				$asset_data = $asset->to_array();
				if ( $asset_data['module'] ) {
					$modules[] = $handle;
				} else {
					$classics[] = $handle;
				}
				$assigned[ $handle ] = true;
			}

			if ( ! empty( $classics ) ) {
				$bundles[] = $this->bundle( $layer, 'classic', $classics, $graph );
			}
			if ( ! empty( $modules ) ) {
				$bundles[] = $this->bundle( $layer, 'module', $modules, $graph );
			}
		}

		$strategies = $has_cycle
			? array( 'deferred' => array(), 'delayed' => array() )
			: $this->strategies( $graph, $enqueued, $context, $options, $warnings );
		foreach ( $retained as $handle => $reasons ) {
			$warnings[] = 'Retained ' . $handle . ': ' . implode( ', ', $reasons );
		}
		$asset_rows = array();
		foreach ( $assets as $asset ) {
			if ( $asset instanceof ScriptAsset ) {
				$asset_rows[ $asset->handle() ] = $asset->to_array();
			}
		}

		$sizes     = $this->sizes( $source_contents, $bundles, $graph );
		$signature = $this->signature( $assets, $components, $context );
		$required  = $has_cycle ? $enqueued : $graph->closure( array_values( array_unique( array_merge( $enqueued, $component ) ) ) );
		$registered_unused = array_values(
			array_diff(
				array_keys( $asset_rows ),
				$required
			)
		);

		return new JavaScriptManifest(
			array(
				'manifest_version' => 1,
				'signature'        => $signature,
				'generated_at'     => gmdate( 'c' ),
				'page_type'       => sanitize_key( (string) ( $context['page_type'] ?? 'unknown' ) ) ?: 'unknown',
				'assets'           => $asset_rows,
				'required'         => $required,
				'unused_candidates' => $registered_unused,
				'file_count'       => count( $asset_rows ),
				'bundles'          => $bundles,
				'retained'         => $retained,
				'deferred'         => $strategies['deferred'],
				'delayed'          => $strategies['delayed'],
				'warnings'         => array_values( array_unique( $warnings ) ),
				'original_size'    => $sizes['original'],
				'optimized_size'   => $sizes['optimized'],
				'measured_files'   => $sizes['measured_files'],
				'invalidation'     => $this->invalidation_metadata( $context, $components ),
			)
		);
	}

	private function component_handles( array $components, DependencyGraph $graph ): array {
		$handles = array();

		foreach ( $components as $component ) {
			if ( ! $component instanceof ComponentDefinition ) {
				continue;
			}

			foreach ( $component->to_array()['javascript_dependencies'] as $dependency ) {
				if ( null !== $graph->asset( $dependency ) ) {
					$handles[] = $dependency;
				}
			}
		}

		return array_values( array_unique( $handles ) );
	}

	private function bundle( string $layer, string $type, array $handles, DependencyGraph $graph ): array {
		$external = array();

		foreach ( $handles as $handle ) {
			$asset = $graph->asset( $handle );
			if ( null === $asset ) {
				continue;
			}

			foreach ( $asset->dependencies() as $dependency ) {
				if ( ! in_array( $dependency, $handles, true ) ) {
					$external[] = $dependency;
				}
			}
		}

		return array(
			'layer'                 => $layer,
			'type'                  => $type,
			'handles'               => $handles,
			'external_dependencies' => array_values( array_unique( $external ) ),
		);
	}

	private function enqueued_handles( array $assets ): array {
		$handles = array();
		foreach ( $assets as $asset ) {
			if ( $asset instanceof ScriptAsset && $asset->to_array()['enqueued'] ) {
				$handles[] = $asset->handle();
			}
		}

		return $handles;
	}

	private function strategies(
		DependencyGraph $graph,
		array $enqueued,
		array $context,
		array $options,
		array &$warnings
	): array {
		$defer_requested = $this->requested( $options, 'defer_handles' );
		$delay_requested = $this->requested( $options, 'delay_handles' );
		$deferred        = array();
		$delayed         = array();

		foreach ( $defer_requested as $handle ) {
			$asset = $graph->asset( $handle );
			if ( null !== $asset && $this->safety->evaluate( $asset, $context )['defer'] ) {
				$deferred[] = $handle;
			} else {
				$warnings[] = 'Defer retained original execution for: ' . $handle;
			}
		}

		foreach ( $delay_requested as $handle ) {
			$asset = $graph->asset( $handle );
			if (
				null !== $asset
				&& $this->safety->evaluate( $asset, $context )['delay']
				&& ! $this->has_non_delayed_dependent(
					$handle,
					$enqueued,
					$delay_requested,
					$graph,
					$context
				)
			) {
				$delayed[] = $handle;
			} else {
				$warnings[] = 'Delay retained original execution for: ' . $handle;
			}
		}

		return array( 'deferred' => $deferred, 'delayed' => $delayed );
	}

	private function has_non_delayed_dependent(
		string $handle,
		array $enqueued,
		array $delay_requested,
		DependencyGraph $graph,
		array $context
	): bool {
		foreach ( $enqueued as $candidate ) {
			$asset = $graph->asset( $candidate );
			if (
				null !== $asset
				&& $candidate !== $handle
				&& in_array( $handle, $graph->closure( array( $candidate ) ), true )
				&& (
					! in_array( $candidate, $delay_requested, true )
					|| ! $this->safety->evaluate( $asset, $context )['delay']
				)
			) {
				return true;
			}
		}

		return false;
	}

	private function requested( array $options, string $key ): array {
		return isset( $options[ $key ] ) && is_array( $options[ $key ] )
			? array_values( array_unique( array_filter( array_map( 'sanitize_key', $options[ $key ] ) ) ) )
			: array();
	}

	private function sizes( array $contents, array $bundles, DependencyGraph $graph ): array {
		$original  = 0;
		$optimized = 0;
		$bundled   = array();

		foreach ( $contents as $handle => $content ) {
			if ( is_string( $content ) ) {
				$original += strlen( $content );
			}
		}
		foreach ( $bundles as $bundle ) {
			foreach ( $bundle['handles'] as $handle ) {
				if ( isset( $contents[ $handle ] ) && null !== $graph->asset( $handle ) ) {
					$optimized += strlen( $this->minifier->minify( (string) $contents[ $handle ] ) );
					$bundled[ $handle ] = true;
				}
			}
		}
		foreach ( $contents as $handle => $content ) {
			if ( is_string( $content ) && ! isset( $bundled[ $handle ] ) ) {
				$optimized += strlen( $content );
			}
		}

		return array(
			'original'       => $original,
			'optimized'      => $optimized,
			'measured_files' => count( array_filter( $contents, 'is_string' ) ),
		);
	}

	private function signature( array $assets, array $components, array $context ): string {
		$source_hashes = isset( $context['source_hashes'] ) && is_array( $context['source_hashes'] )
			? $context['source_hashes']
			: array();
		$asset_hashes = array();
		foreach ( $assets as $asset ) {
			if ( $asset instanceof ScriptAsset ) {
				$asset_hashes[ $asset->handle() ] = $asset->fingerprint(
					(string) ( $source_hashes[ $asset->handle() ] ?? '' )
				);
			}
		}
		ksort( $asset_hashes, SORT_STRING );
		$component_hashes = array();
		foreach ( $components as $component ) {
			if ( $component instanceof ComponentDefinition ) {
				$component_hashes[ $component->id() ] = $component->signature();
			}
		}
		ksort( $component_hashes, SORT_STRING );

		return hash(
			'sha256',
			(string) wp_json_encode(
				array(
					'assets'     => $asset_hashes,
					'components' => $component_hashes,
					'versions'   => $context['versions'] ?? array(),
					'settings'   => sanitize_key( (string) ( $context['settings_hash'] ?? '' ) ),
				)
			)
		);
	}

	private function invalidation_metadata( array $context, array $components ): array {
		return array(
			'source_hashes'        => array_keys( $context['source_hashes'] ?? array() ),
			'versions'             => array_keys( $context['versions'] ?? array() ),
			'settings_hash'         => sanitize_key( (string) ( $context['settings_hash'] ?? '' ) ),
			'component_signatures' => array_values(
				array_map(
					static function ( $component ): string {
						return $component instanceof ComponentDefinition ? $component->signature() : '';
					},
					$components
				)
			),
		);
	}

	private function diagnostic_warnings( array $diagnostics ): array {
		$warnings = array();
		foreach ( $diagnostics['missing'] as $handle => $missing ) {
			$warnings[] = 'Missing dependencies for ' . $handle . ': ' . implode( ', ', $missing );
		}
		foreach ( $diagnostics['cycles'] as $cycle ) {
			$warnings[] = $cycle;
		}

		return $warnings;
	}
}
