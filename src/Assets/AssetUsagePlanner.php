<?php
/** Builds page-scoped, fail-open decisions from capture evidence. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Assets;

final class AssetUsagePlanner {
	public function plan( AssetRegistry $registry, array $evidence, bool $safe_mode = true ): array {
		$assets = $registry->all(); $graph = new AssetDependencyGraph( $assets );
		$required = $this->handles( array_merge( (array) ( $evidence['required'] ?? array() ), (array) ( $evidence['enqueued'] ?? array() ) ) );
		$component = $this->handles( $evidence['component_required'] ?? array() );
		$observed = $this->handles( array_merge( (array) ( $evidence['observed'] ?? array() ), (array) ( $evidence['rendered'] ?? array() ) ) );
		$protected = $this->handles( $evidence['protected'] ?? array() );
		$absent = $this->handles( $evidence['explicitly_absent'] ?? array() );
		$candidates = $this->handles( array_merge( (array) ( $evidence['candidates'] ?? array() ), $required, $component, $observed, $protected, $absent ) );
		$retained_roots = $this->handles( array_merge( $required, $component, $observed, $protected ) );
		$dependencies = $graph->dependencies( $retained_roots );
		$page_scope = $this->handles( array_merge( $candidates, $dependencies ) );
		$complete = true === ( $evidence['capture_complete'] ?? false );
		$dependency_complete = ! array_key_exists( 'dependency_complete', $evidence ) || true === $evidence['dependency_complete'];
		$decisions = array();

		foreach ( $page_scope as $handle ) {
			if ( ! isset( $assets[ $handle ] ) ) { continue; }
			if ( in_array( $handle, $component, true ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'required-by-component', 100 ); }
			elseif ( in_array( $handle, $protected, true ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'protected-by-safety-policy', 100 ); }
			elseif ( in_array( $handle, $observed, true ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'observed-on-rendered-page', 100 ); }
			elseif ( in_array( $handle, $required, true ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'required-by-page', 100 ); }
			elseif ( in_array( $handle, $dependencies, true ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'required-by-dependency', 100 ); }
			elseif ( in_array( $handle, $absent, true ) && ! $complete ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'analysis-incomplete-falls-back-to-keep', 0 ); }
			elseif ( in_array( $handle, $absent, true ) && ( ! $dependency_complete || ! $graph->is_complete_for( array( $handle ) ) || $graph->has_cycle( array( $handle ) ) || ! $graph->can_unload( $handle, $retained_roots ) ) ) { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'dependency-state-uncertain', 0 ); }
			elseif ( in_array( $handle, $absent, true ) ) { $decisions[ $handle ] = $this->decision( $handle, $safe_mode ? AssetDecision::KEEP : AssetDecision::UNLOAD, 'explicitly-absent-from-complete-capture', 100 ); }
			else { $decisions[ $handle ] = $this->decision( $handle, AssetDecision::KEEP, 'unknown-state-falls-back-to-keep', 0 ); }
		}
		return $decisions;
	}

	private function handles( $handles ): array { return array_values( array_unique( array_filter( array_map( 'sanitize_key', is_array( $handles ) ? $handles : array() ) ) ) ); }
	private function decision( string $handle, string $decision, string $reason, int $confidence ): array { return ( new AssetDecision( $handle, $decision, $reason, $confidence ) )->to_array(); }
}
