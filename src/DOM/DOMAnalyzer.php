<?php
/**
 * Safe, explicitly invoked DOM usage analyzer.
 *
 * @package NakhostinPerformanceEngine
 */

namespace Nakhostin\PerformanceEngine\DOM;

use DOMDocument;
use DOMElement;
use DOMNodeList;
use DOMXPath;
use Nakhostin\PerformanceEngine\Contracts\DOMAnalyzerInterface;
use RuntimeException;

final class DOMAnalyzer implements DOMAnalyzerInterface {
	/** @var DOMComponentDetector */
	private $components;

	/** @var DOMStateRegistry */
	private $states;

	/** @var DOMSignature */
	private $signature;

	public function __construct(
		DOMComponentDetector $components,
		DOMStateRegistry $states,
		DOMSignature $signature
	) {
		$this->components = $components;
		$this->states     = $states;
		$this->signature  = $signature;
	}

	public function analyze( string $html, array $context = array() ): array {
		return $this->create_manifest( new DOMSnapshot( $html, $context ) )->to_array();
	}

	public function create_manifest( DOMSnapshot $snapshot ): DOMManifest {
		$document = $this->parse( $snapshot->html() );
		$xpath    = new DOMXPath( $document );
		$usage    = $this->extract_usage( $xpath );
		$detected = $this->components->detect( $xpath );
		$context  = $snapshot->context();

		$page_type           = $this->page_type( $usage['body_classes'], $context );
		$template_identifier = $this->template_identifier( $usage['body_classes'], $context );
		$integrations        = $this->integrations( $usage, $detected );
		$states              = $this->states->detect( $usage['classes'], $usage['state_values'] );
		$signature_input     = array_merge(
			$usage,
			$detected,
			array(
				'page_type'          => $page_type,
				'template_identifier' => $template_identifier,
				'integrations'        => $integrations,
			)
		);

		$data = array(
			'manifest_version'    => 1,
			'page_type'          => $page_type,
			'template_identifier' => $template_identifier,
			'dom_signature'       => $this->signature->generate( $signature_input ),
			'elements'            => $usage['elements'],
			'classes'             => $usage['classes'],
			'ids'                 => $usage['ids'],
			'attributes'          => $usage['attributes'],
			'data_attributes'     => $usage['data_attributes'],
			'body_classes'        => $usage['body_classes'],
			'forms'               => $usage['forms'],
			'buttons'             => $usage['buttons'],
			'links'               => $usage['links'],
			'scripts'             => $usage['scripts'],
			'stylesheets'         => $usage['stylesheets'],
			'components'          => $detected['components'],
			'elementor_widgets'   => $detected['elementor_widgets'],
			'integrations'        => $integrations,
			'detected_states'     => $states,
			'supported_states'    => $this->states->supported(),
			'source_url'          => $this->privacy_safe_url( (string) ( $context['source_url'] ?? '' ) ),
			'generated_at'        => gmdate( 'c' ),
			'versions'            => $this->versions( $context ),
		);

		return new DOMManifest( $data );
	}

	private function parse( string $html ): DOMDocument {
		if ( ! class_exists( DOMDocument::class ) ) {
			throw new RuntimeException( 'The PHP DOM extension is required for DOM analysis.' );
		}

		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );

		if ( '' !== trim( $html ) ) {
			$document->loadHTML(
				'<?xml encoding="utf-8" ?>' . $html,
				LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT
			);
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $document;
	}

