<?php
/** Unified evidence-based health areas; intentionally no synthetic overall score. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
final class SiteHealthScorecard {
	public function build( array $facts ): array { $areas = array(); foreach ( array( 'server', 'page_cache', 'object_cache', 'backend', 'database', 'css', 'javascript', 'fonts', 'dom', 'security_headers', 'compatibility' ) as $area ) { $value = $facts[ $area ] ?? array(); $areas[ $area ] = array( 'status' => (string) ( $value['status'] ?? 'unavailable' ), 'finding' => (string) ( $value['finding'] ?? 'No evidence collected.' ), 'evidence' => $value['evidence'] ?? array(), 'impact' => (string) ( $value['impact'] ?? 'Unknown' ), 'recommended_action' => (string) ( $value['recommended_action'] ?? 'Collect diagnostics.' ), 'npe_can_fix' => (bool) ( $value['npe_can_fix'] ?? false ) ); } return array( 'methodology' => 'Categorical evidence-based statuses; no synthetic performance score.', 'areas' => $areas ); }
}
