<?php
/** F1.6 real schema/config/SSR test fixture. No WordPress or builder runtime. */
define( 'ABSPATH', __DIR__ );
function add_action() {}
function home_url( $path = '' ) { return ( $GLOBALS['test_origin'] ?? 'https://staging.badaround.it' ) . $path; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function current_user_can() { return $GLOBALS['test_admin'] ?? true; }
function rest_url( $path ) { return home_url( '/wp-json/' . $path ); }
function wp_timezone_string() { return 'Europe/Rome'; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_privacy_policy_url() { return home_url( '/privacy-policy/' ); }
function get_permalink() { return home_url( '/segnala-un-evento/' ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function is_wp_error() { return false; }
class WP_Term { public $name; public $slug; public $parent; public $term_id; }
class BadAround_Event_Post_Type { const EVENT_TYPE_TAX = 'ba_tipo_evento'; }
function get_terms( $query ) {
	if ( ! empty( $GLOBALS['missing_taxonomy'] ) ) { return array(); }
	if ( 'ba_territorio' === ( $query['taxonomy'] ?? '' ) ) { return array(); } // No live territory DB in this schema/SSR fixture.
	$map = BadAround_Event_Taxonomy_Map::mapping();
	$key = $query['meta_value']; $term = new WP_Term();
	$keys = array_merge( array_keys( $map['categories'] ), array_keys( $map['subtypes'] ) );
	$term->term_id = array_search( $key, $keys, true ) + 1;
	$term->slug = $map['categories'][ $key ] ?? $map['subtypes'][ $key ];
	$term->name = ucfirst( str_replace( '-', ' ', $term->slug ) );
	$term->parent = isset( $map['categories'][ $key ] ) ? 0 : array_search( BadAround_Event_Taxonomy_Map::category_for_subtype( $key ), $keys, true ) + 1;
	return array( $term );
}
$base = dirname( __DIR__ ) . '/wordpress/';
require $base . 'plugins/badaround-core/includes/class-badaround-report-schema.php';
require $base . 'plugins/badaround-core/includes/class-badaround-report-conditional-contract.php';
require $base . 'plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
require $base . 'plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php';
require $base . 'themes/badaround-child/inc/native-report.php';

function get_header(){require dirname(__DIR__).'/wordpress/themes/badaround-child/header.php';}
function get_footer(){require dirname(__DIR__).'/wordpress/themes/badaround-child/footer.php';}
function get_template_part($part){require dirname(__DIR__).'/wordpress/themes/badaround-child/'.$part.'.php';}
function language_attributes(){echo 'lang="it-IT"';}
function bloginfo($key){echo 'UTF-8';}
function body_class(){ $ctx=json_decode(file_get_contents(__DIR__.'/nfhome01-context.json'),true); echo 'class="'.esc_attr($ctx['body']).'"'; }
function wp_body_open(){ $ctx=json_decode(file_get_contents(__DIR__.'/nfhome01-context.json'),true);echo '<script>'.$ctx['breakpointScript'].'</script>'; }
function esc_html_e($s,$domain=''){echo esc_html($s);}
function esc_attr_e($s,$domain=''){echo esc_attr($s);}
function wp_date($format){return '2026';}
function wp_head(){
 $ctx=json_decode(file_get_contents(__DIR__.'/nfhome01-context.json'),true);
 foreach($ctx['styles'] as $url)echo '<link rel="stylesheet" href="'.esc_attr($url).'">';
 foreach($ctx['inline'] as $s)echo '<style id="'.esc_attr($s['id']).'">'.$s['text'].'</style>';
}
function wp_footer(){
 $ctx=json_decode(file_get_contents(__DIR__.'/nfhome01-context.json'),true);echo $ctx['scrollTop'];echo '<script>'.$ctx['astraScript'].'</script><script src="/wp-content/themes/astra/assets/js/minified/frontend.min.js"></script>'; 
 foreach(['navigation','ui'] as $js)echo '<script src="/wp-content/themes/badaround-child/assets/js/'.$js.'.js"></script>';
}

function add_query_arg($key,$value,$url){return $url.'?'.rawurlencode($key).'='.rawurlencode($value);}
if(in_array('--baseline',$argv,true)){ $src=shell_exec('git show d0ac50bf4866b326943f3f4487b49fbe3d878d7b:wordpress/themes/badaround-child/front-page.php'); $tmp=__DIR__.'/nfhome01-baseline-render.php';file_put_contents($tmp,$src);try{require $tmp;}finally{unlink($tmp);} }
else require dirname(__DIR__).'/wordpress/themes/badaround-child/front-page.php';
