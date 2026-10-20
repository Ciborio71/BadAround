<?php
// Real normalization/intake/repository, synthetic in-memory fixtures only.
require __DIR__ . '/native-report-f13.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-repository.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-public-geo-contract.php';
function apply_filters( $name, $value ) { return $GLOBALS['geo_exact_review'] ?? $value; }
function geo_assert( $ok, $message ) { f13_assert( $ok, 'GEO-PUBLIC-02 ' . $message ); }
function geo_error( $result, $suffix ) { return is_wp_error( $result ) && 'ba_public_geo_' . $suffix === $result->get_error_code(); }
function geo_request( $mode ) {
 $r = f13_fixture();
 $r['location']['public_location_request'] = $mode;
 $r['location']['public_location_request_version'] = BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION;
 return $r;
}
function geo_decision( $mode, $reason = '' ) {
 return array( 'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => $mode, 'reason' => $reason,
  'policy_version' => BadAround_Public_Geo_Contract::VERSION, 'decided_at' => '2026-10-20T13:00:00Z', 'decided_by' => 42 );
}
geo_assert( BadAround_Report_Schema::PUBLIC_LOCATION_REQUEST_VERSION === BadAround_Public_Geo_Contract::REQUEST_VERSION, 'request version synchronized' );
$old = $normalizer->normalize( f13_fixture() );
geo_assert( ! isset( $old['location']['public_location_request'] ) && true === $validator->validate( $old ), 'legacy stays absent and valid' );
foreach ( array( 'exact_point', 'approximate_area' ) as $mode ) {
 $input = geo_request( $mode );
 geo_assert( true === $validator->validate_raw_contract( $input ), $mode . ' raw accepted' );
 $canonical = $normalizer->normalize( $input );
 geo_assert( true === $validator->validate( $canonical ), $mode . ' canonical accepted' );
 $repo = new F13_Fake_Repository(); $service = f13_service( $repo );
 $first = $service->intake( $input ); $again = $service->intake( $input );
 geo_assert( 'success' === $first['status'] && 'success' === $again['status'] && 1 === $repo->persist_count, $mode . ' intake and retry' );
 geo_assert( $repo->stored['location']['exact_lat'] === $canonical['location']['exact_lat'] && $repo->stored['location']['exact_lng'] === $canonical['location']['exact_lng'], $mode . ' preserves final private pair' );
 $changed = $input; $changed['location']['public_location_request'] = 'exact_point' === $mode ? 'approximate_area' : 'exact_point';
 geo_assert( 'success' !== $service->intake( $changed )['status'], 'same UUID cannot change request' );
 $GLOBALS['wpdb'] = new class {
  public $prefix = 'wp_', $row;
  public function update( $table, $row ) { $this->row = $row; return 1; }
  public function prepare( $sql ) { return $sql; }
  public function get_var( $sql ) { return $this->row['content_original']; }
 };
 $repository = new BadAround_Report_Repository();
 geo_assert( true === $repository->persist_canonical_private_data( 77, $canonical ), 'private repository accepts ' . $mode );
 $stored = json_decode( $GLOBALS['wpdb']->row['content_original'], true );
 geo_assert( $stored['canonical'] === $canonical && $repository->native_payload_matches_report( 77, $canonical ), 'private reread includes request/version ' . $mode );
 geo_assert( ! isset( $GLOBALS['wpdb']->row['public_lat'], $GLOBALS['wpdb']->row['public_lng'] ), 'no public coordinates from request ' . $mode );
}
foreach ( array( 'point', 'street', 'EXACT_POINT', 'exact_point!', '', null, array(), true, 1 ) as $bad ) {
 $input = geo_request( $bad );
 geo_assert( is_wp_error( $validator->validate_raw_contract( $input ) ) && is_wp_error( $validator->validate_and_normalize( $input ) ), 'invalid request rejected without normalization escalation' );
}
foreach ( array( 'public_location_request', 'public_location_request_version' ) as $missing ) {
 $input = geo_request( 'exact_point' ); unset( $input['location'][ $missing ] );
 geo_assert( is_wp_error( $validator->validate_raw_contract( $input ) ), 'orphan request/version rejected' );
}
$input = geo_request( 'exact_point' ); $input['location']['public_location_request_version'] = 'future-v2';
geo_assert( is_wp_error( $validator->validate_raw_contract( $input ) ), 'unknown version rejected' );
$exact = geo_request( 'exact_point' )['location']; $area = geo_request( 'approximate_area' )['location'];
foreach ( array( 'exact_point', 'approximate_area', 'street', 'municipality', 'territory_only' ) as $mode ) {
 geo_assert( true === BadAround_Public_Geo_Contract::validate_decision( $exact, geo_decision( $mode, 'Privacy review' ) ), 'exact can reduce to ' . $mode );
}
foreach ( array( 'exact_point', 'street', 'legacy_generalized_point' ) as $mode ) {
 geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_decision( $area, geo_decision( $mode, 'Not allowed' ) ), 'precision_not_authorized' ), 'approximate cannot upgrade to ' . $mode );
}
foreach ( array( 'approximate_area', 'municipality', 'territory_only' ) as $mode ) {
 geo_assert( true === BadAround_Public_Geo_Contract::validate_decision( $area, geo_decision( $mode, 'Privacy review' ) ), 'approximate may use ' . $mode );
}
geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_decision( $exact, geo_decision( 'municipality' ) ), 'reduction_reason_required' ), 'reduction requires reason' );
geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_decision( $old['location'], geo_decision( 'exact_point' ) ), 'precision_not_authorized' ), 'legacy point is never exact' );
$decision = geo_decision( 'exact_point' );
foreach ( array( 'version' => 'v2', 'decided_by' => 0, 'decided_at' => '2026-02-30T13:00:00Z' ) as $key => $bad ) {
 $d = $decision; $d[ $key ] = $bad;
 geo_assert( is_wp_error( BadAround_Public_Geo_Contract::validate_decision( $exact, $d ) ), 'invalid audit rejected ' . $key );
}
$projection = array( 'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => 'exact_point', 'lat' => $exact['exact_lat'], 'lng' => $exact['exact_lng'] );
geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_projection( $exact, $decision, $projection, 'rest', true ), 'b3_required' ), 'exact unavailable outside B3' );
geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_projection( $exact, $decision, $projection, 'b3' ), 'exact_not_authorized' ), 'exact safety denies by default' );
geo_assert( false === BadAround_Public_Geo_Contract::exact_safety_allowed( 1, 1, $decision ), 'sensitive-location hook defaults false' );
$GLOBALS['geo_exact_review'] = true;
geo_assert( true === BadAround_Public_Geo_Contract::exact_safety_allowed( 1, 1, $decision ), 'explicit server safety hook supported' );
geo_assert( true === BadAround_Public_Geo_Contract::validate_projection( $exact, $decision, $projection, 'b3', true ), 'authorized exact proposal validates without publishing' );
geo_assert( is_wp_error( BadAround_Public_Geo_Contract::validate_projection( $area, $decision, $projection, 'b3', true ) ), 'exact requires exact user request' );
$with_address = $projection; $with_address['exact_address'] = $exact['exact_address'];
geo_assert( geo_error( BadAround_Public_Geo_Contract::validate_projection( $exact, $decision, $with_address, 'b3', true ), 'projection_invalid' ), 'exact coordinates never authorize textual address' );
$generalized = array( 'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => 'approximate_area', 'lat' => 41.675, 'lng' => 12.507, 'radius_meters' => 1000 );
geo_assert( true === BadAround_Public_Geo_Contract::validate_projection( $area, geo_decision( 'approximate_area' ), $generalized, 'b3' ), 'separate generalized center validates' );
$leak = $generalized; $leak['lat'] = $exact['exact_lat']; $leak['lng'] = $exact['exact_lng'];
geo_assert( is_wp_error( BadAround_Public_Geo_Contract::validate_projection( $area, geo_decision( 'approximate_area' ), $leak, 'b3' ) ), 'generalized cannot expose private coordinates' );
$territory = array( 'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => 'territory_only' );
geo_assert( true === BadAround_Public_Geo_Contract::validate_projection( $exact, geo_decision( 'territory_only', 'Sensitive place' ), $territory, 'b3' ), 'territory-only without coordinates' );
$territory['lat'] = 41.6; $territory['lng'] = 12.5;
geo_assert( is_wp_error( BadAround_Public_Geo_Contract::validate_projection( $exact, geo_decision( 'territory_only', 'Sensitive place' ), $territory, 'b3' ) ), 'territory-only forbids invented marker' );
$audit = BadAround_Public_Geo_Contract::audit_descriptor( $exact, geo_decision( 'approximate_area', 'Sensitive place' ) );
geo_assert( true === $audit['precision_reduced'] && ! preg_match( '/exact_lat|exact_lng|exact_address|41\\.669/', json_encode( $audit ) ), 'audit describes reduction without copying private location' );
echo "GEO_PUBLIC_02_CONTRACT=PASS\n";
