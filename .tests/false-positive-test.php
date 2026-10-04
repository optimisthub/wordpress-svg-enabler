<?php
define('ABSPATH','/tmp/'); $base=dirname(__DIR__);
function apply_filters($t,$v){ return $v; }
require $base.'/vendor/autoload.php';
$s = str_replace(["defined( 'ABSPATH' ) || exit;",'declare( strict_types = 1 );'],'',file_get_contents($base.'/src/Sanitizer.php'));
eval('?>'.$s);
$S = new \OptimistHub\SvgEnabler\Sanitizer();
$legit = [
  'gradient+internal url' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs><rect width="10" height="10" fill="url(#g)"/></svg>',
  'plain css color'       => '<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:red;stroke:#00ff00}</style><rect class="a" width="4" height="4"/></svg>',
  'keyframes'             => '<svg xmlns="http://www.w3.org/2000/svg"><style>@keyframes spin{to{transform:rotate(360deg)}}.s{animation:spin 1s}</style><rect class="s"/></svg>',
  'embedded base64 img'   => '<svg xmlns="http://www.w3.org/2000/svg"><style>.b{background:url(data:image/png;base64,iVBORw0KGgo=)}</style><rect class="b"/></svg>',
  'text with tspan'       => '<svg xmlns="http://www.w3.org/2000/svg"><text x="1" y="2"><tspan>Hello</tspan></text></svg>',
  'path fill-rule'        => '<svg xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M0 0L5 5Z"/></svg>',
];
$fail=0;
foreach($legit as $n=>$svg){
  $out=$S->sanitize_string($svg);
  $ok = ($out!==false);
  if(!$ok){$fail++;}
  printf("%-24s %s\n", $n, $ok?'ACCEPTED':'REJECTED (false positive)');
}
echo "\n=== FALSE POSITIVES: $fail ===\n";
