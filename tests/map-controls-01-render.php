<?php
// Actual public templates; isolated fake WordPress data. No HTTP/DB or staging writes.
$context = $argv[1] ?? 'home';
$radius = (int) ( $argv[2] ?? 100 );
if ( 'detail' === $context ) {
	ob_start(); require __DIR__ . '/geo-area-01-consumers.php'; ob_end_clean();
	$radius = (int) ( $argv[2] ?? 100 );
	replay( 4500, $radius ? 'approximate_area' : 'exact_point', true, 41.66, 12.43, $radius );
	$request = [ 'mode' => $radius ? 'approximate_area' : 'exact_point' ];
	if ( $radius ) $request['radius_m'] = $radius;
	$authority = BadAround_Public_Geo_Contract::user_authority( [ 'public_geo_governance' => 'user-public-location/v1', 'canonical' => [ 'location' => [ 'public_location_request_version' => 'public-location-request-v2', 'public_location_request' => $request ] ] ] );
	$projection = get_post_meta( 4500, '_ba_public_geo' );
	$proof = get_post_meta( 4500, '_ba_public_geo_proof' );
	$proof['user_authority'] = $authority;
	$proof['seal'] = BadAround_Public_Geo_Projection::signature( 4500, $projection, $authority, $proof['source'] );
	$GLOBALS['meta'][4500]['_ba_public_geo_proof'] = $proof;
	ob_start(); $body = render_consumer( 4500 ); ob_end_clean();
} else {
	define( 'ABSPATH', __DIR__ );
	function get_header() {}
	function get_footer() {}
	function home_url( $p = '' ) { return 'https://localhost' . $p; }
	function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
	function esc_html( $s ) { return esc_url( $s ); }
	function esc_attr( $s ) { return esc_url( $s ); }
	function add_query_arg( $k, $v, $u ) { return $u . '?' . $k . '=' . $v; }
	function get_template_part( $part ) { require dirname( __DIR__ ) . '/wordpress/themes/badaround-child/' . $part . '.php'; }
	ob_start(); require dirname( __DIR__ ) . '/wordpress/themes/badaround-child/' . ( 'home' === $context ? 'front-page.php' : 'page-mappa.php' ); $body = ob_get_clean();
}
echo '<!doctype html><html lang="it"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MAP-CONTROLS-01 offline provider contract</title>';
foreach ( [ 'base', 'components', 'home', 'map', 'pages', 'map-navigation' ] as $css ) echo '<link rel="stylesheet" href="/wordpress/themes/badaround-child/assets/css/' . $css . '.css">';
echo '<body><p id="provider-note">CI offline — API Google simulate; template e codice applicativo effettivi.</p>' . $body;
echo '<a id="after-map" href="#provider-note">Fine mappa</a><script src="/wordpress/themes/badaround-child/assets/js/map-navigation.js"></script><script src="/wordpress/themes/badaround-child/assets/js/map.js"></script></body></html>';
