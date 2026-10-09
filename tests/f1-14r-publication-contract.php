<?php
/**
 * F1.14R publication contract reconciliation.
 *
 * Synthetic contract tests only: no provider, no schema migration, no media writes.
 */
define( 'ABSPATH', __DIR__ . '/' );

function __( $x ){ return $x; }
function absint( $v ){ return abs( (int) $v ); }
function sanitize_key( $v ){ return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ); }
function sanitize_text_field( $v ){ return trim( strip_tags( (string) $v ) ); }
function wp_strip_all_tags( $v ){ return strip_tags( (string) $v ); }
function wp_kses_post( $v ){ return (string) $v; }
function is_wp_error( $v ){ return $v instanceof WP_Error; }
function current_user_can(){ return true; }
function current_time( $type, $gmt = false ){ return $gmt ? '2026-10-09 13:00:00' : '2026-10-09 15:00:00'; }
function wp_salt( $scheme = 'auth' ){ return 'f114r-publication-contract'; }
function add_filter(){ return true; }
function remove_filter(){ return true; }
function do_action(){ return true; }
function has_post_thumbnail(){ return false; }
function set_post_thumbnail(){ return true; }

class WP_Error {
	private $code;
	public function __construct( $code, $message = '' ){ $this->code = $code; }
	public function get_error_code(){ return $this->code; }
}
class WP_Term {
	public $term_id, $parent, $slug, $name, $taxonomy;
	public function __construct( $id, $parent, $slug, $name, $taxonomy = 'ba_territorio' ){
		$this->term_id = $id; $this->parent = $parent; $this->slug = $slug; $this->name = $name; $this->taxonomy = $taxonomy;
	}
}
class WP_Post {
	public $ID, $post_type, $post_status, $post_title, $post_content;
	public function __construct( $id, $status = 'pending', $title = 'Segnalazione da moderare #1', $content = '' ){
		$this->ID = $id; $this->post_type = 'ba_evento'; $this->post_status = $status; $this->post_title = $title; $this->post_content = $content;
	}
}
class BadAround_Event_Post_Type {
	const POST_TYPE = 'ba_evento';
	const EVENT_TYPE_TAX = 'ba_tipo_evento';
	const TERRITORY_TAX = 'ba_territorio';
}
class BadAround_Moderation_Service { const STATUS_APPROVED = 'approved'; }
class BadAround_Audit_Log {
	public static $rows = array();
	public static function record(){ self::$rows[] = func_get_args(); return true; }
	public static function transition(){ self::$rows[] = func_get_args(); return true; }
}

$GLOBALS['f114r_terms'] = array(
	2  => new WP_Term( 2, 0, 'lazio', 'Lazio' ),
	3  => new WP_Term( 3, 2, 'roma', 'Roma' ),
	4  => new WP_Term( 4, 3, 'pomezia', 'Pomezia' ),
	5  => new WP_Term( 5, 4, 'torvaianica', 'Torvaianica' ),
	6  => new WP_Term( 6, 0, 'veicoli', 'Veicoli', 'ba_tipo_evento' ),
	11 => new WP_Term( 11, 6, 'veicolo-rubato', 'Veicolo rubato', 'ba_tipo_evento' ),
);
$GLOBALS['f114r_posts'] = array();
$GLOBALS['f114r_meta'] = array();
$GLOBALS['f114r_terms_for_post'] = array();
$GLOBALS['f114r_term_meta'] = array();
$GLOBALS['f114r_report'] = null;
$GLOBALS['f114r_sensitive_meta_count'] = 0;
$GLOBALS['f114r_public_media'] = array();

