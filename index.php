<?php
/**
 * Plugin Name:     SVG Enabler
 * Plugin URI:      https://github.com/optimisthub/wordpress-svg-enabler
 * Description:     This plugin allow upload any SVG files to WordPress. 
 * Author:          optimisthub
 * Author URI:      https://optimisthub.com
 * Text Domain:     svg-enabler
 * Version:         1.0.3
 * Requires at least: 5.0
 * Tested up to: 6.1.1
 * Requires PHP: 7.1
 * License: GPLv2
 */


if ( ! defined( 'ABSPATH' ) ) 
{
	exit;
}

require __DIR__ . '/vendor/autoload.php';

use enshrined\svgSanitize\Sanitizer as Sanitizer;
use enshrined\svgSanitize\data\AllowedAttributes as AllowedAttributes;
use enshrined\svgSanitize\data\AllowedTags as AllowedTags;

class SvgEnabler  
{
    public $sanitizer;
    public $allowedAttributes;
    public $allowedTags;

    public function __construct()
    {
        $this->sanitizer    = new Sanitizer();  

        $this->allowedAttrs = array_map('strtolower', AllowedAttributes::getAttributes());
        $this->allowedTags  = array_map('strtolower', AllowedTags::getTags());

        apply_filters( 'optimisthub_svg_enabler_allowed_attributes', $this->allowedAttrs );
        apply_filters( 'optimisthub_svg_enabler_allowed_tags', $this->allowedTags );

        add_filter( 'upload_mimes', [$this, 'addSupport'] );
        add_filter( 'wp_handle_upload_prefilter', [$this, 'uploadFilter'] );
        add_filter( 'wp_check_filetype_and_ext', [$this, 'svgFileValidator'], 10, 4);
        add_filter( 'wp_calculate_image_srcset_meta', [$this, 'disableSrcSet'], 10, 4 );
        add_action( 'get_image_tag', [$this, 'getImageTagOverride'], 10, 6 );

    }

    public function addSupport($types)
    { 
        $types['svg'] = 'image/svg+xml'; 
        return $types;
    }

    public function uploadFilter($upload)
    {
        if ($upload['type'] === 'image/svg+xml') 
        {
            if (!self::svgSanitizer($upload['tmp_name'])) 
            {
                $upload['error'] = __( "Sorry, please check your file", 'svg-enabler' );
            }
        }

        return $upload;
    }

    public function svgFileValidator($file)
    {
 
        if ( ! isset( $file['tmp_name'] ) ) {
            return $file;
        }

        $fileName       = isset( $file['name'] ) ? $file['name'] : '';
        $_wpFileType    = wp_check_filetype_and_ext( $file['tmp_name'], $fileName );
        $type           = ! empty( $_wpFileType['type'] ) ? $_wpFileType['type'] : '';

        if ( 'image/svg+xml' === $type ) {
            if ( ! $this->sanitize( $file['tmp_name'] ) ) {
                $file['error'] = __(
                    "Sorry, this file couldn't be sanitized so for security reasons wasn't uploaded",
                    'safe-svg'
                );
            }
        }

        return $file;
    }

    public function getImageTagOverride( $html, $id, $alt, $title, $align, $size ) 
    {
        $mime = get_post_mime_type( $id );

        if ( 'image/svg+xml' === $mime ) 
        {    
            if ( is_array( $size ) ) 
            {
                $width  = $size[0];
                $height = $size[1];    
            }

            if ( $height && $width ) 
            {
                $html = str_replace( 'width="1" ', sprintf( 'width="%s" ', $width ), $html );
                $html = str_replace( 'height="1" ', sprintf( 'height="%s" ', $height ), $html );
            
            } else {

                $html = str_replace( 'width="1" ', '', $html );
                $html = str_replace( 'height="1" ', '', $html );
            }

            $html = str_replace( '/>', ' role="img" />', $html );
        }

        return $html;
    }

    public function disableSrcSet($imageMeta, $sizeArray, $imageSrc, $attachmentId)
    {
        if ( $attachmentId && 'image/svg+xml' === get_post_mime_type( $attachmentId ) )
        {
            $imageMeta['sizes'] = [];
        }

        return $imageMeta;
    }


    private function svgSanitizer($file)
    {  

        $dirtyFile = file_get_contents( $file );

        if ( $dirtyFile === false ) {
            return false;
        }

        $cleanFile = $this->sanitizer->sanitize( $dirtyFile ); 
        
        if ( $cleanFile === false ) {
            return false;
        }

        file_put_contents( $file, $cleanFile );

        return true;
    }
 
}

new SvgEnabler();


