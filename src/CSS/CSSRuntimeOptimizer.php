<?php
namespace Nakhostin\PerformanceEngine\CSS;
final class CSSRuntimeOptimizer { private $applier; public function __construct( ?StylesheetStrategyApplier $applier = null ) { $this->applier = $applier ?: new StylesheetStrategyApplier(); } public function apply( array $manifest, bool $safe_mode ): array { return $this->applier->apply( (array) ( $manifest['stylesheets'] ?? array() ), $safe_mode ); } public function inline( string $css ): void { $css = str_ireplace( '</style', '', $css ); if ( '' !== trim( $css ) ) { echo '<style id="npe-critical-css">' . $css . '</style>'; } } }
