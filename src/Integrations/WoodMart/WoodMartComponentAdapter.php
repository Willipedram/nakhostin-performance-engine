<?php
/**
 * Defensive, markup-only WoodMart component adapter.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\WoodMart;

use Nakhostin\PerformanceEngine\Components\ComponentAdapterInterface;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;

final class WoodMartComponentAdapter implements ComponentAdapterInterface {
	/** @var bool|null */
	private $availability;

	/** @var bool */
	private $enabled;

	public function __construct( ?bool $availability = null, bool $enabled = true ) {
		$this->availability = $availability;
		$this->enabled      = $enabled;
	}

	public function get_id(): string {
		return 'woodmart';
	}

	public function is_available(): bool {
		if ( null !== $this->availability ) {
			return $this->availability;
		}

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		return is_object( $theme )
			&& ( 'woodmart' === strtolower( (string) $theme->get_template() )
				|| 'woodmart' === strtolower( (string) $theme->get_stylesheet() ) );
	}

	public function is_enabled(): bool {
		return $this->enabled && $this->is_available();
	}

	public function register(): void {
		// Intentionally no dependency on WoodMart PHP classes or private APIs.
	}

	public function inspect( array $manifest ): array {
		if ( ! $this->is_enabled() ) {
			return array( 'page_types' => array(), 'components' => array(), 'metadata' => array() );
		}

		$classes = $manifest['classes'] ?? array();
		$found   = array_filter(
			$classes,
			static function ( $class_name ): bool {
				return 0 === strpos( (string) $class_name, 'wd-' )
					|| false !== stripos( (string) $class_name, 'woodmart' );
			}
		);

		if ( empty( $found ) ) {
			return array( 'page_types' => array(), 'components' => array(), 'metadata' => array() );
		}

		$component = new ComponentDefinition(
			array(
				'id'                        => 'WOODMART_STRUCTURE',
				'name'                      => __( 'WoodMart Structure', 'nakhostin-performance-engine' ),
				'selectors'                 => array( '[class*="wd-"]' ),
				'invalidation_dependencies' => array( 'theme' ),
				'integration_owner'         => 'woodmart',
			)
		);

		return array(
			'page_types' => array(),
			'components' => array( $component ),
			'metadata'   => array( 'detected_classes' => array_values( $found ) ),
		);
	}
}
