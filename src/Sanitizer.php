<?php
/**
 * SVG sanitization wrapper.
 *
 * @package OptimistHub\SvgEnabler
 */

declare( strict_types = 1 );

namespace OptimistHub\SvgEnabler;

defined( 'ABSPATH' ) || exit;

use enshrined\svgSanitize\Sanitizer as SvgSanitize;
use enshrined\svgSanitize\data\AllowedAttributes;
use enshrined\svgSanitize\data\AllowedTags;

/**
 * Sanitizes SVG markup using enshrined/svg-sanitize.
 *
 * The library strips <script>, event handler attributes, external references
 * and other XSS vectors. We additionally force remote-reference removal and
 * allow site owners to extend or restrict the allowed tag/attribute sets.
 */
final class Sanitizer {

	/**
	 * Cached sanitizer instance.
	 *
	 * @var SvgSanitize|null
	 */
	private ?SvgSanitize $sanitizer = null;

	/**
	 * Build (or reuse) the configured sanitizer.
	 *
	 * @return SvgSanitize
	 */
	private function sanitizer(): SvgSanitize {
		if ( null !== $this->sanitizer ) {
			return $this->sanitizer;
		}

		$sanitizer = new SvgSanitize();

		/*
		 * Remote references let an SVG fetch external resources (tracking,
		 * SSRF, or loading a remote DTD). Always strip them.
		 */
		$sanitizer->removeRemoteReferences( true );

		$sanitizer->setAllowedTags(
			new class() implements \enshrined\svgSanitize\data\TagInterface {
				/**
				 * Return the allowed SVG tags.
				 *
				 * @return array<int, string>
				 */
				public static function getTags() {
					$tags = array_map( 'strtolower', AllowedTags::getTags() );

					/**
					 * Filter the list of allowed SVG tags.
					 *
					 * @param array<int, string> $tags Lowercase tag names.
					 */
					$tags = (array) apply_filters( 'optimisthub_svg_enabler_allowed_tags', $tags );

					return array_values( array_unique( array_map( 'strtolower', array_map( 'strval', $tags ) ) ) );
				}
			}
		);

		$sanitizer->setAllowedAttrs(
			new class() implements \enshrined\svgSanitize\data\AttributeInterface {
				/**
				 * Return the allowed SVG attributes.
				 *
				 * @return array<int, string>
				 */
				public static function getAttributes() {
					$attributes = array_map( 'strtolower', AllowedAttributes::getAttributes() );

					/**
					 * Filter the list of allowed SVG attributes.
					 *
					 * @param array<int, string> $attributes Lowercase attribute names.
					 */
					$attributes = (array) apply_filters( 'optimisthub_svg_enabler_allowed_attributes', $attributes );

					return array_values( array_unique( array_map( 'strtolower', array_map( 'strval', $attributes ) ) ) );
				}
			}
		);

		$this->sanitizer = $sanitizer;

		return $this->sanitizer;
	}

	/**
	 * Sanitize SVG markup held in a string.
	 *
	 * @param string $dirty Raw SVG markup.
	 * @return string|false Cleaned markup, or false when it cannot be sanitized.
	 */
	public function sanitize_string( string $dirty ) {
		if ( '' === trim( $dirty ) ) {
			return false;
		}

		$previous_errors = libxml_use_internal_errors( true );

		try {
			$clean = $this->sanitizer()->sanitize( $dirty );
		} catch ( \Throwable $e ) {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_errors );

			return false;
		}

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( ! is_string( $clean ) || '' === trim( $clean ) ) {
			return false;
		}

		/*
		 * The sanitizer can return an empty <svg> shell for malformed input.
		 * Treat a document with no drawable/meaningful content as a failure so
		 * we never store a silently useless file.
		 */
		if ( ! $this->has_svg_root( $clean ) ) {
			return false;
		}

		/*
		 * The upstream library does not inspect the contents of <style> blocks,
		 * so CSS-borne vectors (@import exfiltration, javascript: URLs,
		 * expression()) survive it. Harden the CSS ourselves.
		 */
		$clean = $this->harden_css( $clean );

		/*
		 * Upstream leaves remote href/xlink:href values on elements such as
		 * <image>, which lets an SVG phone home (tracking, IP disclosure, or
		 * pulling an arbitrary remote resource through the site). Strip them.
		 */
		$clean = $this->strip_remote_references( $clean );

