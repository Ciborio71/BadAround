<?php
/** Isolated production PHP/SQL tests; no staging writes, no browser submissions. */
require __DIR__ . '/native-media-harness.php';
require_once $inc . 'class-badaround-moderation-admin.php';
require_once $inc . 'class-badaround-report-v2-validator.php';
$checks = 0;
function v2check( $ok, $message ) { global $checks; ++$checks; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $message ); } }
function v2code( $result ) { return is_wp_error( $result ) ? $result->get_error_code() : ( $result['error']['code'] ?? 'success' ); }
function v2put( &$p, $path, $value ) { BadAround_Report_Conditional_Contract::put( $p, $path, $value ); }
function v2get( $p, $path ) { return BadAround_Report_Conditional_Contract::value( $p, $path ); }
function v2unset( &$p, $path ) { $parts = explode( '.', $path ); $last = array_pop( $parts ); $cur =& $p; foreach ( $parts as $part ) { if ( ! isset( $cur[ $part ] ) ) { return; } $cur =& $cur[ $part ]; } unset( $cur[ $last ] ); }
function v2fixture( $subtype = 'animal_missing' ) {
	$c = BadAround_Report_Conditional_Contract::definition();
	foreach ( $c['regression_oracle'] as $test ) {
		if ( $test['subtype'] === $subtype ) {
			$p = f13_fixture();
			foreach ( $test['minimal_user_values'] as $path => $value ) { v2put( $p, $path, $value ); }
			$p['schema_version'] = 'badaround-report/v2'; $p['submission_id'] = BadAround_Native_Media_Config::new_uuid();
			return $p;
		}
	}
	throw new RuntimeException( 'Missing frozen oracle row.' );
}
function v2gold( $repo = null, $tax = null, $persist = null ) {
	$repo = $repo ?: new BadAround_Report_Repository();
	$persist = $persist ?: new BadAround_Report_Persistence_Service( $repo, $tax ?: new TestTax(), new TestTerritory(), new BadAround_Media_Repository(), new TestProjection() );
	return new BadAround_Native_Report_Golden_Path_Service( new BadAround_Native_Report_Intake_Service( null, null, $repo ), $persist, $repo );
}
$contract = BadAround_Report_Conditional_Contract::definition();
v2check( hash_file( 'sha256', $inc . '../contracts/nf-conditional-v1.0.json' ) === BadAround_Report_Conditional_Contract::SHA256, 'frozen artifact exact hash' );
v2check( count( $contract['matrix'] ) === 33 && count( $contract['regression_oracle'] ) === 33, '33 frozen rows/oracle cases' );
$assignments = 0; $damage_counts = array( 'REQUIRED' => 0, 'OPTIONAL' => 0, 'HIDDEN' => 0 );
foreach ( $contract['matrix'] as $row ) {
	$p = v2fixture( $row['subtype'] ); $n = BadAround_Report_Version_Registry::prepare( $p );
	v2check( ! is_wp_error( $n ), 'oracle valid ' . $row['subtype'] . ' ' . v2code( $n ) . ( is_wp_error( $n ) ? json_encode( $n->get_error_data() ) : '' ) );
	++$damage_counts[ $row['fields']['damage.status']['state'] ];
	foreach ( $row['fields'] as $path => $rule ) {
		++$assignments;
		if ( in_array( $path, array( 'schema_version', 'submission_id' ), true ) ) { $rule = array( 'state' => 'REQUIRED' ); } // NF-UX-02A explicit transport/identity overlay.
		if ( 'HIDDEN' === $rule['state'] ) {
			v2check( null === v2get( $n, $path ), 'hidden omitted ' . $row['subtype'] . '/' . $path );
			$stale = $p; v2put( $stale, $path, array( 'invalid_stale_value' ) );
			v2check( BadAround_Report_Version_Registry::prepare( $stale ) === $n, 'known hidden strips regardless of stale type ' . $path );
		}
		if ( 'REQUIRED' === $rule['state'] ) {
			$missing = $p; v2unset( $missing, $path );
			v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $missing ) ), 'required cannot be omitted ' . $path );
		}
		if ( 'OPTIONAL' === $rule['state'] ) {
			$missing = $p; v2unset( $missing, $path );
			if ( in_array( $path, array( 'location.exact_lat', 'location.exact_lng' ), true ) ) { v2unset( $missing, 'location.exact_lat' ); v2unset( $missing, 'location.exact_lng' ); } // Certified GEO pair invariant.
			v2check( ! is_wp_error( BadAround_Report_Version_Registry::prepare( $missing ) ), 'optional may be omitted ' . $path );
		}
	}
	$unknown = $p; $unknown['injected'] = 'x';
	v2check( v2code( BadAround_Report_Version_Registry::prepare( $unknown ) ) === 'ba_report_unknown_field', 'unknown always rejected' );
}
v2check( $assignments === 2805 && $damage_counts === array( 'REQUIRED' => 2, 'OPTIONAL' => 14, 'HIDDEN' => 17 ), 'matrix coverage and damage counts' );
$specs = array(
	'location.place_type_other_detail' => array( 'hazard_road_obstruction', 'location.place_type', 'other', 'road_sidewalk' ),
	'vehicle.type_other_detail' => array( 'vehicle_stolen', 'vehicle.type', 'other', 'car' ),
	'vehicle.color_other_detail' => array( 'vehicle_stolen', 'vehicle.color', 'other', 'red' ),
	'animal.type_other_detail' => array( 'animal_missing', 'animal.type', 'other', 'dog' ),
	'property.tampered_entry_point_other_detail' => array( 'property_attempted_breakin', 'property.tampered_entry_point', 'other', 'door' ),
	'property.stolen_item_categories_other_detail' => array( 'property_theft', 'property.stolen_item_categories', array( 'cash', 'other' ), array( 'cash' ) ),
	'property.access_method_other_detail' => array( 'property_theft', 'property.access_method', 'other', 'unknown' ),
	'authority.type_other_detail' => array( 'animal_missing', 'authority.type', 'other', 'local_police' ),
);
foreach ( $specs as $path => list( $subtype, $parent, $other, $normal ) ) {
	$p = v2fixture( $subtype ); if ( 0 === strpos( $path, 'authority.' ) ) { $p['authority']['status'] = 'yes'; }
	v2put( $p, $parent, $other );
	$missing = BadAround_Report_Version_Registry::prepare( $p );
	v2check( v2code( $missing ) === 'ba_report_required_field' && $missing->get_error_data()['field'] === $path, 'specific conditional required ' . $path );
	v2check( BadAround_Report_Result::from_wp_error( $missing )['error']['code'] === 'conditional_field_required', 'stable conditional error' );
	$max = $contract['fields'][ $path ]['constraints']['max_length'];
	foreach ( array( '', '   ', str_repeat( 'é', $max + 1 ), 7, array(), "test\nvalue", '<b>text</b>' ) as $invalid ) {
		$bad = $p; v2put( $bad, $path, $invalid ); v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $bad ) ), 'specific invalid type/text/boundary ' . $path );
	}
	foreach ( array( 'é', str_repeat( 'é', $max ) ) as $text ) { v2put( $p, $path, ' ' . $text . ' ' ); $n = BadAround_Report_Version_Registry::prepare( $p ); v2check( ! is_wp_error( $n ) && v2get( $n, $path ) === $text, 'specific Unicode length/trim ' . $path ); }
	$gold = v2gold(); $created = $gold->submit( $p ); v2check( v2code( $created ) === 'success', 'specific persists through Golden Path ' . $path );
	$stored = json_decode( $db->get_var( $db->prepare( 'SELECT content_original FROM test_ba_reports WHERE id=%d', $created['report_id'] ) ), true );
	v2check( v2get( $stored['canonical'], $path ) === v2get( $n, $path ) && $stored['schema_version'] === $stored['canonical']['schema_version'], 'specific canonical storage/coherent versions ' . $path );
	v2put( $p, $parent, $normal ); $clean = BadAround_Report_Version_Registry::prepare( $p ); v2check( ! is_wp_error( $clean ) && null === v2get( $clean, $path ), 'specific stripped after parent changes ' . $path );
}
// Conditional branches are exercised independently of evaluator implementation.
$p = v2fixture(); $p['authority'] = array( 'status' => 'yes', 'type' => 'other', 'type_other_detail' => 'Servizio' );
v2check( ! is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'authority branch valid' );
$p['authority']['status'] = 'no'; v2check( BadAround_Report_Version_Registry::prepare( $p )['authority'] === array( 'status' => 'no' ), 'authority descendants stripped' );
$p = v2fixture(); $p['reward'] = array( 'status' => 'fixed' ); v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'reward details required' );
$p['reward'] += array( 'amount' => 10, 'conditions' => 'Informazioni utili', 'expires_on' => '2030-01-01', 'confirmed' => true ); v2check( ! is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'reward branch valid' );
$p['event']['subtype'] = 'animal_injured'; v2check( ! isset( BadAround_Report_Version_Registry::prepare( $p )['reward'] ), 'reward inactive after subtype switch' );
$p = v2fixture( 'wallet_docs' ); $p['object']['status'] = 'found'; $p['object']['owner_verification_detail'] = 'private detail';
$p['reward'] = array( 'status' => 'fixed', 'amount' => 'stale' ); $n = BadAround_Report_Version_Registry::prepare( $p ); v2check( ! is_wp_error( $n ) && ! isset( $n['reward'] ) && isset( $n['object']['owner_verification_detail'] ), 'found item hides reward' );
$p['object']['status'] = 'lost'; v2unset( $p, 'reward' ); $n = BadAround_Report_Version_Registry::prepare( $p ); v2check( ! is_wp_error( $n ) && ! isset( $n['object']['owner_verification_detail'] ), 'lost item hides private found detail' );
$p = v2fixture( 'vehicle_stolen' ); $p['vehicle']['plate_knowledge'] = 'full'; v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'plate required when full' );
$p['vehicle']['plate_raw'] = 'ab 123 cd'; $n = BadAround_Report_Version_Registry::prepare( $p ); v2check( $n['vehicle']['plate_raw'] === 'AB123CD', 'plate normalization unchanged' );
$p['vehicle']['plate_knowledge'] = 'unknown'; v2check( ! isset( BadAround_Report_Version_Registry::prepare( $p )['vehicle']['plate_raw'] ), 'plate stale strips' );
$p = v2fixture(); $p['time'] = array( 'mode' => 'exact', 'date' => '2026-10-01', 'knowledge' => 'range', 'range_start' => '11:00', 'range_end' => '10:00' ); v2check( v2code( BadAround_Report_Version_Registry::prepare( $p ) ) === 'ba_report_invalid_time_range', 'time cross rules shared' );
$p['time']['mode'] = 'unknown'; v2check( BadAround_Report_Version_Registry::prepare( $p )['time'] === array( 'mode' => 'unknown' ), 'time descendants strip recursively' );
$p = v2fixture(); $p['animal']['type'] = 'do!g'; v2check( v2code( BadAround_Report_Version_Registry::prepare( $p ) ) === 'ba_report_invalid_enum', 'invalid enum never sanitized into valid value' );
$p = v2fixture(); $p['vehicle'] = array( 'injected' => 'x' ); v2check( v2code( BadAround_Report_Version_Registry::prepare( $p ) ) === 'ba_report_unknown_field', 'unknown under hidden group rejected before strip' );
$p = v2fixture(); $p['location']['public_location_request'] = 'approximate_area'; $p['location']['public_location_request_version'] = 'public-location-request-v1';
$n = BadAround_Report_Version_Registry::prepare( $p ); v2check( $n['location']['exact_lat'] === $p['location']['exact_lat'] && $n['location']['public_location_request'] === 'approximate_area', 'GEO request and private values unchanged' );
$p['location']['public_location_request_version'] = 'bad'; v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'GEO version cannot be bypassed' );