function get_term( $id, $taxonomy = '' ){
	$id = (int) $id;
	$term = $GLOBALS['f114r_terms'][ $id ] ?? null;
	if ( ! $term ) return null;
	if ( $taxonomy && $taxonomy !== $term->taxonomy ) return null;
	return $term;
}
function get_ancestors( $id, $taxonomy, $type = 'taxonomy' ){
	$out = array(); $seen = array(); $term = get_term( $id, $taxonomy );
	while ( $term && $term->parent && ! isset( $seen[ $term->parent ] ) ) {
		$seen[ $term->parent ] = true;
		$out[] = (int) $term->parent;
		$term = get_term( $term->parent, $taxonomy );
	}
	return $out;
}
function get_term_meta( $id, $key, $single = true ){
	return $GLOBALS['f114r_term_meta'][ (int) $id ][ $key ] ?? '';
}
function get_post( $id ){ return $GLOBALS['f114r_posts'][ (int) $id ] ?? null; }
function get_post_status( $id ){ $post = get_post( $id ); return $post ? $post->post_status : null; }
function get_post_meta( $id, $key, $single = true ){ return $GLOBALS['f114r_meta'][ (int) $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ){ $GLOBALS['f114r_meta'][ (int) $id ][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ){ unset( $GLOBALS['f114r_meta'][ (int) $id ][ $key ] ); return true; }
function wp_get_post_terms( $id, $taxonomy ){
	$ids = $GLOBALS['f114r_terms_for_post'][ (int) $id ][ $taxonomy ] ?? array();
	return array_values( array_filter( array_map( static fn( $term_id ) => get_term( $term_id, $taxonomy ), $ids ) ) );
}
function get_the_terms( $id, $taxonomy ){ return wp_get_post_terms( $id, $taxonomy ); }
function wp_set_object_terms( $id, $terms, $taxonomy, $append = false ){
	$GLOBALS['f114r_terms_for_post'][ (int) $id ][ $taxonomy ] = array_values( array_map( 'intval', $terms ) );
	return $GLOBALS['f114r_terms_for_post'][ (int) $id ][ $taxonomy ];
}
function wp_update_post( $data, $error = false ){
	$id = (int) $data['ID'];
	if ( ! isset( $GLOBALS['f114r_posts'][ $id ] ) ) return new WP_Error( 'missing_post' );
	foreach ( $data as $key => $value ) {
		if ( 'ID' !== $key ) $GLOBALS['f114r_posts'][ $id ]->$key = $value;
	}
	return $id;
}

class F114R_WPDB {
	public $prefix = 'wp_';
	public $postmeta = 'wp_postmeta';
	public function prepare( $sql ){ return $sql; }
	public function get_row( $sql ){ return $GLOBALS['f114r_report']; }
	public function get_var( $sql ){ return $GLOBALS['f114r_sensitive_meta_count']; }
	public function get_results( $sql ){ return $GLOBALS['f114r_public_media']; }
}
$wpdb = new F114R_WPDB();

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-discovery-query.php';

function f114r_assert( $ok, $message ){
	if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); }
	echo "PASS: $message\n";
}

function f114r_report( $overrides = array() ){
	return (object) array_merge(
		array(
			'author_name' => 'QA',
			'author_surname' => 'Tester',
			'author_email' => 'qa@example.invalid',
			'author_phone' => '',
			'exact_address' => 'Via Segreta 10',
			'exact_lat' => null,
			'exact_lng' => null,
			'full_plate' => '',
			'content_original' => '{}',
		),
		$overrides
	);
}

function f114r_event( $id, $territory_id = 5, $precision = 'f32-c4', $meta = array(), $report = null ){
	$GLOBALS['f114r_posts'][ $id ] = new WP_Post( $id );
	$GLOBALS['f114r_terms_for_post'][ $id ] = array(
		'ba_territorio' => $territory_id ? array( $territory_id ) : array(),
		'ba_tipo_evento' => array( 6, 11 ),
	);
	$GLOBALS['f114r_meta'][ $id ] = array_merge(
		array(
			'_ba_moderation_status' => 'approved',
			'_ba_event_status' => 'open',
			'_ba_public_location_precision' => $precision,
			'_ba_public_place_name' => $territory_id ? get_term( $territory_id, 'ba_territorio' )->name : '',
			'_ba_time_precision' => 'f16-c4',
			'_ba_occurred_date' => '09/10/2026',
			'_ba_occurred_time' => '',
			'_ba_vehicle_make' => 'Fiat',
			'_ba_vehicle_model' => '500',
			'_ba_vehicle_color' => 'Grigio',
			'_ba_vehicle_plate_masked' => '',
		),
		$meta
	);
	$GLOBALS['f114r_report'] = $report ?: f114r_report();
	$GLOBALS['f114r_sensitive_meta_count'] = 0;
	$GLOBALS['f114r_public_media'] = array();
	$GLOBALS['f114r_term_meta'] = array();
}

$svc = new BadAround_Publication_Service();