		return $clean;
	}

	/**
	 * Remove remote URLs from element attributes.
	 *
	 * Keeps fragment references (url(#gradient)) and relative paths, which are
	 * legitimate and local, but removes anything with a scheme or protocol
	 * relative prefix so the SVG cannot fetch external resources.
	 *
	 * @param string $markup Sanitized SVG markup.
	 * @return string
	 */
	private function strip_remote_references( string $markup ): string {
		$attributes = array( 'href', 'xlink:href', 'src', 'poster', 'data', 'action', 'formaction', 'srcset', 'style' );

		foreach ( $attributes as $attribute ) {
			$pattern = '/(\s' . preg_quote( $attribute, '/' ) . '\s*=\s*)("([^"]*)"|\'([^\']*)\')/i';

			$markup = (string) preg_replace_callback(
				$pattern,
				static function ( array $matches ) use ( $attribute ): string {
					$quote = '"' === substr( $matches[2], 0, 1 ) ? '"' : "'";
					$value = isset( $matches[3] ) && '' !== $matches[3] ? $matches[3] : ( $matches[4] ?? '' );

					if ( 'style' === strtolower( $attribute ) ) {
						$value = (string) preg_replace( '/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', 'none', $value );

						return $matches[1] . $quote . $value . $quote;
					}

					// Fragment-only references (url(#id)) are safe and local.
					if ( '' === $value || '#' === substr( $value, 0, 1 ) ) {
						return $matches[0];
					}

					// A scheme (http:, https:, data:, file:, javascript:) or // is remote.
					if ( preg_match( '#^\s*(?:[a-z][a-z0-9+.\-]*:|//)#i', $value ) ) {
						return '';
					}

					return $matches[0];
				},
				$markup
			);
		}

		return $markup;
	}

	/**
	 * Neutralise dangerous constructs inside SVG <style> blocks.
	 *
	 * Upstream svg-sanitize treats <style> content as opaque text. This strips
	 *
	 * @import/@charset rules, javascript: and vbscript: URLs, CSS expression()
	 * and behavior: declarations, and any remote url() reference.
	 *
	 * @param string $markup Sanitized SVG markup.
	 * @return string
	 */
	private function harden_css( string $markup ): string {
		if ( false === stripos( $markup, '<style' ) ) {
			return $markup;
		}

		$result = preg_replace_callback(
			'#(<style\b[^>]*>)(.*?)(</style>)#is',
			static function ( array $matches ): string {
				$css = $matches[2];

				// Drop @import / @charset / @namespace rules entirely.
				$css = preg_replace( '/@(?:import|charset|namespace)\b[^;]*;?/i', '', $css );

				// Drop declarations whose value can execute or phone home.
				$css = preg_replace( '/\bexpression\s*\(/i', 'none(', $css );
				$css = preg_replace( '/\bbehavior\s*:/i', 'none:', $css );
				$css = preg_replace( '/\b(?:javascript|vbscript|livescript|mocha)\s*:/i', 'none:', $css );

				// Remove any url() that points at an absolute/remote resource.
				$css = preg_replace_callback(
					'/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is',
					static function ( array $url_match ): string {
						$target = trim( $url_match[2] );

						if ( preg_match( '#^(?:[a-z][a-z0-9+.\-]*:|//|\\\\)#i', $target ) ) {
							return 'none';
						}

						return $url_match[0];
					},
					(string) $css
				);

				return $matches[1] . $css . $matches[3];
			},
			$markup
		);

		return is_string( $result ) ? $result : $markup;
	}

	/**
	 * Sanitize an SVG file in place.
	 *
	 * @param string $path Absolute path to the uploaded file.
	 * @return bool True when the file was sanitized and rewritten.
	 */
	public function sanitize_file( string $path ): bool {
		if ( ! is_readable( $path ) || ! is_writable( $path ) ) {
			return false;
		}

		$dirty = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local upload temp file.

		if ( false === $dirty || '' === $dirty ) {
			return false;
		}

		$clean = $this->sanitize_string( $dirty );

		if ( false === $clean ) {
			return false;
		}

		$written = file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Rewriting the local upload temp file in place.

		return false !== $written;
	}

	/**
	 * Check that sanitized markup still contains an <svg> root element.
	 *
	 * @param string $markup Sanitized markup.
	 * @return bool
	 */
	private function has_svg_root( string $markup ): bool {
		return (bool) preg_match( '/<svg[\s>]/i', $markup );
	}
}
