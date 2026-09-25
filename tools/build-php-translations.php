<?php
/**
 * Compile PO catalogs into WordPress text-based PHP translation catalogs.
 *
 * @package NakhostinPerformanceEngine
 */

declare(strict_types=1);

$root     = dirname( __DIR__ );
$catalogs = glob( $root . '/languages/*.po' ) ?: array();

foreach ( $catalogs as $catalog ) {
	$parsed = npe_parse_po_catalog( (string) file_get_contents( $catalog ) );
	$target = substr( $catalog, 0, -3 ) . '.l10n.php';
	$output = "<?php\n/** Generated text translation catalog. @package NakhostinPerformanceEngine */\n\nreturn "
		. var_export( $parsed, true ) . ";\n";

	if ( false === file_put_contents( $target, $output, LOCK_EX ) ) {
		fwrite( STDERR, sprintf( "Unable to write %s.\n", basename( $target ) ) );
		exit( 1 );
	}

	echo sprintf( "Built %s\n", substr( $target, strlen( $root ) + 1 ) );
}

/**
 * Parse the PO subset supported by WordPress catalogs, including contexts,
 * multiline values, and plural translations.
 *
 * @return array<string, mixed>
 */
function npe_parse_po_catalog( string $source ): array {
	$entries = array();
	foreach ( preg_split( '/\R[ \t]*\R/u', trim( $source ) ) ?: array() as $block ) {
		$entry = array();
		$field = '';
		foreach ( preg_split( '/\R/u', $block ) ?: array() as $line ) {
			if ( '#' === substr( ltrim( $line ), 0, 1 ) ) {
				continue;
			}
			if ( preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[(\d+)\])?)\s+"(.*)"$/', $line, $match ) ) {
				$field = $match[1];
				$entry[ $field ] = npe_po_string( $match[3] );
				continue;
			}
			if ( '' !== $field && preg_match( '/^"(.*)"$/', $line, $match ) ) {
				$entry[ $field ] .= npe_po_string( $match[1] );
			}
		}
		if ( array_key_exists( 'msgid', $entry ) ) {
			$entries[] = $entry;
		}
	}

	$headers  = array();
	$messages = array();
	foreach ( $entries as $item ) {
		$msgid = (string) ( $item['msgid'] ?? '' );
		if ( '' === $msgid ) {
			foreach ( explode( "\n", (string) ( $item['msgstr'] ?? '' ) ) as $header ) {
				if ( false === strpos( $header, ':' ) ) {
					continue;
				}
				list( $name, $value ) = array_map( 'trim', explode( ':', $header, 2 ) );
				$headers[ strtolower( $name ) ] = $value;
			}
			continue;
		}

		$key = isset( $item['msgctxt'] ) ? $item['msgctxt'] . "\x04" . $msgid : $msgid;
		if ( isset( $item['msgid_plural'] ) ) {
			$translations = array();
			for ( $index = 0; array_key_exists( 'msgstr[' . $index . ']', $item ); $index++ ) {
				$translations[] = $item[ 'msgstr[' . $index . ']' ];
			}
			if ( $translations ) {
				$messages[ $key ] = $translations;
			}
		} elseif ( isset( $item['msgstr'] ) && '' !== $item['msgstr'] ) {
			$messages[ $key ] = $item['msgstr'];
		}
	}

	$headers['messages'] = $messages;
	return $headers;
}

function npe_po_string( string $value ): string {
	$decoded = json_decode( '"' . $value . '"', true );
	return is_string( $decoded ) ? $decoded : stripcslashes( $value );
}
