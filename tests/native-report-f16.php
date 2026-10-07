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
require $base . 'plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
require $base . 'plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php';
require $base . 'themes/badaround-child/inc/native-report.php';
$config = badaround_native_report_config();
if ( in_array( '--config', $argv, true ) ) { echo json_encode( $config ); exit; }
if ( in_array( '--html', $argv, true ) ) {
	echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>F1.6 native fixture</title>';
	foreach ( array( 'base', 'components', 'report', 'native-report' ) as $css ) { echo '<link rel="stylesheet" href="/wordpress/themes/badaround-child/assets/css/' . $css . '.css">'; }
	echo '<body class="page-template-page-segnala-evento"><main class="ba-report-app"><section class="ba-report-workspace ba-container"><header class="ba-report-workspace__head"><h1>Segnala un evento</h1></header><div class="ba-report-stage">';
	require $base . 'themes/badaround-child/template-parts/native-report/shell.php';
	echo '</div></section></main>';
	foreach ( array( 'model', 'api', 'errors', 'wizard' ) as $js ) { echo '<script src="/wordpress/themes/badaround-child/assets/js/native-report/' . $js . '.js"></script>'; }
	echo '</body></html>'; exit;
}
function f16_assert( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } echo "PASS: $message\n"; }
$_GET['native_report'] = '1';
f16_assert( badaround_native_report_qa_enabled(), 'staging administrator QA gate' );
$GLOBALS['test_admin'] = false;
f16_assert( ! badaround_native_report_qa_enabled(), 'anonymous query cannot activate native form' );
$GLOBALS['test_admin'] = true; $GLOBALS['test_origin'] = 'https://www.badaround.it';
f16_assert( ! badaround_native_report_qa_enabled(), 'production cannot activate QA form' );
$GLOBALS['test_origin'] = 'https://staging.badaround.it'; unset( $_GET['native_report'] );
f16_assert( ! badaround_native_report_qa_enabled(), 'ordinary administrator URL preserves legacy form' );
f16_assert( 'badaround-report/v1' === $config['schemaVersion'], 'schema version from frozen F1.2' );
$schema = BadAround_Report_Schema::fields();
$excluded = array( 'schema_version', 'submission_id', 'consents.version', 'media.items', 'location.place_id' );
f16_assert( count( $config['fields'] ) === count( $schema ) - count( $excluded ), 'all user fields covered except deferred upload/Places and generated envelope' );
foreach ( $config['fields'] as $path => $field ) {
	f16_assert( isset( $schema[ $path ] ) && $field['required'] === $schema[ $path ]['required'] && $field['conditions'] === $schema[ $path ]['conditions'] && $field['constraints'] === $schema[ $path ]['constraints'], 'contract-derived ' . $path );
	f16_assert( ! isset( $field['destination'], $field['public_projection'] ) && $field['label'] !== $path, 'public presentation only ' . $path );
	if ( 'enum' === $field['type'] || 'array' === $field['type'] ) {
		foreach ( $field['options'] as $value => $label ) { f16_assert( $label !== $value, 'Italian option label ' . $path . ':' . $value ); }
	}
}
f16_assert( array_keys( $config['categories'] ) === array( 'vehicle', 'property', 'hazard', 'public_space', 'animal', 'item_document' ), 'approved category presentation order' );
foreach ( $config['categories'] as $key => $category ) { f16_assert( array_keys( $category['subtypes'] ) === BadAround_Report_Schema::category_subtypes()[ $key ], 'canonical subtype membership ' . $key ); }
f16_assert( strpos( json_encode( $config ), 'ba_reports.' ) === false, 'no internal storage metadata exposed' );
ob_start(); require $base . 'themes/badaround-child/template-parts/native-report/shell.php'; $html = ob_get_clean();
f16_assert( 7 === substr_count( $html, '<section data-native-step=' ), 'seven steps server rendered' );
f16_assert( count( $config['fields'] ) === substr_count( $html, 'data-native-field=' ), 'server-rendered field structure' );
f16_assert( strpos( $html, '<noscript>' ) !== false && strpos( $html, 'data-native-submit disabled hidden' ) !== false, 'no-JS consultation and disabled submit' );
f16_assert( strpos( $html, 'aria-describedby=' ) !== false && strpos( $html, '<fieldset' ) !== false && strpos( $html, 'role="alert"' ) !== false, 'accessible labels/groups/error summary' );
f16_assert( strpos( $html, 'wpforms' ) === false && strpos( $html, 'type="file"' ) === false, 'no builder runtime or fictitious upload' );
$GLOBALS['missing_taxonomy'] = true;
f16_assert( null === badaround_native_report_config(), 'missing taxonomy fails closed without mutations' );
echo "F1.6 PHP/SSR tests complete.\n";
