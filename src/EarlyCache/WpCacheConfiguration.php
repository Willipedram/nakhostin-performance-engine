<?php
/**
 * Enables WordPress's early drop-in loader in conventional wp-config.php files.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\EarlyCache;

final class WpCacheConfiguration {
	/** @var string|null */ private $failure;

	public function failure(): ?string {
		return $this->failure;
	}

	public function enable( string $path ): bool {
		$this->failure = 'config-write';
		if ( is_link( $path ) || ! is_file( $path ) ) {
			$this->failure = 'config-location';
			return false;
		}
		if ( ! is_writable( $path ) || ! is_writable( dirname( $path ) ) ) {
			$this->failure = 'config-permissions';
			return false;
		}

		$file = @fopen( $path, 'rb' );
		if ( false === $file ) {
			return false;
		}

		$temp = false;
		try {
			if ( ! flock( $file, LOCK_EX ) ) {
				return false;
			}
			$original = stream_get_contents( $file );
			$stat = fstat( $file );
			$updated = is_string( $original ) ? $this->enable_in_source( $original ) : null;
			if ( is_string( $original ) && null === $updated ) {
				$this->failure = 'config-format';
				return false;
			}
			if ( null === $updated || false === $stat || 1 !== $stat['nlink'] ) {
				return false;
			}
			if ( $original === $updated ) {
				$this->failure = null;
				return true;
			}

			// Keep the PHP extension while staging a file that contains credentials.
			$candidate = dirname( $path ) . '/.npe-config-' . bin2hex( random_bytes( 16 ) ) . '.php';
			$staging = @fopen( $candidate, 'xb' );
			if ( false === $staging ) {
				return false;
			}
			$temp = $candidate;
			fclose( $staging );
			if ( ! @chmod( $temp, 0600 ) || fileowner( $temp ) !== $stat['uid'] ) {
				return false;
			}
			if ( filegroup( $temp ) !== $stat['gid'] && ! @chgrp( $temp, $stat['gid'] ) ) {
				return false;
			}
			if ( @file_put_contents( $temp, $updated ) !== strlen( $updated ) || ! @chmod( $temp, $stat['mode'] & 0777 ) ) {
				return false;
			}

			clearstatcache( true, $path );
			$current = @lstat( $path );
			if ( false === $current || $current['ino'] !== $stat['ino'] || $current['dev'] !== $stat['dev'] || @file_get_contents( $path ) !== $original ) {
				return false;
			}
			if ( ! @rename( $temp, $path ) ) {
				return false;
			}
			$temp = false;
			if ( function_exists( 'opcache_invalidate' ) ) {
				@opcache_invalidate( $path, true );
			}
			$this->failure = null;
			return true;
		} catch ( \Exception $error ) {
			return false;
		} finally {
			if ( false !== $temp ) {
				@unlink( $temp );
			}
			fclose( $file );
		}
	}

	private function enable_in_source( string $source ): ?string {
		if ( 0 !== strpos( $source, '<?php' ) ) {
			return null;
		}
		$definition = null;
		$bootstrap = null;
		$references = 0;
		$offset = 0;
		$depth = 0;
		$previous = T_OPEN_TAG;
		$includes = 0;
		try {
			$tokens = token_get_all( $source, TOKEN_PARSE );
			foreach ( $tokens as $token ) {
				$text = is_array( $token ) ? $token[1] : $token;
				if ( is_array( $token ) && in_array( $token[0], array( T_STRING, T_CONSTANT_ENCAPSED_STRING ), true ) && 'WP_CACHE' === strtoupper( trim( $text, "\"'" ) ) ) {
					++$references;
				}
				$standalone = 0 === $depth && in_array( $previous, array( T_OPEN_TAG, ';', '}' ), true );
				if ( $standalone && is_array( $token ) && T_STRING === $token[0] && 0 === strcasecmp( $text, 'define' ) && preg_match( '/\Adefine\s*\(\s*([\x27\x22])WP_CACHE\1\s*,\s*(true|false)\s*\)\s*;/i', substr( $source, $offset ), $match, PREG_OFFSET_CAPTURE ) ) {
					$definition = array( $offset + $match[2][1], $match[2][0] );
				}
				if ( $standalone && is_array( $token ) && T_REQUIRE_ONCE === $token[0] && preg_match( '/\Arequire_once\s+ABSPATH\s*\.\s*([\x27\x22])wp-settings\.php\1\s*;/i', substr( $source, $offset ) ) ) {
					$bootstrap = $offset;
				} elseif ( is_array( $token ) && in_array( $token[0], array( T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE ), true ) ) {
					++$includes;
				}
				if ( ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
					$previous = is_array( $token ) ? $token[0] : $token;
				}
				if ( '{' === $token || ( is_array( $token ) && in_array( $token[0], array( T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES ), true ) ) ) {
					++$depth;
				} elseif ( '}' === $token ) {
					--$depth;
				}
				$offset += strlen( $text );
			}
			if ( null === $bootstrap || 0 !== $includes ) {
				return null;
			}
			if ( 0 === $references ) {
				$newline = false === strpos( $source, "\r\n" ) ? "\n" : "\r\n";
				$updated = substr_replace( $source, "define( 'WP_CACHE', true );" . $newline . $newline, $bootstrap, 0 );
			} elseif ( 1 === $references && null !== $definition && $definition[0] < $bootstrap ) {
				$updated = substr_replace( $source, 'true', $definition[0], strlen( $definition[1] ) );
			} else {
				return null;
			}
			token_get_all( $updated, TOKEN_PARSE );
			return $updated;
		} catch ( \ParseError $error ) {
			return null;
		}
	}
}