	private function extract_usage( DOMXPath $xpath ): array {
		$elements        = array();
		$classes         = array();
		$ids             = array();
		$attributes      = array();
		$data_attributes = array();
		$state_values    = array();
		$body_classes    = array();
		$scripts         = array();
		$stylesheets     = array();
		$nodes           = $xpath->query( '//*' );

		if ( $nodes instanceof DOMNodeList ) {
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof DOMElement ) {
					continue;
				}

				$elements[] = strtolower( $node->tagName );
				$node_classes = $this->tokens( $node->getAttribute( 'class' ) );
				$classes       = array_merge( $classes, $node_classes );

				if ( 'body' === strtolower( $node->tagName ) ) {
					$body_classes = $node_classes;
				}

				if ( $node->hasAttribute( 'id' ) ) {
					$id = $this->safe_identifier( $node->getAttribute( 'id' ) );
					if ( '' !== $id ) {
						$ids[] = $id;
					}
				}

				foreach ( $node->attributes as $attribute ) {
					$name         = strtolower( $attribute->nodeName );
					$attributes[] = $name;

					if ( 0 === strpos( $name, 'data-' ) ) {
						$data_attributes[] = $name;
					}

					if ( in_array( $name, array( 'class', 'aria-expanded', 'aria-selected', 'aria-current', 'data-state' ), true ) ) {
						$state_values[] = sanitize_text_field( $attribute->nodeValue );
					}
				}

				if ( 'script' === strtolower( $node->tagName ) ) {
					$scripts[] = array(
						'src'  => $this->privacy_safe_url( $node->getAttribute( 'src' ) ),
						'type' => sanitize_key( $node->getAttribute( 'type' ) ),
					);
				}

				if ( 'link' === strtolower( $node->tagName ) && false !== stripos( $node->getAttribute( 'rel' ), 'stylesheet' ) ) {
					$stylesheets[] = $this->privacy_safe_url( $node->getAttribute( 'href' ) );
				}
			}
		}

		return array(
			'elements'        => $this->unique( $elements ),
			'classes'         => $this->unique_safe_identifiers( $classes ),
			'ids'             => $this->unique_safe_identifiers( $ids ),
			'attributes'      => $this->unique( $attributes ),
			'data_attributes' => $this->unique( $data_attributes ),
			'forms'           => $this->count( $xpath, '//form' ),
			'buttons'         => $this->count( $xpath, '//button | //input[@type="button" or @type="submit" or @type="reset"]' ),
			'links'           => $this->count( $xpath, '//a[@href]' ),
			'scripts'         => $scripts,
			'stylesheets'     => array_values( array_filter( $this->unique( $stylesheets ) ) ),
			'body_classes'    => $this->unique_safe_identifiers( $body_classes ),
			'state_values'    => $state_values,
		);
	}

	private function page_type( array $body_classes, array $context ): string {
		if ( isset( $context['page_type'] ) ) {
			return sanitize_key( (string) $context['page_type'] ) ?: 'unknown';
		}

		$types = array(
			'single-product' => 'product',
			'post-type-archive-product' => 'product-archive',
			'woocommerce-cart' => 'cart',
			'woocommerce-checkout' => 'checkout',
			'home' => 'home',
			'blog' => 'blog',
			'single' => 'single',
			'page' => 'page',
			'archive' => 'archive',
			'search' => 'search',
			'404' => '404',
		);

		foreach ( $types as $class_name => $type ) {
			if ( in_array( $class_name, $body_classes, true ) ) {
				return $type;
			}
		}

		return 'unknown';
	}

	private function template_identifier( array $body_classes, array $context ): string {
		if ( isset( $context['template_identifier'] ) ) {
			return $this->safe_identifier( (string) $context['template_identifier'] ) ?: 'unknown';
		}

		foreach ( $body_classes as $class_name ) {
			if ( 0 === strpos( $class_name, 'page-template-' ) || 0 === strpos( $class_name, 'theme-' ) ) {
				return $class_name;
			}
		}

		return 'unknown';
	}

	private function integrations( array $usage, array $detected ): array {
		$haystack     = implode( ' ', array_merge( $usage['classes'], $usage['attributes'] ) );
		$integrations = array();

		$has_woocommerce = false !== stripos( $haystack, 'woocommerce' )
			|| in_array( 'woocommerce-components', $detected['components'], true );
		foreach ( $usage['classes'] as $class_name ) {
			$has_woocommerce = $has_woocommerce || 0 === strpos( $class_name, 'wc-' );
		}
		if ( $has_woocommerce ) {
			$integrations[] = 'woocommerce';
		}

		if ( false !== stripos( $haystack, 'elementor' ) || ! empty( $detected['elementor_widgets'] ) ) {
			$integrations[] = 'elementor';
		}

		return $integrations;
	}

	private function versions( array $context ): array {
		$versions = isset( $context['versions'] ) && is_array( $context['versions'] ) ? $context['versions'] : array();
		$allowed  = array( 'wordpress', 'theme', 'woocommerce', 'elementor', 'npe' );
		$result   = array();

		foreach ( $allowed as $name ) {
			if ( isset( $versions[ $name ] ) && is_scalar( $versions[ $name ] ) ) {
				$result[ $name ] = sanitize_text_field( (string) $versions[ $name ] );
			}
		}

		return $result;
	}

	private function privacy_safe_url( string $url ): string {
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts || ! is_array( $parts ) ) {
			return '';
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( '' !== $scheme && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}

		$host = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path = isset( $parts['path'] ) ? $this->privacy_safe_path( $parts['path'] ) : '';

		return ( '' !== $host ? ( '' !== $scheme ? $scheme . ':' : '' ) . '//' . $host : '' ) . $path;
	}

	private function privacy_safe_path( string $path ): string {
		$segments = explode( '/', $path );

		foreach ( $segments as &$segment ) {
			$decoded = rawurldecode( $segment );
			if (
				false !== strpos( $decoded, '@' )
				|| strlen( $decoded ) > 80
				|| 1 === preg_match( '/^[0-9]{7,}$|^[a-f0-9-]{16,}$/i', $decoded )
			) {
				$segment = '{dynamic}';
			}
		}
		unset( $segment );

		return '/' . ltrim( implode( '/', $segments ), '/' );
	}

	private function safe_identifier( string $value ): string {
		$value = trim( $value );

		if (
			'' === $value
			|| strlen( $value ) > 128
			|| false !== strpos( $value, '@' )
			|| 1 === preg_match( '/(?:pass|token|secret|auth|session|nonce|customer|email)/i', $value )
			|| 1 === preg_match( '/^[a-f0-9]{16,}$/i', $value )
			|| 1 === preg_match( '/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/i', $value )
			|| 1 === preg_match( '/^[0-9]{7,}$/', $value )
		) {
			return '';
		}

		return preg_replace( '/[^a-zA-Z0-9_-]/', '', $value ) ?: '';
	}

	private function tokens( string $value ): array {
		return array_values( array_filter( preg_split( '/\s+/', trim( $value ) ) ?: array() ) );
	}

	private function unique_safe_identifiers( array $values ): array {
		return $this->unique( array_filter( array_map( array( $this, 'safe_identifier' ), $values ) ) );
	}

	private function unique( array $values ): array {
		$values = array_values( array_unique( array_map( 'strval', $values ) ) );
		sort( $values, SORT_STRING );

		return $values;
	}

	private function count( DOMXPath $xpath, string $query ): int {
		$nodes = $xpath->query( $query );

		return $nodes instanceof DOMNodeList ? $nodes->length : 0;
	}
}
