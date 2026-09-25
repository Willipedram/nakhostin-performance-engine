<?php
/**
 * JavaScript Intelligence facade.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\JavaScript;

use Nakhostin\PerformanceEngine\Contracts\JavaScriptAnalyzerInterface;

final class JavaScriptAnalyzer implements JavaScriptAnalyzerInterface {
	/** @var JavaScriptPlanner */
	private $planner;

	public function __construct( JavaScriptPlanner $planner ) {
		$this->planner = $planner;
	}

	public function analyze( string $document, array $assets = array() ): array {
		$normalized = array();
		foreach ( $assets as $asset ) {
			if ( $asset instanceof ScriptAsset ) {
				$normalized[] = $asset;
			} elseif ( is_array( $asset ) ) {
				$normalized[] = new ScriptAsset( $asset );
			}
		}

		return $this->planner->plan( $normalized )->to_array();
	}

	public function supports( string $asset_type ): bool {
		return in_array( strtolower( $asset_type ), array( 'js', 'javascript', 'module' ), true );
	}

	public function discover_dependencies( string $script ): array {
		$dependencies = array();
		if ( preg_match_all( '/(?:import\s+(?:[^;]+?\s+from\s+)?|import\s*\()(["\'])([^"\']+)\1/', $script, $matches ) ) {
			$dependencies = $matches[2];
		}

		return array_values( array_unique( array_map( 'sanitize_text_field', $dependencies ) ) );
	}

	public function analyze_assets(
		array $assets,
		array $components = array(),
		array $context = array(),
		array $options = array(),
		array $source_contents = array()
	): JavaScriptManifest {
		return $this->planner->plan( $assets, $components, $context, $options, $source_contents );
	}
}
