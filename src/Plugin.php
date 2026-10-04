<?php
/**
 * Main plugin class.
 *
 * @package OptimistHub\SvgEnabler
 */

declare( strict_types = 1 );

namespace OptimistHub\SvgEnabler;

defined( 'ABSPATH' ) || exit;

/**
 * Wires SVG support into WordPress.
 */
final class Plugin {

	/**
	 * MIME type handled by this plugin.
	 */
	public const MIME = 'image/svg+xml';

	/**
	 * Sanitizer instance.
	 *
	 * @var Sanitizer
	 */
	private Sanitizer $sanitizer;

	/**
	 * Set up dependencies.
	 */
	public function __construct() {
		$this->sanitizer = new Sanitizer();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function boot(): void {
		add_filter( 'upload_mimes', array( $this, 'allow_svg_mime' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'validate_upload' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'prefilter_upload' ) );

		/*
		 * Display handling. SVG has no raster dimensions, so srcset and the
		 * attachment metadata dimensions are meaningless and must be neutralised.
		 */
		add_filter( 'wp_get_attachment_image_src', array( $this, 'fix_attachment_image_src' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset_meta', array( $this, 'disable_srcset' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'disable_srcset_urls' ), 10, 5 );

		add_filter( 'get_image_tag', array( $this, 'fix_image_tag' ), 10, 6 );

		/* Allow SVG in the media library list and as a featured image. */
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 10, 3 );
		add_filter( 'file_is_displayable_image', array( $this, 'is_displayable_image' ), 10, 2 );
	}

	/**
	 * Register the SVG MIME type.
	 *
	 * @param array<string, string> $mimes Allowed MIME types.
	 * @return array<string, string>
	 */
	public function allow_svg_mime( $mimes ) {
		if ( ! is_array( $mimes ) ) {
			$mimes = array();
		}

		/*
		 * Only users who can upload files at all, and who pass the dedicated
		 * capability check, are offered the SVG type.
		 */
		if ( ! $this->current_user_can_upload_svg() ) {
			return $mimes;
		}

		$mimes['svg']  = self::MIME;
		$mimes['svgz'] = self::MIME;

		return $mimes;
	}

	/**
	 * Correct the extension/type verdict for SVG uploads.
	 *
	 * WordPress does not know the SVG type, so it reports a mismatch for
	 * otherwise-valid files. We confirm the type here and leave the security
	 * decision to wp_handle_upload_prefilter, which can sanitize the file.
	 *
	 * @param array<string, mixed> $data     File data: ext, type, proper_filename.
	 * @param string               $file     Full path to the file.
	 * @param string               $filename The name of the file.
	 * @param array<string, mixed> $mimes    Allowed MIME types keyed by extension.
	 * @return array<string, mixed>
	 */
	public function validate_upload( $data, $file, $filename, $mimes ) {
		unset( $mimes );

		if ( ! is_array( $data ) ) {
			return $data;
		}

		// Never recurse into wp_check_filetype_and_ext() from this filter.
		if ( ! $this->filename_has_svg_extension( (string) $filename ) ) {
			return $data;
		}

		if ( ! $this->current_user_can_upload_svg() ) {
			return $data;
		}

		/*
		 * Trust the extension only for SVG, and only when the file actually
		 * begins with SVG-ish markup. The real gate is sanitization in
		 * prefilter_upload(); this just stops core from rejecting the file
		 * before we ever get to sanitize it.
		 */
		if ( ! is_string( $file ) || ! is_readable( $file ) ) {
			return $data;
		}

		if ( ! $this->looks_like_svg( $file ) ) {
			return $data;
		}

		$data['ext']  = 'svg';
		$data['type'] = self::MIME;

		return $data;
	}

	/**
	 * Sanitize an SVG before WordPress moves it into the uploads directory.
	 *
	 * This is the security gate: the file is rewritten in place while it is
	 * still a temporary upload, so only cleaned markup is ever stored.
	 *
	 * @param array<string, mixed> $upload Upload data.
	 * @return array<string, mixed>
	 */
	public function prefilter_upload( $upload ) {
		if ( ! is_array( $upload ) ) {
			return $upload;
		}

		$name = isset( $upload['name'] ) ? (string) $upload['name'] : '';

		if ( ! $this->filename_has_svg_extension( $name ) ) {
			return $upload;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			$upload['error'] = __( 'Sorry, you are not allowed to upload SVG files.', 'svg-enabler' );

			return $upload;
		}

		if ( ! $this->current_user_can_upload_svg() ) {
			$upload['error'] = __( 'Sorry, you are not allowed to upload SVG files.', 'svg-enabler' );

			return $upload;
		}

		$tmp_name = isset( $upload['tmp_name'] ) ? (string) $upload['tmp_name'] : '';

		if ( '' === $tmp_name || ! is_readable( $tmp_name ) ) {
			$upload['error'] = __( 'Sorry, the uploaded SVG file could not be read.', 'svg-enabler' );

			return $upload;
		}

		if ( ! $this->looks_like_svg( $tmp_name ) ) {
			$upload['error'] = __( 'Sorry, this file does not appear to be a valid SVG image.', 'svg-enabler' );

			return $upload;
		}

		if ( ! $this->sanitizer->sanitize_file( $tmp_name ) ) {
			$upload['error'] = __(
				'Sorry, this file could not be sanitized, so for security reasons it was not uploaded.',
				'svg-enabler'
			);

			return $upload;
		}

		return $upload;
	}

	/**
	 * Supply dimensions for SVG attachments so the media library renders them.
	 *
	 * @param array<int, mixed>|false $image        Image src data.
	 * @param int                     $attachment_id Attachment ID.
	 * @param string|int[]            $size         Requested size.
	 * @param bool                    $icon         Whether the image is an icon.
	 * @return array<int, mixed>|false
	 */
	public function fix_attachment_image_src( $image, $attachment_id, $size, $icon ) {
		unset( $size, $icon );

		if ( ! $this->is_svg_attachment( (int) $attachment_id ) ) {
			return $image;
		}

		if ( ! is_array( $image ) ) {
			return $image;
		}

		list( $url, $width, $height ) = $this->svg_dimensions( (int) $attachment_id );

		$image[0] = $url;
		$image[1] = $width;
		$image[2] = $height;

		return $image;
	}

	/**
	 * Disable srcset generation for SVG attachments.
	 *
	 * @param array<string, mixed> $image_meta    Image metadata.
	 * @param array<int, int>      $size_array    Requested size array.
	 * @param string               $image_src     Image URL.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function disable_srcset( $image_meta, $size_array, $image_src, $attachment_id ) {
		unset( $size_array, $image_src );

		if ( is_array( $image_meta ) && $this->is_svg_attachment( (int) $attachment_id ) ) {
			$image_meta['sizes'] = array();
		}

		return $image_meta;
	}

	/**
	 * Remove any srcset sources for SVG attachments.
	 *
	 * @param array<string, mixed> $sources       Sources.
	 * @param array<int, int>      $size_array    Requested size array.
	 * @param string               $image_src     Image URL.
	 * @param array<string, mixed> $image_meta    Image metadata.
	 * @param int                  $attachment_id Attachment ID.
	 * @return array<string, mixed>
	 */
	public function disable_srcset_urls( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		unset( $size_array, $image_src, $image_meta );

		if ( $this->is_svg_attachment( (int) $attachment_id ) ) {
			return array();
		}

		return $sources;
	}

	/**
	 * Fix the markup produced for inline SVG images.
	 *
	 * Core renders SVG attachments with width="1" height="1" because the
	 * attachment has no raster dimensions. Replace those with real values.
	 *
	 * @param string       $html  Image HTML.
	 * @param int          $id    Attachment ID.
	 * @param string       $alt   Alt text.
	 * @param string       $title Title attribute.
	 * @param string       $align Alignment.
	 * @param string|int[] $size  Requested size.
	 * @return string
	 */
	public function fix_image_tag( $html, $id, $alt, $title, $align, $size ) {
		unset( $alt, $title, $align, $size );

		if ( ! $this->is_svg_attachment( (int) $id ) ) {
			return $html;
		}

		list( , $width, $height ) = $this->svg_dimensions( (int) $id );

		$html = preg_replace( '/\s(width|height)="1"/i', '', (string) $html );

		if ( $width > 0 && $height > 0 ) {
			$html = preg_replace(
				'/<img/i',
				sprintf( '<img width="%d" height="%d"', $width, $height ),
				(string) $html,
				1
			);
		}

		if ( false === strpos( (string) $html, 'role="img"' ) ) {
			$html = str_replace( '/>', ' role="img" />', (string) $html );
		}

		return (string) $html;
	}

	/**
	 * Give the media library a sane preview for SVG attachments.
	 *
	 * @param array<string, mixed> $response   Attachment response.
	 * @param \WP_Post             $attachment Attachment post.
	 * @param mixed                $meta       Attachment metadata.
	 * @return array<string, mixed>
	 */
	public function prepare_attachment_for_js( $response, $attachment, $meta ) {
		unset( $meta );

		if ( ! is_array( $response ) || ! $attachment instanceof \WP_Post ) {
			return $response;
		}

		if ( self::MIME !== get_post_mime_type( $attachment ) ) {
			return $response;
		}

		list( $url, $width, $height ) = $this->svg_dimensions( (int) $attachment->ID );

		$response['url']    = $url;
		$response['width']  = $width;
		$response['height'] = $height;

		if ( ! isset( $response['sizes'] ) || ! is_array( $response['sizes'] ) ) {
			$response['sizes'] = array();
		}

		$response['sizes']['full'] = array(
			'url'         => $url,
			'width'       => $width,
			'height'      => $height,
			'orientation' => $width >= $height ? 'landscape' : 'portrait',
		);

		return $response;
	}

	/**
	 * Let WordPress treat an SVG attachment as displayable.
	 *
	 * @param bool   $result Whether the file is displayable.
	 * @param string $path   File path.
	 * @return bool
	 */
	public function is_displayable_image( $result, $path ) {
		if ( $this->filename_has_svg_extension( (string) $path ) ) {
			return true;
		}

		return (bool) $result;
	}

	/**
	 * Read width/height for an SVG attachment.
	 *
	 * Values come from the sanitized file. When the SVG uses a viewBox without
	 * width/height we fall back to the viewBox dimensions, and finally to a
	 * neutral 0 (meaning "no intrinsic size").
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{0: string, 1: int, 2: int} URL, width, height.
	 */
	private function svg_dimensions( int $attachment_id ): array {
		$url    = (string) wp_get_attachment_url( $attachment_id );
		$width  = 0;
		$height = 0;

		$file = get_attached_file( $attachment_id );

		if ( is_string( $file ) && is_readable( $file ) ) {
			$dimensions = $this->read_dimensions_from_file( $file );

			$width  = $dimensions[0];
			$height = $dimensions[1];
		}

		if ( $width <= 0 ) {
			$width = 1000;
		}

		if ( $height <= 0 ) {
			$height = 1000;
		}

		/** This filter is documented here for extensibility. */
		return (array) apply_filters(
			'optimisthub_svg_enabler_dimensions',
			array( $url, $width, $height ),
			$attachment_id
		);
	}

	/**
	 * Parse width/height (or viewBox) from an SVG file.
	 *
	 * @param string $file Absolute file path.
	 * @return array{0: int, 1: int}
	 */
	private function read_dimensions_from_file( string $file ): array {
		$contents = file_get_contents( $file, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the sanitized local SVG header only.

		if ( false === $contents || '' === $contents ) {
			return array( 0, 0 );
		}

		if ( preg_match( '/<svg[^>]*>/i', $contents, $matches ) ) {
			$tag = $matches[0];

			$width  = $this->parse_length( $this->attr( $tag, 'width' ) );
			$height = $this->parse_length( $this->attr( $tag, 'height' ) );

			if ( $width > 0 && $height > 0 ) {
				return array( $width, $height );
			}

			$view_box = $this->attr( $tag, 'viewBox' );

			if ( '' !== $view_box ) {
				$parts = preg_split( '/[\s,]+/', trim( $view_box ) );

				if ( is_array( $parts ) && 4 === count( $parts ) ) {
					$vb_width  = (float) $parts[2];
					$vb_height = (float) $parts[3];

					$width  = $width > 0 ? $width : (int) round( $vb_width );
					$height = $height > 0 ? $height : (int) round( $vb_height );

					if ( $width > 0 && $height > 0 ) {
						return array( $width, $height );
					}
				}
			}
		}

		return array( 0, 0 );
	}

	/**
	 * Pull an attribute value out of an SVG tag string.
	 *
	 * @param string $tag  The <svg ...> tag.
	 * @param string $name Attribute name.
	 * @return string
	 */
	private function attr( string $tag, string $name ): string {
		$pattern = '/\s' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i';

		if ( preg_match( $pattern, $tag, $matches ) ) {
			return isset( $matches[2] ) && '' !== $matches[2] ? $matches[2] : ( $matches[3] ?? '' );
		}

		return '';
	}

	/**
	 * Convert an SVG length (10, 10px, 10pt) to an integer of user units.
	 *
	 * Percentages and unitless-with-units are treated conservatively as 0.
	 *
	 * @param string $value Raw attribute value.
	 * @return int
	 */
	private function parse_length( string $value ): int {
		$value = trim( $value );

		if ( '' === $value ) {
			return 0;
		}

		if ( preg_match( '/^([0-9]*\.?[0-9]+)\s*(px|pt|pc|mm|cm|in|q|em|ex)?$/i', $value, $matches ) ) {
			$number = (float) $matches[1];
			$unit   = isset( $matches[2] ) ? strtolower( $matches[2] ) : '';

			$factors = array(
				''   => 1.0,
				'px' => 1.0,
				'pt' => 96 / 72,
				'pc' => 16.0,
				'mm' => 96 / 25.4,
				'cm' => 96 / 2.54,
				'in' => 96.0,
				'q'  => 96 / 101.6,
				'em' => 16.0,
				'ex' => 8.0,
			);

			$factor = $factors[ $unit ] ?? 1.0;
			$result = (int) round( $number * $factor );

			return $result > 0 ? $result : 0;
		}

		return 0;
	}

	/**
	 * Whether an attachment is an SVG.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_svg_attachment( int $attachment_id ): bool {
		return $attachment_id > 0 && self::MIME === get_post_mime_type( $attachment_id );
	}

	/**
	 * Whether a filename carries an SVG extension.
	 *
	 * @param string $filename Filename or path.
	 * @return bool
	 */
	private function filename_has_svg_extension( string $filename ): bool {
		if ( '' === $filename ) {
			return false;
		}

		$extension = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );

		return in_array( $extension, array( 'svg', 'svgz' ), true );
	}

	/**
	 * Cheap content sniff: does the file look like SVG markup?
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	private function looks_like_svg( string $file ): bool {
		$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Sniffing a local upload temp file.

		if ( false === $handle ) {
			return false;
		}

		$head = fread( $handle, 4096 );
		fclose( $handle );

		if ( false === $head || '' === $head ) {
			return false;
		}

		// Strip a UTF-8 BOM and leading whitespace/XML prolog noise.
		$head = preg_replace( '/^\xEF\xBB\xBF/', '', $head );

		return (bool) preg_match( '/<svg[\s>]/i', (string) $head );
	}

	/**
	 * Capability check for SVG uploads.
	 *
	 * WordPress grants `unfiltered_upload` only on multisite (or when
	 * ALLOW_UNFILTERED_UPLOADS is set), so using it as the default silently
	 * blocks SVG uploads for administrators on ordinary single-site installs.
	 * We default to `manage_options` instead, which is the conventional
	 * "administrator only" capability, and allow sites to lower it.
	 *
	 * @return bool
	 */
	private function current_user_can_upload_svg(): bool {
		if ( ! current_user_can( 'upload_files' ) ) {
			return false;
		}

		/**
		 * Filter the capability required to upload SVG files.
		 *
		 * Defaults to manage_options so, out of the box, only administrators
		 * can add SVG. Lower it (for example to 'upload_files') to let editors
		 * or authors upload SVG too. Return an empty string to allow anyone
		 * who can upload files.
		 *
		 * @param string $capability Capability name.
		 */
		$capability = (string) apply_filters( 'optimisthub_svg_enabler_capability', 'manage_options' );

		if ( '' === $capability ) {
			return true;
		}

		return current_user_can( $capability );
	}
}
