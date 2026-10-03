<?php
/** Browser-cache wrapper for context-aware BFCache evidence. @package NakhostinPerformanceEngine */
namespace Nakhostin\PerformanceEngine\Diagnostics;
final class BrowserCacheHealthCheck { /** @var BFCacheAnalyzer */ private $analyzer; public function __construct( ?BFCacheAnalyzer $analyzer = null ) { $this->analyzer = $analyzer ?? new BFCacheAnalyzer(); } public function run( array $headers, string $context ): array { return $this->analyzer->analyze( $headers, $context ); } }
