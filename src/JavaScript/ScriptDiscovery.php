<?php
/**
 * WordPress script registry discovery.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

final class ScriptDiscovery {
	public function discover( $scripts = null, array $script_modules = array() ): array {
		if ( null === $scripts ) {
			global $wp_scripts;
			$scripts = $wp_scripts;
		}

		$registered = is_object( $scripts ) && isset( $scripts->registered ) && is_array( $scripts->registered )
			? $scripts->registered
			: array();
		$queue  = is_object( $scripts ) && isset( $scripts->queue ) && is_array( $scripts->queue )
			? $scripts->queue
			: array();
		$assets = array();

		foreach ( $registered as $handle => $registration ) {
			if ( ! is_object( $registration ) ) {
				continue;
			}

			$extra    = isset( $registration->extra ) && is_array( $registration->extra ) ? $registration->extra : array();
			$before   = $this->list_value( $extra['before'] ?? array() );
			$after    = $this->list_value( $extra['after'] ?? array() );
			$localized = isset( $extra['data'] )
				&& is_scalar( $extra['data'] )
				&& '' !== trim( (string) $extra['data'] );
			if ( $localized ) {
				$before[] = (string) $extra['data'];
			}

			$assets[] = new ScriptAsset(
				array(
					'handle'        => $handle,
					'src'           => $registration->src ?? '',
					'dependencies'  => $registration->deps ?? array(),
					'version'       => $registration->ver ?? '',
					'group'         => $extra['group'] ?? 0,
					'strategy'      => $extra['strategy'] ?? '',
					'module'        => 'module' === ( $extra['type'] ?? '' ),
					'inline_before' => $before,
					'inline_after'  => $after,
					'localized'     => $localized,
					'translations'  => ! empty( $registration->textdomain ),
					'enqueued'      => in_array( $handle, $queue, true ),
				)
			);
		}

		foreach ( $script_modules as $handle => $module ) {
			if ( ! is_array( $module ) ) {
				continue;
			}

			$assets[] = new ScriptAsset(
				array(
					'handle'       => $handle,
					'src'          => $module['src'] ?? '',
					'dependencies' => $this->module_dependencies( $module['dependencies'] ?? array() ),
					'version'      => $module['version'] ?? '',
					'module'       => true,
					'enqueued'     => true === ( $module['enqueued'] ?? false ),
				)
			);
		}

		return $assets;
	}

	private function list_value( $value ): array {
		if ( is_string( $value ) ) {
			return array( $value );
		}

		return is_array( $value ) ? array_values( array_filter( $value, 'is_string' ) ) : array();
	}

	private function module_dependencies( $dependencies ): array {
		$dependencies = is_array( $dependencies ) ? $dependencies : array();
		$result       = array();

		foreach ( $dependencies as $dependency ) {
			if ( is_string( $dependency ) ) {
				$result[] = $dependency;
			} elseif ( is_array( $dependency ) && isset( $dependency['id'] ) ) {
				$result[] = (string) $dependency['id'];
			}
		}

		return $result;
	}
}
