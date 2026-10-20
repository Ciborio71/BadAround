<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-badaround-public-geo-contract.php';

/** Pure projection policy and public-only integrity reader. No private storage reads. */
class BadAround_Public_Geo_Projection {
	const PROOF_META = '_ba_public_geo_proof';
	const RADII = array( 'f32-c4' => 150, 'f32-c5' => 300, 'f32-c6' => 1000, 'f32-c7' => 3000 );

	public static function radius( $precision ) {
		return self::RADII[ $precision ] ?? 750;
	}

	public static function signature( $event_id, $projection, $decision, $source ) {
		return hash_hmac( 'sha256', wp_json_encode( array( (int) $event_id, $projection, $decision, $source ) ), wp_salt( 'auth' ) );
	}

	/** Stable keyed spherical offset. Sampling occurs only in the B3 writer. */
	public static function build( $event_id, $location, $decision, $precision ) {
		$mode = $decision['mode'];
		$p = array( 'version' => BadAround_Public_Geo_Contract::VERSION, 'mode' => $mode );
		if ( in_array( $mode, array( 'territory_only', 'municipality' ), true ) ) { return $p; }
		$lat = $location['exact_lat'] ?? null; $lng = $location['exact_lng'] ?? null;
		if ( ! self::pair( $lat, $lng ) ) { return self::error(); }
		if ( 'exact_point' === $mode ) {
			return $p + array( 'lat' => (float) $lat, 'lng' => (float) $lng, 'radius_meters' => 0 );
		}
		if ( ! in_array( $mode, array( 'approximate_area', 'street', 'legacy_generalized_point' ), true ) ) { return self::error(); }
		$radius = 'street' === $mode ? self::RADII['f32-c5'] : self::radius( $precision );
		$seed = hash_hmac( 'sha256', 'ba-public-geo-v1|' . (int) $event_id, wp_salt( 'auth' ) );
		$bearing = 2 * M_PI * hexdec( substr( $seed, 0, 8 ) ) / 4294967295;
		$distance = max( 100, (int) round( $radius * 0.65 ) ) / 6378137;
		$phi = deg2rad( (float) $lat ); $lambda = deg2rad( (float) $lng );
		$phi2 = asin( sin( $phi ) * cos( $distance ) + cos( $phi ) * sin( $distance ) * cos( $bearing ) );
		$lambda2 = $lambda + atan2( sin( $bearing ) * sin( $distance ) * cos( $phi ), cos( $distance ) - sin( $phi ) * sin( $phi2 ) );
		$public_lat = round( rad2deg( $phi2 ), 6 );
		$public_lng = round( fmod( rad2deg( $lambda2 ) + 540, 360 ) - 180, 6 );
		return $p + array( 'lat' => $public_lat, 'lng' => $public_lng, 'radius_meters' => $radius );
	}

	public static function pair( $lat, $lng ) {
		return is_numeric( $lat ) && is_numeric( $lng ) && is_finite( (float) $lat ) && is_finite( (float) $lng )
			&& abs( (float) $lat ) <= 90 && abs( (float) $lng ) <= 180;
	}

	/** Checks the persisted seal, explicit semantics and public compatibility mirrors. */
	public static function verified( $event_id ) {
		$p = get_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, true );
		$d = get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true );
		$proof = get_post_meta( $event_id, self::PROOF_META, true );
		if ( ! is_array( $p ) || ! is_array( $d ) || ! is_array( $proof ) || ! isset( $proof['source'], $proof['seal'] )
			|| ! is_string( $proof['source'] ) || ! is_string( $proof['seal'] )
			|| ! hash_equals( self::signature( $event_id, $p, $d, $proof['source'] ), $proof['seal'] )
			|| ( $p['version'] ?? '' ) !== BadAround_Public_Geo_Contract::VERSION || ( $p['mode'] ?? '' ) !== ( $d['mode'] ?? null ) ) { return self::error(); }
		$lat = get_post_meta( $event_id, '_ba_public_lat', true ); $lng = get_post_meta( $event_id, '_ba_public_lng', true );
		$radius = get_post_meta( $event_id, '_ba_public_radius_m', true );
		if ( in_array( $p['mode'], array( 'territory_only', 'municipality' ), true ) ) {
			return ! isset( $p['lat'], $p['lng'] ) && '' === $lat && '' === $lng && '' === $radius ? $p : self::error();
		}
		if ( ! self::pair( $p['lat'] ?? null, $p['lng'] ?? null ) || ! self::pair( $lat, $lng )
			|| abs( (float) $lat - $p['lat'] ) > 0.0000001 || abs( (float) $lng - $p['lng'] ) > 0.0000001
			|| ! isset( $p['radius_meters'] ) || (int) $radius !== $p['radius_meters'] ) { return self::error(); }
		$allowed_radii = array( 150, 300, 750, 1000, 3000 );
		if ( 'exact_point' === $p['mode'] ) {
			return 0 === $p['radius_meters'] && true === ( $d['exact_confirmed'] ?? false ) ? $p : self::error();
		}
		return in_array( $p['mode'], array( 'approximate_area', 'street', 'legacy_generalized_point' ), true )
			&& in_array( $p['radius_meters'], $allowed_radii, true ) ? $p : self::error();
	}

	/** Consumers never query ba_reports and never generate a geometry. */
	public static function public_view( $event_id ) {
		if ( 'publish' !== get_post_status( $event_id ) || 'published' !== get_post_meta( $event_id, '_ba_moderation_status', true ) ) { return null; }
		$p = self::verified( $event_id );
		if ( is_wp_error( $p ) || ! isset( $p['lat'], $p['lng'] ) ) { return null; }
		return array( 'version' => $p['version'], 'mode' => $p['mode'], 'lat' => $p['lat'], 'lng' => $p['lng'],
			'radius_meters' => $p['radius_meters'], 'radius_m' => $p['radius_meters'], 'source' => 'event_public', 'precision' => $p['mode'] );
	}

	private static function error() { return new WP_Error( 'ba_publication_geo_integrity', 'Proiezione geografica non valida.' ); }
}
