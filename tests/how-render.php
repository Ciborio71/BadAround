<?php
define('ABSPATH',__DIR__);
function wp_enqueue_style(...$a){}
function get_stylesheet_directory_uri(){return '/wordpress/themes/badaround-child';}
function get_header(){echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/wordpress/themes/badaround-child/assets/css/how-v2.css"><style>body{margin:0;font-family:Arial,sans-serif}</style><body>';}
function get_footer(){echo '</body></html>';}
function home_url($s){return $s;}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES);}
function add_query_arg($k,$v,$url){return $url.'?'.$k.'='.rawurlencode($v);}
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/pins.php';
ob_start();include dirname(__DIR__).'/wordpress/themes/badaround-child/page-come-funziona.php';$html=ob_get_clean();
if(substr_count($html,'class="how-step"')!==5)throw new RuntimeException('Five steps');
if(substr_count($html,'class="how-category"')!==6)throw new RuntimeException('Six categories');
if(substr_count($html,'<h1>')!==1)throw new RuntimeException('One H1');
if(!str_contains($html,'Dopo l’approvazione')||!str_contains($html,'Punto esatto o area approssimata'))throw new RuntimeException('Publication and precision copy');
file_put_contents(dirname(__DIR__).'/how-fixture.html',$html);
echo "HOW_RENDER_PASS\n";
