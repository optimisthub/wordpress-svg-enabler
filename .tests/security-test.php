<?php
/**
 * Standalone security test for the SVG Enabler sanitizer.
 *
 * Exercises the Sanitizer class against known SVG XSS/XXE vectors without
 * booting WordPress. Run: php .tests/security-test.php
 */

define( 'ABSPATH', '/tmp/' );

$base = dirname( __DIR__ );

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) { // phpcs:ignore
		return $value;
	}
}

require $base . '/vendor/autoload.php';

/*
 * Load the Sanitizer class with its WordPress guards stripped so it can run
 * outside a full WordPress bootstrap.
 */
$source = file_get_contents( $base . '/src/Sanitizer.php' ); // phpcs:ignore

$source = str_replace(
	array(
		"defined( 'ABSPATH' ) || exit;",
		'declare( strict_types = 1 );',
		'declare(strict_types=1);',
	),
	'',
	$source
);

eval( '?>' . $source ); // phpcs:ignore

$sanitizer = new \OptimistHub\SvgEnabler\Sanitizer();

$payloads = array(
	'plain'        => '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"><rect width="100" height="50"/></svg>',
	'script_tag'   => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
	'onload'       => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>',
	'onclick'      => '<svg xmlns="http://www.w3.org/2000/svg"><rect onclick="alert(1)"/></svg>',
	'js_href'      => '<svg xmlns="http://www.w3.org/2000/svg"><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>',
	'foreign_obj'  => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject></svg>',
	'remote_ref'   => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/x.png"/></svg>',
	'xxe'          => '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
	'style_import' => '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url("http://evil.example/x.css");</style><rect/></svg>',
	'not_svg'      => '<?php echo "pwned"; ?>',
	'empty'        => '',
	'viewbox_only' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>',
);

$needles = array( '<script', 'onload=', 'onclick=', 'javascript:', '<foreignobject', '<!entity', '@import', '<?php' );

$failures = 0;

foreach ( $payloads as $name => $payload ) {
	$output = $sanitizer->sanitize_string( $payload );
	$leaked = array();

	if ( false !== $output ) {
		foreach ( $needles as $needle ) {
			if ( false !== stripos( $output, $needle ) ) {
				$leaked[] = $needle;
			}
		}
	}

	if ( false === $output ) {
		$status = 'reject';
	} elseif ( empty( $leaked ) ) {
		$status = 'CLEAN';
	} else {
		$status = 'LEAK!!';
		++$failures;
	}

	printf(
		"%-14s %-7s %s\n",
		$name,
		$status,
		'LEAK!!' === $status ? 'LEAKED: ' . implode( ',', $leaked ) : substr( str_replace( "\n", ' ', (string) $output ), 0, 80 )
	);
}

printf( "\n=== SECURITY FAILURES: %d ===\n", $failures );

exit( $failures > 0 ? 1 : 0 );