// Real repository, locks and SQL: version-safe service Golden Path, equality and faults.
$repo = new BadAround_Report_Repository(); $gold = v2gold( $repo ); $p = v2fixture();
$created = $gold->submit( $p ); $retry = $gold->submit( $p );
v2check( v2code( $created ) === 'success' && $created['report_id'] === $retry['report_id'] && $created['event_id'] === $retry['event_id'] && $retry['duplicate'], 'v2 retry retains report/event identity' );
v2check( get_post_status( $created['event_id'] ) === 'pending', 'v2 event pending, never published' );
$same = $p; $same['damage'] = array( 'status' => 'not_applicable', 'description' => array( 'stale' ) ); $same['vehicle'] = array( 'type' => array( 'stale' ) );
v2check( $gold->submit( $same )['event_id'] === $created['event_id'], 'stale-only difference idempotent' );
$reordered = array_reverse( $same, true ); v2check( $gold->submit( $reordered )['event_id'] === $created['event_id'], 'raw key order cannot change normalized equality' );
$changed = $p; $changed['animal']['name'] = 'different'; v2check( v2code( $gold->submit( $changed ) ) === 'duplicate_submission', 'active change conflicts' );
$v1 = f13_fixture(); $v1['submission_id'] = $p['submission_id']; v2check( v2code( $gold->submit( $v1 ) ) === 'duplicate_submission', 'v2 UUID to v1 conflicts' );
$v1 = f13_fixture(); $v1['submission_id'] = BadAround_Native_Media_Config::new_uuid(); $old = $gold->submit( $v1 );
$v2 = v2fixture(); $v2['submission_id'] = $v1['submission_id']; v2check( v2code( $gold->submit( $v2 ) ) === 'duplicate_submission', 'v1 UUID to v2 conflicts' );
$admin = new BadAround_Moderation_Admin();
foreach ( array( $created, $old ) as $result ) {
	$envelope = json_decode( $db->get_var( $db->prepare( 'SELECT content_original FROM test_ba_reports WHERE id=%d', $result['report_id'] ) ), true );
	$view = $admin->canonical_report_view( $envelope ); v2check( $view && $view['schema_version'] === $envelope['schema_version'], 'admin read distinguishes both versions' );
}
$bare = v2fixture(); $repo->create_native_intake_record( $bare['submission_id'] ); v2check( v2code( $gold->submit( $bare ) ) === 'duplicate_submission', 'unmarked legacy reservation cannot become v2' );
foreach ( array( 'INSERT INTO test_ba_reports ', 'UPDATE test_ba_reports SET author_name=' ) as $fault ) {
	$p = v2fixture(); $db->fail = static function( $q ) use ( $fault ) { return 0 === strpos( $q, $fault ); };
	$failed = $gold->submit( $p ); v2check( v2code( $failed ) !== 'success', 'injected persistence failure surfaced' );
	$opposite = f13_fixture(); $opposite['submission_id'] = $p['submission_id']; v2check( v2code( $gold->submit( $opposite ) ) === 'duplicate_submission', 'version remains bound after failure' );
	$recovered = $gold->submit( $p ); v2check( v2code( $recovered ) === 'success' && $gold->submit( $p )['event_id'] === $recovered['event_id'], 'same snapshot recovery has no duplicate event' );
}
class V2FaultTax extends TestTax { public $fail = true; public function assign( $event, $report ) { if ( $this->fail ) { $this->fail = false; return new WP_Error( 'injected_taxonomy_failure' ); } return parent::assign($event,$report); } }
$tax = new V2FaultTax(); $faultgold = v2gold( null, $tax ); $p = v2fixture(); $before = count( $GLOBALS['posts'] );
v2check( v2code( $faultgold->submit( $p ) ) !== 'success' && count( $GLOBALS['posts'] ) === $before, 'event creation partial failure compensated' );
$out = $faultgold->submit( $p ); v2check( v2code( $out ) === 'success' && count( $GLOBALS['posts'] ) === $before + 1, 'event recovery exactly once' );
// Existing REST envelope; no media coordinator may be constructed.
$controller = new BadAround_Native_Report_REST_Controller( v2gold(), static function() { throw new RuntimeException( 'No-media coordinator invoked' ); } );
$p = v2fixture(); $GLOBALS['transients'] = array();
$response = $controller->rest_create( new WP_REST_Request( json_encode( $p ), array( 'content-type' => 'application/json', 'x-badaround-intake' => 'badaround-report/v1' ) ) );
v2check( $response->status === 201 && $response->data['schema_version'] === 'badaround-report/v2' && ! isset( $response->data['normalized_payload'] ), 'v2 HTTP response accepts explicit version without private payload' );
$p['schema_version'] = 'badaround-report/v1'; $p = array_replace_recursive( f13_fixture(), $p ); $GLOBALS['transients'] = array();
$conflict = $controller->rest_create( new WP_REST_Request( json_encode( $p ), array( 'content-type' => 'application/json' ) ) );
v2check( $conflict->status === 409 && $conflict->data['error']['code'] === 'duplicate_submission' && ! $conflict->data['error']['retryable'], 'cross-version HTTP409 nonretryable' );
foreach ( array( null, '', 'badaround-report/v3' ) as $version ) { $p = v2fixture(); if ( null === $version ) { unset( $p['schema_version'] ); } else { $p['schema_version'] = $version; } v2check( is_wp_error( BadAround_Report_Version_Registry::prepare( $p ) ), 'missing/unsupported version never accepted' ); }
$v1 = f13_fixture(); $v1['animal']['type_other_detail'] = 'must not upgrade'; v2check( v2code( BadAround_Report_Version_Registry::prepare( $v1 ) ) === 'ba_report_unknown_field', 'v1 remains closed to v2-only fields' );
echo "NF_UX_02B_V2=PASS assertions={$checks} matrix_assignments={$assignments} oracle_cases=33 specs=8\n";
rmdir( $root ); rmdir( $public );
