<?php
/** Optional home preview. All hooks are scoped to its template. */
if (!defined('ABSPATH')) exit;
function badaround_home_two_active(){return is_page_template('page-home-2.php');}
add_filter('body_class',static function($classes){if(badaround_home_two_active())$classes[]='ba-home-two-page';return $classes;});
add_filter('wp_robots',static function($robots){if(badaround_home_two_active()&&!is_front_page()){$robots['noindex']=true;$robots['nofollow']=true;unset($robots['index']);}return $robots;});
add_filter('pre_get_document_title',static function($title){return badaround_home_two_active()?(is_front_page()?'BadAround | Segnalazioni e informazioni dalla tua zona':'Anteprima nuova home | BadAround'):$title;},99);
add_action('wp_enqueue_scripts',static function(){
 if(!badaround_home_two_active())return;
 $uri=get_stylesheet_directory_uri();$dir=dirname(__DIR__);
 wp_enqueue_style('badaround-blog',$uri.'/assets/css/blog.css',['badaround-components'],(string)filemtime($dir.'/assets/css/blog.css'));
 wp_enqueue_style('badaround-home-two',$uri.'/assets/css/home-two.css',['badaround-shell','badaround-blog'],(string)filemtime($dir.'/assets/css/home-two.css'));
 wp_enqueue_script('badaround-home-two',$uri.'/assets/js/home-two.js',[],(string)filemtime($dir.'/assets/js/home-two.js'),true);
 wp_script_add_data('badaround-home-two','strategy','defer');
 $settings=get_option('wpforms_settings',[]);$key=is_array($settings)?trim((string)($settings['geolocation-google-places-api-key']??'')):'';
 wp_add_inline_script('badaround-home-two','window.BadAroundHomeTwo='.wp_json_encode(['mapUrl'=>home_url('/mappa/'),'mapsUrl'=>$key?add_query_arg(['key'=>$key,'loading'=>'async','v'=>'weekly','libraries'=>'places'],'https://maps.googleapis.com/maps/api/js'):'']).';','before');
},30);

// A static front page otherwise selects front-page.php ahead of its assigned template.
add_filter('template_include',static function($template){
 if(is_front_page()&&badaround_home_two_active())return dirname(__DIR__).'/page-home-2.php';
 return $template;
},99);
