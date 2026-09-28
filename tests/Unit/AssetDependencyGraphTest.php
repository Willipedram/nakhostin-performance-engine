<?php
namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\Assets\AssetDependencyGraph;
use PHPUnit\Framework\TestCase;

final class AssetDependencyGraphTest extends TestCase {
	public function test_direct_and_transitive_dependencies_are_closed(): void {
		$graph = new AssetDependencyGraph( $this->assets( array( 'a' => array( 'b' ), 'b' => array( 'c' ), 'c' => array() ) ) );
		self::assertSame( array( 'b', 'c' ), $graph->dependencies( array( 'a' ) ) );
		self::assertFalse( $graph->can_unload( 'c', array( 'a' ) ) );
	}

	public function test_diamond_is_deduplicated_deterministically(): void {
		$graph = new AssetDependencyGraph( $this->assets( array( 'a' => array( 'b', 'c' ), 'b' => array( 'd' ), 'c' => array( 'd' ), 'd' => array() ) ) );
		self::assertSame( array( 'b', 'd', 'c' ), $graph->dependencies( array( 'a' ) ) );
		self::assertSame( array( 'b', 'c', 'a' ), $graph->transitive_dependents( 'd' ) );
	}

	public function test_cycles_terminate_and_are_reported(): void {
		$graph = new AssetDependencyGraph( $this->assets( array( 'a' => array( 'b' ), 'b' => array( 'a' ) ) ) );
		self::assertTrue( $graph->has_cycle( array( 'a' ) ) );
		self::assertSame( array( 'b', 'a' ), $graph->dependencies( array( 'a' ) ) );
	}

	public function test_missing_dependency_nodes_make_state_incomplete(): void {
		$graph = new AssetDependencyGraph( $this->assets( array( 'a' => array( 'missing' ) ) ) );
		self::assertFalse( $graph->is_complete_for( array( 'a' ) ) );
	}

	private function assets( array $dependencies ): array { $assets = array(); foreach ( $dependencies as $handle => $deps ) { $assets[ $handle ] = array( 'handle' => $handle, 'dependencies' => $deps ); } return $assets; }
}
