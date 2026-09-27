<?php
/**
 * Public-signal-only Elementor component adapter.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Integrations\Elementor;

use Nakhostin\PerformanceEngine\Components\ComponentAdapterInterface;
use Nakhostin\PerformanceEngine\Components\ComponentDefinition;

final class ElementorComponentAdapter implements ComponentAdapterInterface {
	/** @var bool|null */
	private $availability;

	/** @var bool */
	private $enabled;

	public function __construct( ?bool $availability = null, bool $enabled = true ) {
		$this->availability = $availability;
		$this->enabled      = $enabled;
	}

	public function get_id(): string {
		return 'elementor';
	}

	public function is_available(): bool {
		return null !== $this->availability
			? $this->availability
			: defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' );
	}

	public function is_enabled(): bool {
		return $this->enabled && $this->is_available();
	}

	public function register(): void {
		// Detection uses manifest evidence and public availability signals only.
	}

	public function inspect( array $manifest ): array {
		if ( ! $this->is_enabled() ) {
			return array( 'page_types' => array(), 'components' => array(), 'metadata' => array() );
		}

		$body_classes = $manifest['body_classes'] ?? array();
		$is_page      = in_array( 'elementor-page', $body_classes, true );
		$is_template  = $this->contains_prefix( $body_classes, 'elementor-template-' );
		$styles       = array_values(
			array_filter(
				$manifest['stylesheets'] ?? array(),
				static function ( $stylesheet ): bool {
					return false !== stripos( (string) $stylesheet, '/elementor/' );
				}
			)
		);
		$components = array();

		foreach ( $manifest['elementor_widgets'] ?? array() as $widget ) {
			$components[] = new ComponentDefinition(
				array(
					'id'                => 'ELEMENTOR_WIDGET_' . strtoupper( sanitize_key( $widget ) ),
					/* translators: %s: Elementor widget type. */
					'name'              => sprintf(
						__( 'Elementor Widget: %s', 'nakhostin-performance-engine' ),
						sanitize_text_field( $widget )
					),
					'selectors'         => array( '.elementor-widget-' . sanitize_key( $widget ) ),
					'css_dependencies'  => empty( $styles ) ? array() : array( 'elementor-generated-styles' ),
					'integration_owner' => 'elementor',
				)
			);
		}

		return array(
			'page_types' => $is_page ? array( 'elementor-page' ) : array(),
			'components' => $components,
			'metadata'   => array(
				'is_page'          => $is_page,
				'is_template'      => $is_template,
				'generated_styles' => $styles,
			),
		);
	}

	private function contains_prefix( array $values, string $prefix ): bool {
		foreach ( $values as $value ) {
			if ( 0 === strpos( (string) $value, $prefix ) ) {
				return true;
			}
		}

		return false;
	}
}
