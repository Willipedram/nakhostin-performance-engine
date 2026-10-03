<?php
namespace Nakhostin\PerformanceEngine\JavaScript;
final class JavaScriptInteractionLoader { public function register( array $handles ): void { if ( ! empty( $handles ) ) { do_action( 'npe/javascript/experimental_delay_ready', array_values( $handles ) ); } } }
