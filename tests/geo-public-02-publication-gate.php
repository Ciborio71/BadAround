<?php
// Existing real publication/geo regression first, then new contract activation gate.
require __DIR__ . '/f1-14r-publication-contract.php';
foreach ( array( 'exact_point', 'approximate_area' ) as $request ) {
 seed( 301 );
 $GLOBALS['private_fixture'] = array( 'content_original' => json_encode( array( 'canonical' => array( 'location' => array(
  'public_location_request' => $request, 'public_location_request_version' => 'public-location-request-v1'
 ) ) ) ) );
 $before = serialize( array( $GLOBALS['meta'], $GLOBALS['posts'], $GLOBALS['object_terms'] ) );
 error_is( $svc->prepare_public_projection( 301 ), 'ba_publication_geo_contract_not_active', 'new ' . $request . ' cannot use legacy preparation' );
 error_is( $svc->validate_public_projection( 301 ), 'ba_publication_geo_contract_not_active', 'new ' . $request . ' cannot use legacy validation' );
 error_is( $svc->publish( 301 ), 'ba_publication_geo_contract_not_active', 'new ' . $request . ' cannot publish before authorized writer exists' );
 check( $before === serialize( array( $GLOBALS['meta'], $GLOBALS['posts'], $GLOBALS['object_terms'] ) ), 'blocked publication causes no public or private mutation' );
}
unset( $GLOBALS['private_fixture'] );
seed( 302 ); $GLOBALS['meta'][302][BadAround_Public_Geo_Contract::DECISION_META] = array( 'mode' => 'exact_point' );
error_is( $svc->prepare_public_projection(302), 'ba_publication_geo_contract_not_active', 'unapproved decision metadata cannot bypass B3' );
seed( 303 ); $GLOBALS['meta'][303][BadAround_Public_Geo_Contract::PROJECTION_META] = array( 'mode' => 'exact_point', 'lat' => 41.6, 'lng' => 12.5 );
error_is( $svc->prepare_public_projection(303), 'ba_publication_geo_contract_not_active', 'unapproved projection metadata cannot bypass B3' );
// Old generalized creation is persisted and reused, not re-sampled on reads/preparation.
$GLOBALS['private_fixture'] = array( 'exact_lat' => 41.669, 'exact_lng' => 12.501 );
seed( 304 ); check( true === $svc->prepare_public_projection(304), 'legacy generalized creation preserved' );
$first = array_intersect_key( $GLOBALS['meta'][304], array_flip( array('_ba_public_lat','_ba_public_lng','_ba_public_radius_m') ) );
check( count($first) === 3 && true === $svc->prepare_public_projection(304), 'legacy generalized projection persisted' );
$second = array_intersect_key( $GLOBALS['meta'][304], array_flip( array('_ba_public_lat','_ba_public_lng','_ba_public_radius_m') ) );
check( $first === $second, 'persisted jitter center remains stable' );
check( $GLOBALS['private_fixture']['exact_lat'] === 41.669 && $GLOBALS['private_fixture']['exact_lng'] === 12.501, 'generalization preserves private truth' );
echo "GEO_PUBLIC_02_PUBLICATION_GATE=PASS\n";