/* A. known date + public coordinates -> PASS. */
f114r_event( 100, 5, 'f32-c4', array(
	'_ba_public_lat' => 41.6501,
	'_ba_public_lng' => 12.4501,
	'_ba_public_radius_m' => 150,
) );
$r = $svc->prepare_public_projection( 100 );
f114r_assert( true === $r, 'A projection prepares known-date event with safe public coordinates' );
$r = $svc->validate_public_projection( 100 );
f114r_assert( true === $r, 'A known date + public coordinates publishes' );

/* B. unknown time + valid canonical territory + no coordinates -> PASS. */
f114r_event( 101, 5, 'f32-c4', array(
	'_ba_time_precision' => 'f16-c8',
	'_ba_occurred_date' => '',
	'_ba_occurred_time' => '',
	'_ba_public_lat' => null,
	'_ba_public_lng' => null,
	'_ba_public_radius_m' => 0,
), f114r_report( array( 'exact_lat' => null, 'exact_lng' => null ) ) );
$r = $svc->prepare_public_projection( 101 );
f114r_assert( true === $r, 'B unknown-time territory-only projection prepares without fabricated geo' );
f114r_assert( '' === get_post_meta( 101, '_ba_public_lat', true ) && '' === get_post_meta( 101, '_ba_public_lng', true ), 'B territory-only projection has no public coordinates' );
f114r_assert( false === strpos( get_post( 101 )->post_content, '09/10/2026' ), 'B unknown time does not fabricate a date' );
$r = $svc->validate_public_projection( 101 );
f114r_assert( true === $r, 'B valid canonical unknown time does not block publication' );

/* Canonical approximate/ongoing/repeated time states are also valid without an exact date. */
foreach ( array( 'f16-c5', 'f16-c6', 'f16-c7' ) as $index => $time_precision ) {
	$id = 120 + $index;
	f114r_event( $id, 5, 'f32-c6', array(
		'_ba_time_precision' => $time_precision,
		'_ba_occurred_date' => '',
		'_ba_occurred_time' => '',
	), f114r_report() );
	f114r_assert( true === $svc->prepare_public_projection( $id ), "canonical time state {$time_precision} prepares without exact date" );
	f114r_assert( true === $svc->validate_public_projection( $id ), "canonical time state {$time_precision} is publishable without fabricated date" );
}

/* C. known date + canonical territory + no coordinates -> PASS. */
f114r_event( 102, 5, 'f32-c5', array(
	'_ba_time_precision' => 'f16-c4',
	'_ba_occurred_date' => '09/10/2026',
	'_ba_public_lat' => null,
	'_ba_public_lng' => null,
	'_ba_public_radius_m' => 0,
), f114r_report( array( 'exact_lat' => null, 'exact_lng' => null ) ) );
f114r_assert( true === $svc->prepare_public_projection( 102 ), 'C known-time territory-only projection prepares' );
f114r_assert( true === $svc->validate_public_projection( 102 ), 'C known time + canonical territory + no coordinates publishes' );

