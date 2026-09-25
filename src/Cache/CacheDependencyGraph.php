<?php
/**
 * Bounded, persistent dependency graph for targeted cache invalidation.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\Cache;

final class CacheDependencyGraph {
	public const OPTION = 'npe_cache_dependency_graph';
	private const MAX_NODES = 2000;
	private const MAX_EDGES = 50;

	public function register_node( string $node, array $metadata = array() ): bool {
		return $this->merge( array( $node => $metadata ), array() );
	}

	public function connect( string $source, string $dependent ): bool {
		$source    = $this->normalize_node( $source );
		$dependent = $this->normalize_node( $dependent );
		if ( '' === $source || '' === $dependent || $source === $dependent ) {
			return false;
		}
		return $this->merge( array( $source => array(), $dependent => array() ), array( array( $source, $dependent ) ) );
	}

	public function merge( array $nodes, array $edges ): bool {
		if ( ! $this->acquire_lock() ) {
			return false;
		}
		try {
			$graph = $this->all();
			foreach ( $nodes as $node => $metadata ) {
				$node = $this->normalize_node( $node );
				if ( '' === $node || ( ! isset( $graph[ $node ] ) && count( $graph ) >= self::MAX_NODES ) ) {
					continue;
				}
				$metadata        = is_array( $metadata ) ? $metadata : array();
				$current         = $graph[ $node ] ?? array();
				$graph[ $node ] = array(
					'dependents' => array_values( array_unique( array_slice( (array) ( $current['dependents'] ?? array() ), 0, self::MAX_EDGES ) ) ),
					'tags'       => $this->normalize_tags( array_merge( (array) ( $current['tags'] ?? array() ), (array) ( $metadata['tags'] ?? array() ) ) ),
					'urls'       => $this->normalize_urls( array_merge( (array) ( $current['urls'] ?? array() ), (array) ( $metadata['urls'] ?? array() ) ) ),
					'warm_urls'  => $this->normalize_urls( array_merge( (array) ( $current['warm_urls'] ?? array() ), (array) ( $metadata['warm_urls'] ?? array() ) ) ),
					'updated_at' => time(),
				);
			}
			foreach ( $edges as $edge ) {
				$source = $this->normalize_node( $edge[0] ?? '' );
				$target = $this->normalize_node( $edge[1] ?? '' );
				if ( '' === $source || '' === $target || $source === $target || ! isset( $graph[ $source ], $graph[ $target ] ) ) {
					continue;
				}
				$dependents = (array) $graph[ $source ]['dependents'];
				if ( ! in_array( $target, $dependents, true ) && count( $dependents ) < self::MAX_EDGES ) {
					$dependents[] = $target;
					sort( $dependents, SORT_STRING );
					$graph[ $source ]['dependents'] = $dependents;
				}
			}

			return update_option( self::OPTION, $graph, false ) || $graph === $this->all();
		} finally {
			delete_option( self::OPTION . '_lock' );
		}
	}

	public function resolve( array $roots, int $limit = 500 ): array {
		$graph   = $this->all();
		$queue   = array_values( array_unique( array_filter( array_map( array( $this, 'normalize_node' ), $roots ) ) ) );
		$visited = array();
		$tags    = array();
		$urls    = array();
		$warm    = array();
		$limit   = max( 1, min( self::MAX_NODES, $limit ) );

		while ( $queue && count( $visited ) < $limit ) {
			$node = array_shift( $queue );
			if ( isset( $visited[ $node ] ) ) {
				continue;
			}
			$visited[ $node ] = true;
			$metadata         = $graph[ $node ] ?? array();
			$tags             = array_merge( $tags, (array) ( $metadata['tags'] ?? array() ) );
			$urls             = array_merge( $urls, (array) ( $metadata['urls'] ?? array() ) );
			$warm             = array_merge( $warm, (array) ( $metadata['warm_urls'] ?? array() ) );
			foreach ( (array) ( $metadata['dependents'] ?? array() ) as $dependent ) {
				if ( ! isset( $visited[ $dependent ] ) ) {
					$queue[] = $dependent;
				}
			}
		}

		return array(
			'nodes'     => array_keys( $visited ),
			'tags'      => array_values( array_unique( $tags ) ),
			'urls'      => array_values( array_unique( $urls ) ),
			'warm_urls' => array_values( array_unique( $warm ) ),
			'truncated' => ! empty( $queue ),
		);
	}

	public function all(): array {
		$graph = get_option( self::OPTION, array() );
		return is_array( $graph ) ? $graph : array();
	}

	public function summary(): array {
		$graph = $this->all();
		$edges = 0;
		foreach ( $graph as $node ) {
			$edges += count( (array) ( $node['dependents'] ?? array() ) );
		}
		return array( 'nodes' => count( $graph ), 'edges' => $edges );
	}

	private function normalize_node( $node ): string {
		$node = strtolower( trim( (string) $node ) );
		return preg_match( '/^[a-z][a-z0-9_-]*(?::[a-z0-9_.-]+){0,3}$/', $node ) ? $node : '';
	}

	private function normalize_tags( array $tags ): array {
		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $tags ) ) ) );
	}

	private function normalize_urls( array $urls ): array {
		$normalized = array();
		foreach ( $urls as $url ) {
			$url = esc_url_raw( (string) $url );
			if ( '' !== $url ) {
				$normalized[] = $url;
			}
		}
		return array_slice( array_values( array_unique( $normalized ) ), 0, 100 );
	}

	private function acquire_lock(): bool {
		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			if ( add_option( self::OPTION . '_lock', time(), '', false ) ) {
				return true;
			}
			$created = (int) get_option( self::OPTION . '_lock', 0 );
			if ( $created && $created < time() - 10 ) {
				delete_option( self::OPTION . '_lock' );
				continue;
			}
			usleep( 5000 );
		}
		return false;
	}
}
