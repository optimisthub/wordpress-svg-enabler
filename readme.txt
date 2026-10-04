=== SVG Enabler ===
Contributors: optimisthub, fatih-toprak
Tags: svg, svg upload, allow svg upload, svg support, svg sanitizer
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safely upload SVG files to WordPress. Every SVG is sanitized to remove scripts, event handlers and remote references before it is stored.

== Description ==

SVG Enabler lets you upload SVG files to the WordPress media library while making sure each file is safe.

SVG is an XML format, which means it can carry JavaScript, event handler attributes, external references and XML entities. Uploading an unsanitized SVG is equivalent to allowing arbitrary HTML and JavaScript uploads. This plugin sanitizes every SVG during upload, before the file is written to your uploads directory.

**What gets removed**

* `<script>` elements and inline JavaScript
* Event handler attributes such as `onload`, `onclick`, `onerror`
* `javascript:` URLs in links and attributes
* `<foreignObject>` payloads
* Remote references in `<image>`, `<use>`, `href` and `xlink:href`
* Remote `url()` and `@import` rules inside `<style>` blocks
* XML entities and DTD declarations (XXE)
* CSS `expression()` and `behavior:` declarations

**Features**

* SVG and SVGZ upload support
* Sanitizes on upload, before the file reaches the uploads folder
* Uses the actively maintained `enshrined/svg-sanitize` library (0.22.0+)
* Correct image dimensions in the media library, editor and srcset
* Works with the block editor, classic editor and featured images
* Filterable allow-lists and capability requirements
* No settings screen required, works out of the box

== Installation ==

### INSTALL "SVG Enabler" FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select 'Add New';
2. Search for 'SVG Enabler';
3. Activate SVG Enabler from your Plugins page;
4. You are done. Upload an SVG from the media library.

### INSTALL "SVG Enabler" MANUALLY

1. Upload the 'svg-enabler' folder to the /wp-content/plugins/ directory;
2. Activate the SVG Enabler through the 'Plugins' menu in WordPress;
3. You are done.

== Frequently Asked Questions ==

= Who can upload SVG files? =

By default only administrators (users with the `manage_options` capability) can upload SVG files. Everyone else is blocked, because SVG is the one image format that can execute code.

You can change this with the `optimisthub_svg_enabler_capability` filter. For example, to let editors upload SVG as well:

`
add_filter( 'optimisthub_svg_enabler_capability', function ( $capability ) {
    return 'edit_others_posts';
} );
`

Return an empty string to allow anyone who can upload files.

= Can we change the allowed attributes and tags? =

Yes, this can be done using the `optimisthub_svg_enabler_allowed_attributes` and `optimisthub_svg_enabler_allowed_tags` filters. They take one argument that must be returned.

`
    add_filter( 'optimisthub_svg_enabler_allowed_attributes', function ( $attributes )
    {
        $attributes[] = 'target'; // This would allow the target="" attribute.
        return $attributes;

    } );
`

`
    add_filter( 'optimisthub_svg_enabler_allowed_tags', function ( $tags )
    {
        $tags[] = 'use'; // This would allow the <use> element.

        return $tags;
    } );
`

= Why was my SVG rejected? =

The file either was not valid XML, did not contain an `<svg>` root element, or could not be sanitized. Rejected files are never stored. Try re-saving the file with a vector editor such as Inkscape or Illustrator.

= Does this plugin make SVG safe to use? =

It removes the known dangerous constructs from uploaded SVG files and keeps the sanitizer library up to date. No sanitizer can be considered a complete guarantee, which is why upload permission is restricted to administrators by default. Only upload SVG files from sources you trust.

= Does it work with SVGZ? =

Yes, `.svgz` files use the same MIME type and are sanitized the same way.

== Changelog ==

= 2.0.0 =

**Security**

* Fixed a sanitizer bypass: remote references in `href`, `xlink:href`, `src` and similar attributes are now removed. Previously an uploaded SVG could contact an external server.
* Fixed a sanitizer bypass: `@import`, remote `url()` references, `expression()` and `behavior:` inside `<style>` blocks are now removed.
* Upgraded `enshrined/svg-sanitize` from 0.15.4 to 0.22.0. The old version was affected by CVE-2025-55166, CVE-2022-23638 and CVE-2019-10772.
* SVG uploads now require the `manage_options` capability by default, instead of relying on `unfiltered_upload`, which WordPress only grants on multisite.

**Fixed**

* Fixed a fatal error on every SVG upload: `svgFileValidator()` called `$this->sanitize()`, a method that did not exist.
* Fixed a fatal error caused by registering `svgFileValidator()` for `wp_check_filetype_and_ext` with four accepted arguments while the callback only accepted one.
* Removed a recursive call to `wp_check_filetype_and_ext()` from inside its own filter.
* Fixed undefined `$width` and `$height` variables in `getImageTagOverride()` when `$size` was not an array.
* Fixed the `optimisthub_svg_enabler_allowed_attributes` and `_allowed_tags` filters being applied but their return values discarded, which made both filters do nothing.
* Fixed SVG images rendering as 1x1 pixels in the editor and on the front end. Real width and height are now derived from the SVG's `width`/`height` or `viewBox`.
* Fixed srcset generating broken entries for SVG attachments.
* Added a media library preview and correct attachment dimensions.

**Changed**

* Rewritten as a namespaced, PSR-4 autoloaded plugin (`OptimistHub\SvgEnabler`).
* Sanitization now runs before the file is moved into the uploads directory.
* Added `composer.json` so dependencies are reproducible; the previous `composer.json` was excluded by `.gitignore`.
* Requires WordPress 6.0 and PHP 7.4 or newer.
* The plugin now refuses to enable SVG uploads when PHP lacks the `dom` and `libxml` extensions, and shows an admin notice explaining why.

= 1.0.3 =

* Author name issue.

= 1.0.2 - 1.0.1 =

* Github Action

= 1.0.0 =

* Stable version released

== Upgrade Notice ==

= 2.0.0 =

Security release. Fixes two sanitizer bypasses and an upstream library affected by CVE-2025-55166, and fixes a fatal error that broke SVG uploads. Upgrading is strongly recommended.
