<?php
require __DIR__ . '/native-media-harness.php';
$baseline = '1f9e1cc56f120dec6f634fc2ac6233e803623b88';
$names = array( 'BadAround_Report_Normalizer' => 'NF02B_Frozen_Normalizer', 'BadAround_Report_Validator' => 'NF02B_Frozen_Validator', 'BadAround_Report_Result' => 'NF02B_Frozen_Result', 'BadAround_Report_Repository' => 'NF02B_Frozen_Repository', 'BadAround_Report_Idempotency_Service' => 'NF02B_Frozen_Idempotency', 'BadAround_Native_Report_Intake_Service' => 'NF02B_Frozen_Intake', 'BadAround_Native_Report_Golden_Path_Service' => 'NF02B_Frozen_Golden', 'BadAround_Native_Report_REST_Controller' => 'NF02B_Frozen_Controller' );
foreach ( array( 'report-normalizer', 'report-validator', 'report-result', 'report-repository', 'report-idempotency-service', 'native-report-intake-service', 'native-report-golden-path-service', 'native-report-rest-controller' ) as $file ) {
	$pipes = array(); $proc = proc_open( array( 'git', 'show', $baseline . ':wordpress/plugins/badaround-core/includes/class-badaround-' . $file . '.php' ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, dirname( __DIR__ ) );
	$source = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); if ( proc_close( $proc ) !== 0 ) { throw new RuntimeException( 'Exact frozen baseline unavailable: ' . $error ); }
	eval( substr( strtr( str_replace( '__DIR__', var_export( $inc, true ), $source ), $names ), 5 ) );
}
$cases = array( f13_fixture() );
foreach ( array_keys( BadAround_Report_Schema::fields() ) as $path ) {
	$p = f13_fixture(); $parts = explode( '.', $path ); $last = array_pop( $parts ); $cur =& $p; foreach ( $parts as $part ) { if ( ! isset( $cur[ $part ] ) ) { $cur[ $part ] = array(); } $cur =& $cur[ $part ]; } unset( $cur[ $last ] ); unset( $cur ); $cases[] = $p;
}
foreach ( array( null, false, 7, array(), 'other', 'unverified' ) as $value ) { $p = f13_fixture(); $p['damage']['status'] = $value; $cases[] = $p; }
$p = f13_fixture(); $p['unknown'] = 'x'; $cases[] = $p;
$p = f13_fixture(); $p['schema_version'] = 'badaround-report/v3'; $cases[] = $p;
$p = f13_fixture(); $p['location']['public_location_request'] = 'exact_point'; $p['location']['public_location_request_version'] = 'public-location-request-v1'; $cases[] = $p;
$p = f13_fixture(); $p['reporter']['social_visibility'] = 'requested'; $p['consents']['social_visibility_version'] = 'social-preference-v1'; $cases[] = $p;
function nd_error( $r ) { return is_wp_error( $r ) ? array( $r->get_error_code(), $r->get_error_data() ) : $r; }
$count = 0;
foreach ( $cases as $p ) {
	$a = new NF02B_Frozen_Normalizer(); $b = new BadAround_Report_Normalizer(); $av = new NF02B_Frozen_Validator(); $bv = new BadAround_Report_Validator();
	if ( $a->normalize( $p ) !== $b->normalize( $p ) || nd_error( $av->validate_raw_contract( $p ) ) !== nd_error( $bv->validate_raw_contract( $p ) ) || nd_error( $av->validate( $a->normalize( $p ) ) ) !== nd_error( $bv->validate( $b->normalize( $p ) ) ) ) { throw new RuntimeException( 'V1 differential failed at case ' . $count ); }
	++$count;
}
function nd_flow( $frozen ) {
	global $db; $db->query( 'DELETE FROM test_ba_reports' ); $db->query( 'DELETE FROM test_options' ); $GLOBALS['posts'] = array(); $GLOBALS['transients'] = array();
	$repo = $frozen ? new NF02B_Frozen_Repository() : new BadAround_Report_Repository();
	$persist = new BadAround_Report_Persistence_Service( $repo, new TestTax(), new TestTerritory(), new BadAround_Media_Repository(), new TestProjection() );
	$intake = $frozen ? new NF02B_Frozen_Intake( null, null, $repo ) : new BadAround_Native_Report_Intake_Service( null, null, $repo );
	$gold = $frozen ? new NF02B_Frozen_Golden( $intake, $persist, $repo ) : new BadAround_Native_Report_Golden_Path_Service( $intake, $persist, $repo );
	$factory = static function() { throw new RuntimeException( 'No-media factory invoked' ); };
	$controller = $frozen ? new NF02B_Frozen_Controller( $gold, $factory ) : new BadAround_Native_Report_REST_Controller( $gold, $factory );
	$p = f13_fixture(); $out = array();
	foreach ( array( 'new', 'retry', 'conflict' ) as $phase ) {
		if ( $phase === 'conflict' ) { $p['content']['description'] = 'Changed active value'; }
		$GLOBALS['transients'] = array(); $response = $controller->rest_create( new WP_REST_Request( json_encode( $p ), array( 'content-type' => 'application/json' ) ) );
		$data = $response->data; if ( isset( $data['event_id'] ) ) { $data['event_id'] = 'generated-event'; }
		$rows = $db->get_results( 'SELECT status,content_original,event_id FROM test_ba_reports', ARRAY_A ); foreach ( $rows as &$row ) { if ( $row['event_id'] ) { $row['event_id'] = 'generated-event'; } } unset( $row );
		$out[] = array( $response->status, $data, $rows, count( $GLOBALS['posts'] ) );
	}
	return $out;
}
if ( nd_flow( true ) !== nd_flow( false ) ) { throw new RuntimeException( 'V1 HTTP/persistence/Golden Path differential failed.' ); }
echo "NF_UX_02C1_V1_DIFFERENTIAL=PASS baseline={$baseline} canonical_cases={$count} service_http_flows=3\n";
rmdir( $root ); rmdir( $public );
