<?php
define('ABSPATH','/tmp/');
$base = dirname(__DIR__);
function apply_filters($t,$v){ return $v; }
require $base.'/vendor/autoload.php';
$s = str_replace(["defined( 'ABSPATH' ) || exit;",'declare( strict_types = 1 );'],'',file_get_contents($base.'/src/Sanitizer.php'));
eval('?>'.$s);
$S = new \OptimistHub\SvgEnabler\Sanitizer();
$cases = [
 'style_import' => '<svg xmlns="http://www.w3.org/2000/svg"><style>@import url("http://evil.example/x.css");</style><rect/></svg>',
 'style_script' => '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:url(javascript:alert(1))}</style><rect/></svg>',
 'style_plain'  => '<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:red}</style><rect class="a"/></svg>',
];
foreach($cases as $n=>$p){ echo "### $n\n"; var_dump($S->sanitize_string($p)); echo "\n"; }
