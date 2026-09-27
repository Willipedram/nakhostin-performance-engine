<?php
/**
 * PHPUnit bootstrap.
 *
 * @package NakhostinPerformanceEngine
 */

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! is_readable( $autoload ) ) {
	fwrite( STDERR, "Composer dependencies are required. Run composer install.\n" );
	exit( 1 );
}

require_once $autoload;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

require_once __DIR__ . '/Fixtures/wordpress-stubs.php';
