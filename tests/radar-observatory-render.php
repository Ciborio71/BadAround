<?php
define('ABSPATH',__DIR__);
function add_filter(...$a){} function wp_enqueue_script(...$a){}
function get_stylesheet_directory(){return dirname(__DIR__).'/wordpress/themes/badaround-child';}
function get_stylesheet_directory_uri(){return '/wordpress/themes/badaround-child';}
function get_header(){echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{margin:0;font-family:Arial}.ast-container{max-width:1200px;margin:auto;padding:0 30px}#wpadminbar{height:32px;background:#222;color:white}.ba-site-header{height:88px;background:#062537;color:white;display:flex;align-items:center;padding:0 24px;box-sizing:border-box}</style><body class="page-template-page-radar-demo"><div id="wpadminbar">WordPress · Amministrazione</div><header class="ba-site-header">BadAround · Radar</header><div id="content"><div class="ast-container"><div id="primary">';}
function get_footer(){echo '</div></div></div><footer class="ba-site-footer">BadAround</footer></body></html>';}
function home_url($s){return $s;} function rest_url($s){return '/wp-json/'.$s;}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}function esc_url_raw($s){return $s;}
function get_option(...$a){return array();}function get_terms(...$a){return array();}
function is_wp_error($s){return false;}function wp_json_encode($s){return json_encode($s);}
require get_stylesheet_directory().'/inc/pins.php';
ob_start();include get_stylesheet_directory().'/page-radar-demo.php';$html=ob_get_clean();
if(substr_count($html,'<h1>')!==1)throw new RuntimeException('One H1');
if(str_contains($html,'RadarHeatOverlay'))throw new RuntimeException('No heat layer');
if(strpos($html,'// RADAR-PUBLIC-06')!==false)throw new RuntimeException('PHP leaked');
if(strpos($html,'ba-radar-admin-diagnostics')<strpos($html,'</main>'))throw new RuntimeException('Diagnostics outside workspace');
file_put_contents(dirname(__DIR__).'/radar-fixture.html',$html);
echo "RADAR_RENDER_PASS\n";
