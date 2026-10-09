<?php
/** F1.14 native-default / rollback routing contract test. */
define( 'ABSPATH', __DIR__ );
$rollback = in_array( '--rollback', $argv, true );
if ( $rollback ) { define( 'BADAROUND_NATIVE_REPORT_DEFAULT', false ); }

function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function home_url( $path = '' ) { return 'https://staging.badaround.it' . $path; }
function wp_parse_url( $url, $part ) { return parse_url( $url, $part ); }
function current_user_can() { return false; } // Anonymous public path.
function is_page_template( $templates ) { return in_array( 'page-segnala-evento.php', (array) $templates, true ); }
function get_header() {}
function get_footer() {}
function get_stylesheet_directory() { return dirname( __DIR__ ) . '/wordpress/themes/badaround-child'; }
function get_stylesheet_directory_uri() { return home_url( '/wp-content/themes/badaround-child' ); }
function get_template_part( $part ) { require get_stylesheet_directory() . '/' . $part . '.php'; }
function wpforms() {
	return (object) array( 'frontend' => new class {
		public function output( $id ) { echo '<div data-test-wpforms="' . (int) $id . '"></div>'; }
	} );
}
function rest_url( $path ) { return home_url( '/wp-json/' . $path ); }
function wp_timezone_string() { return 'Europe/Rome'; }
function esc_attr( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $value ) { return esc_attr( $value ); }
function esc_url( $value ) { return esc_attr( $value ); }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function get_privacy_policy_url() { return home_url( '/privacy-policy/' ); }
function get_permalink() { return home_url( '/segnala-un-evento/' ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function is_wp_error() { return false; }
function is_front_page() { return false; }
function is_singular() { return false; }
function is_tax() { return false; }
function is_404() { return false; }
function is_page() { return false; }
function wp_enqueue_style( $handle ) { $GLOBALS['assets'][ $handle ] = true; }
function wp_enqueue_script( $handle, $url = '', $deps = array() ) { $GLOBALS['assets'][ $handle ] = $deps; }
function wp_script_add_data() {}
function wp_resource_hints() {}
function nocache_headers() {}
class WP_Term { public $name; public $slug; public $parent; public $term_id; }
class BadAround_Event_Post_Type { const EVENT_TYPE_TAX = 'ba_tipo_evento'; const TERRITORY_TAX = 'ba_territorio'; }
function get_terms( $query ) {
	if ( isset( $query['taxonomy'] ) && BadAround_Event_Post_Type::TERRITORY_TAX === $query['taxonomy'] ) {
		$fixture = array(
			array( 2, 'Lazio', 'lazio', 0 ),
			array( 3, 'Roma', 'roma', 2 ),
			array( 4, 'Pomezia', 'pomezia', 3 ),
			array( 5, 'Torvaianica', 'torvaianica', 4 ),
		);
		$terms = array_map(
			static function ( $row ) {
				$term = new WP_Term();
				$term->term_id = $row[0]; $term->name = $row[1]; $term->slug = $row[2]; $term->parent = $row[3];
				return $term;
			},
			$fixture
		);
		if ( isset( $query['name'] ) ) {
			$terms = array_values( array_filter( $terms, static function ( $term ) use ( $query ) { return $term->name === $query['name']; } ) );
		}
		if ( isset( $query['parent'] ) ) {
			$terms = array_values( array_filter( $terms, static function ( $term ) use ( $query ) { return (int) $term->parent === (int) $query['parent']; } ) );
		}
		return $terms;
	}
	$map = BadAround_Event_Taxonomy_Map::mapping();
	$key = $query['meta_value']; $term = new WP_Term();
	$keys = array_merge( array_keys( $map['categories'] ), array_keys( $map['subtypes'] ) );
	$term->term_id = array_search( $key, $keys, true ) + 1;
	$term->slug = $map['categories'][ $key ] ?? $map['subtypes'][ $key ];
	$term->name = ucfirst( str_replace( '-', ' ', $term->slug ) );
	$term->parent = isset( $map['categories'][ $key ] ) ? 0 : array_search( BadAround_Event_Taxonomy_Map::category_for_subtype( $key ), $keys, true ) + 1;
	return array( $term );
}

function f114_assert( $ok, $message ) {
	if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
	echo "PASS: $message\n";
}

$base = dirname( __DIR__ ) . '/wordpress/';
require $base . 'plugins/badaround-core/includes/class-badaround-report-schema.php';
require $base . 'plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
require $base . 'plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php';
require $base . 'plugins/badaround-core/includes/class-badaround-territory-resolver.php';
require $base . 'themes/badaround-child/inc/native-report.php';

$_SERVER['HTTP_HOST'] = 'staging.badaround.it';
$_GET = array();

f114_assert( badaround_native_report_available(), 'native runtime available' );
f114_assert( ( $rollback ? 'wpforms' : 'native' ) === badaround_report_entry_mode(), 'single routing control selects expected engine' );
f114_assert( ( ! $rollback ) === badaround_native_report_use_native(), 'native helper reflects control point' );
f114_assert( ! badaround_native_report_qa_enabled(), 'ordinary anonymous URL is not QA mode' );

$territory_registry = badaround_native_report_territory_registry();
f114_assert( 'Pomezia' === $territory_registry['municipality'][0]['value'], 'registry exposes canonical municipality Pomezia' );
f114_assert( array( 'Lazio', 'Roma' ) === $territory_registry['municipality'][0]['lineage'], 'registry preserves municipality lineage' );
$resolver = new BadAround_Territory_Resolver();
f114_assert( 4 === $resolver->resolve_location( array( 'region' => 'Lazio', 'province' => 'Roma', 'municipality' => 'Pomezia' ) ), 'explicit canonical municipality chain resolves deterministically' );
f114_assert( 5 === $resolver->resolve_location( array( 'region' => 'Lazio', 'province' => 'Roma', 'municipality' => 'Pomezia', 'locality' => 'Torvaianica' ) ), 'explicit canonical locality chain resolves deterministically' );
f114_assert( 0 === $resolver->resolve_location( array( 'region' => 'Lombardia', 'province' => 'Milano', 'municipality' => 'Milano' ) ), 'unsupported hierarchy fails closed before persistence' );

require $base . 'themes/badaround-child/functions.php';
$GLOBALS['assets'] = array();
badaround_child_enqueue_assets();
ob_start();
require $base . 'themes/badaround-child/page-segnala-evento.php';
$html = ob_get_clean();

if ( $rollback ) {
	f114_assert( false === strpos( $html, 'data-native-report' ), 'rollback hides native wizard' );
	f114_assert( false !== strpos( $html, 'data-test-wpforms="6"' ), 'rollback renders WPForms form ID 6' );
	f114_assert( isset( $GLOBALS['assets']['badaround-report-ui'], $GLOBALS['assets']['badaround-wpforms'] ), 'rollback loads legacy assets' );
	f114_assert( ! isset( $GLOBALS['assets']['badaround-native-wizard'] ), 'rollback does not load native wizard assets' );
} else {
	f114_assert( false !== strpos( $html, 'data-native-report' ), 'anonymous ordinary URL renders native wizard by default' );
	f114_assert( false === strpos( $html, 'data-test-wpforms="6"' ), 'native default does not render WPForms' );
	f114_assert( isset( $GLOBALS['assets']['badaround-native-wizard'] ), 'native default loads native assets' );
	f114_assert( ! isset( $GLOBALS['assets']['badaround-report-ui'], $GLOBALS['assets']['badaround-wpforms'] ), 'native default isolates legacy assets' );
	f114_assert( false !== strpos( $html, 'data-native-territory="region"' ), 'Step 2 region is a canonical registry selector' );
	f114_assert( false !== strpos( $html, 'data-native-territory="municipality"' ), 'Step 2 municipality is a canonical registry selector' );
	f114_assert( false !== strpos( $html, '>Pomezia</option>' ), 'canonical municipality Pomezia is rendered' );
	f114_assert( false === strpos( $html, '>Milano</option>' ), 'unsupported free municipality is not rendered' );
}

$inc = file_get_contents( $base . 'themes/badaround-child/inc/native-report.php' );
f114_assert( false !== strpos( $inc, "BADAROUND_NATIVE_REPORT_DEFAULT" ), 'explicit administrative rollback constant exists' );
f114_assert( false !== strpos( $inc, "X-Robots-Tag: noindex, nofollow, noarchive" ), 'QA-only robots header retained' );
f114_assert( false !== strpos( $inc, "badaround_native_report_qa_enabled()" ), 'legacy QA diagnostic marker retained separately' );
f114_assert( false !== strpos( $inc, 'badaround_native_report_territory_registry()' ), 'canonical territory registry adapter is present' );
$model_js = file_get_contents( $base . 'themes/badaround-child/assets/js/native-report/model.js' );
$wizard_js = file_get_contents( $base . 'themes/badaround-child/assets/js/native-report/wizard.js' );
f114_assert( false !== strpos( $model_js, 'canonicalTerritoryEnabled' ), 'client validation requires a canonical region/province/municipality chain' );
f114_assert( false !== strpos( $wizard_js, 'syncTerritories()' ), 'wizard cascades canonical territory selectors' );

echo $rollback ? "F1.14 rollback routing tests complete.\n" : "F1.14 native-default routing tests complete.\n";
