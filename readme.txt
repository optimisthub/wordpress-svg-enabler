=== SVG Enabler ===
Contributors: fatih-toprak, optimisthub
Tags: svg upload, allow svg upload, svg support, svg upload enabler
Requires at least: 5.0
Tested up to: 6.1.1
Requires PHP: 7.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin gives you the ability to allow SVG uploads whilst making sure that they’re sanitized to stop SVG/XML vulnerabilities affecting your site.

== Description ==

This plugin gives you the ability to allow SVG uploads whilst making sure that they’re sanitized to stop SVG/XML vulnerabilities affecting your site.
 
== Installation ==

### INSTALL "SVG Enabler" FROM WITHIN WORDPRESS

1. Visit the plugins page within your dashboard and select ‘Add New’;
1. Search for ‘SVG Enabler’;
1. Activate SVG Enabler from your Plugins page;
1. Go to ‘after activation’ below.

### INSTALL "SVG Enabler" MANUALLY

1. Upload the ‘svg-enabler’ folder to the /wp-content/plugins/ directory;
1. Activate the SVG Enabler through the ‘Plugins’ menu in WordPress;
1. Go to ‘after activation’ below.
 
### AFTER ACTIVATION

1. SVG Enabler is a 'set and forget' plugin. There are no settings fields as your site's scheduled posts will be automatically checked when the plugin is installed and activated.
1. You’re done!


== Frequently Asked Questions ==

= Can we change the allowed attributes and tags? =

Yes, this can be done using the `svg_allowed_attributes` and `svg_allowed_tags` filters. They take one argument that must be returned. 

`
    add_filter( 'optimisthub_svg_enabler_allowed_attributes', function ( $attributes ) 
    {
        $attributes[] = 'target'; // This would allow the target="" attribute.
        return $attributes;

    } );


    add_filter( 'optimisthub_svg_enabler_allowed_tags', function ( $tags ) 
    {
 
        $tags[] = 'use'; // This would allow the <use> element.

        return $tags;
    } );
`

== Changelog == 

= 1.0.3 =

* Author name issue.

= 1.0.2 - 1.0.1 =

* Github Action

= 1.0.0 =

* Stable version released