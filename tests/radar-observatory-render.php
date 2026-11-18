<?php
define('ABSPATH',__DIR__);
function add_filter(...$a){} function wp_enqueue_script(...$a){}
function get_stylesheet_directory(){return dirname(__DIR__).'/wordpress/themes/badaround-child';}
function get_stylesheet_directory_uri(){return '/wordpress/themes/badaround-child';}
function get_header(){echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Arial}</style><body class="page-template-page-radar-demo">';}
function get_footer(){echo '</body></html>';}
function home_url($s){return $s;} function rest_url($s){return '/wp-json/'.$s;}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}function esc_url_raw($s){return $s;}
function get_option(...$a){return array();}function get_terms(...$a){return array();}
function is_wp_error($s){return false;}function wp_json_encode($s){return json_encode($s);}
require get_stylesheet_directory().'/inc/pins.php';
ob_start();include get_stylesheet_directory().'/page-radar-demo.php';$html=ob_get_clean();
if(substr_count($html,'<h1>')!==1)throw new RuntimeException('One H1');
if(str_contains($html,'RadarHeatOverlay'))throw new RuntimeException('No heat layer');
file_put_contents(dirname(__DIR__).'/radar-fixture.html',$html);
echo "RADAR_RENDER_PASS\n";