/* Missing exact date with exact canonical time remains invalid. */
f114r_event( 103, 5, 'f32-c4', array(
	'_ba_time_precision' => 'f16-c4',
	'_ba_occurred_date' => '',
) );
f114r_assert( true === $svc->prepare_public_projection( 103 ), 'exact-time projection can prepare before validation' );
$r = $svc->validate_public_projection( 103 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_date_missing' === $r->get_error_code(), 'missing date remains invalid for exact canonical time' );

/* Malformed time precision remains invalid. */
f114r_event( 104, 5, 'f32-c4', array( '_ba_time_precision' => 'broken-time-state' ) );
f114r_assert( true === $svc->prepare_public_projection( 104 ), 'malformed-time projection can prepare before validation' );
$r = $svc->validate_public_projection( 104 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_time_invalid' === $r->get_error_code(), 'malformed canonical time state is rejected' );

/* D. no canonical territory + no coordinates -> FAIL. */
f114r_event( 105, 0, 'f32-c4', array(
	'_ba_public_place_name' => '',
	'_ba_public_lat' => null,
	'_ba_public_lng' => null,
	'_ba_public_radius_m' => 0,
), f114r_report( array( 'exact_lat' => null, 'exact_lng' => null ) ) );
$r = $svc->prepare_public_projection( 105 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_location_missing' === $r->get_error_code(), 'D no canonical territory + no coordinates fails closed' );

/* E. private exact address alone is not public location evidence. */
f114r_event( 106, 0, 'f32-c4', array(
	'_ba_public_place_name' => '',
), f114r_report( array( 'exact_address' => 'Via Gran Bretagna 66', 'exact_lat' => null, 'exact_lng' => null ) ) );
$r = $svc->prepare_public_projection( 106 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_location_missing' === $r->get_error_code(), 'E exact private address alone cannot satisfy publication location' );
f114r_assert( false === strpos( get_post( 106 )->post_title, 'Via Gran Bretagna 66' ) && false === strpos( get_post( 106 )->post_content, 'Via Gran Bretagna 66' ), 'E private exact address is not copied to title/content' );

/* F. municipality mode never derives public coordinates from private exact coordinates. */
f114r_event( 107, 5, 'f32-c7', array(
	'_ba_public_place_name' => 'Torvaianica',
	'_ba_public_lat' => 41.600001,
	'_ba_public_lng' => 12.400001,
	'_ba_public_radius_m' => 150,
), f114r_report( array( 'exact_lat' => 41.777777, 'exact_lng' => 12.777777 ) ) );
$r = $svc->prepare_public_projection( 107 );
f114r_assert( true === $r, 'F municipality projection prepares' );
f114r_assert( '' === get_post_meta( 107, '_ba_public_lat', true ) && '' === get_post_meta( 107, '_ba_public_lng', true ), 'F municipality mode exposes no private-derived coordinates without verified centroid' );
f114r_assert( 'Pomezia' === get_post_meta( 107, '_ba_public_place_name', true ), 'F municipality mode collapses public label to canonical Comune' );
f114r_assert( true === $svc->validate_public_projection( 107 ), 'F municipality label-only projection validates' );

/* G. full plate leak -> FAIL. */
f114r_event( 108, 5, 'f32-c4', array(
	'_ba_vehicle_plate_masked' => 'AB123FR',
), f114r_report( array( 'full_plate' => 'AB123FR' ) ) );
f114r_assert( true === $svc->prepare_public_projection( 108 ), 'G projection prepares before plate validation' );
$r = $svc->validate_public_projection( 108 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_plate_not_masked' === $r->get_error_code(), 'G full plate leak is rejected' );

/* H. reporter PII leak -> FAIL. */
f114r_event( 109, 5, 'f32-c4', array(), f114r_report( array( 'author_email' => 'private@example.test' ) ) );
$GLOBALS['f114r_posts'][109]->post_title = 'Titolo pubblico';
$GLOBALS['f114r_posts'][109]->post_content = 'Contenuto pubblico private@example.test';
$r = $svc->validate_public_projection( 109 );
f114r_assert( is_wp_error( $r ) && 'ba_publication_private_data_detected' === $r->get_error_code(), 'H PII leak is rejected' );

/* Discovery/map geo: unverified territory centre must not fabricate a marker. */
f114r_event( 110, 5, 'f32-c4', array(
	'_ba_public_lat' => null,
	'_ba_public_lng' => null,
	'_ba_public_radius_m' => 0,
) );
$GLOBALS['f114r_term_meta'][5] = array(
	'_ba_center_lat' => '41.650000',
	'_ba_center_lng' => '12.450000',
	'_ba_geo_level' => 'locality',
);
$discovery = new BadAround_Discovery_Query();
$method = new ReflectionMethod( BadAround_Discovery_Query::class, 'public_geo' );
$method->setAccessible( true );
$geo = $method->invoke( $discovery, 110 );
f114r_assert( null === $geo, 'unverified canonical territory centre does not produce public geo' );
$GLOBALS['f114r_term_meta'][5]['_ba_geo_verified'] = '1';
$geo = $method->invoke( $discovery, 110 );
f114r_assert( is_array( $geo ) && 'territory_center' === $geo['source'] && 1000 <= $geo['radius_m'], 'verified canonical locality centre may provide conservative map geo' );

/* I. Native media fail-closed gate remains in publication path; media implementation is untouched. */
$publication_source = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php' );
f114r_assert( false !== strpos( $publication_source, "BadAround_Native_Media_Fence::check" ), 'I publication still enforces Native media fence' );

echo "F1.14R_TIME_UNKNOWN=PASS\n";
echo "F1.14R_TERRITORY_ONLY=PASS\n";
echo "F1.14R_UNVERIFIED_MAP_GEO=BLOCKED\n";
echo "F1.14R_PUBLICATION_CONTRACT_TESTS=PASS\n";
