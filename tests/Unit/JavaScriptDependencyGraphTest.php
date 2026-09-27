<?php
/**
 * JavaScript dependency graph tests.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Tests\Unit;

use Nakhostin\PerformanceEngine\JavaScript\DependencyCycleException;
use Nakhostin\PerformanceEngine\JavaScript\DependencyGraph;
use Nakhostin\PerformanceEngine\JavaScript\ScriptAsset;
use PHPUnit\Framework\TestCase;

final class JavaScriptDependencyGraphTest extends TestCase {
	public function test_transitive_dependencies_are_ordered_before_dependents(): void {
		$graph = new DependencyGraph(
			array(
				$this->asset( 'jquery' ),
				$this->asset( 'gallery', array( 'jquery' ) ),
				$this->asset( 'product', array( 'gallery' ) ),
			)
		);

		$this->assertSame( array( 'jquery', 'gallery', 'product' ), $graph->closure( array( 'product' ) ) );
	}

	public function test_cycle_is_reported_and_never_reordered(): void {
		$graph = new DependencyGraph(
			array(
				$this->asset( 'first', array( 'second' ) ),
				$this->asset( 'second', array( 'first' ) ),
			)
		);

		$this->expectException( DependencyCycleException::class );
		$graph->closure( array( 'first' ) );
	}

	public function test_missing_dependencies_are_reported(): void {
		$graph = new DependencyGraph( array( $this->asset( 'app', array( 'missing-library' ) ) ) );

		$this->assertSame( array( 'missing-library' ), $graph->diagnostics()['missing']['app'] );
	}

	private function asset( string $handle, array $dependencies = array() ): ScriptAsset {
		return new ScriptAsset(
			array(
				'handle'       => $handle,
				'src'          => '/' . $handle . '.js',
				'dependencies' => $dependencies,
			)
		);
	}
}
