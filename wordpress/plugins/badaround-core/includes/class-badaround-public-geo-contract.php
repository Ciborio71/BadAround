<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Versioned, side-effect-free geo contract. Never reads public/private storage,
 * publishes, generates coordinates, or grants a capability to its caller.
 * GEO-PUBLIC-04/05 will supply authorized decisions and persist projections.
 */
class BadAround_Public_Geo_Contract {
	const VERSION = 'public-location/v1';
	const REQUEST_VERSION = 'public-location-request-v1';
	const DECISION_META = '_ba_public_location_decision';
	const PROJECTION_META = '_ba_public_geo';

	public static function definition() {
		return array(
			'version' => self::VERSION,
			'request_version' => self::REQUEST_VERSION,
			'default_new_native_request' => 'exact_point',
			'modes' => array( 'exact_point', 'approximate_area', 'street', 'municipality', 'territory_only', 'legacy_generalized_point' ),
			'decision_storage' => self::DECISION_META,
			'projection_storage' => self::PROJECTION_META,
			'public_textual_address' => 'independently-moderated-never-derived-from-coordinates',
			'projection_generation' => 'B3-only-persist-once-never-randomize-on-read',
			'exact_safety_hook' => 'badaround_public_geo_exact_allowed-default-false',
			'activation' => 'blocked-until-GEO-PUBLIC-05',
		);
	}

	public static function validate_request( $location ) {
		if ( ! is_array( $location ) ) { return self::error( 'request_invalid' ); }
		$has_request = array_key_exists( 'public_location_request', $location );
		$has_version = array_key_exists( 'public_location_request_version', $location );
		if ( ! $has_request && ! $has_version ) { return true; }
		if ( ! $has_request || ! $has_version || self::REQUEST_VERSION !== $location['public_location_request_version']
			|| ! in_array( $location['public_location_request'], array( 'exact_point', 'approximate_area' ), true ) ) {
			return self::error( 'request_invalid' );
		}
		return true;
	}

	public static function allowed_decisions( $request ) {
		if ( 'exact_point' === $request ) {
			return array( 'exact_point', 'approximate_area', 'street', 'municipality', 'territory_only' );
		}
		if ( 'approximate_area' === $request ) {
			return array( 'approximate_area', 'municipality', 'territory_only' );
		}
		if ( null === $request ) {
			return array( 'legacy_generalized_point', 'approximate_area', 'street', 'municipality', 'territory_only' );
		}
		return array();
	}

	public static function validate_decision( $location, $decision ) {
		$valid = self::validate_request( $location );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$keys = array( 'version', 'mode', 'reason', 'policy_version', 'decided_at', 'decided_by' );
		$allowed = array_merge( $keys, array( 'reason_code', 'exact_confirmed' ) );
		if ( ! is_array( $decision ) || array_diff( $keys, array_keys( $decision ) ) || array_diff( array_keys( $decision ), $allowed ) ) {
			return self::error( 'decision_invalid' );
		}
		if ( ( array_key_exists( 'reason_code', $decision ) && ( ! is_string( $decision['reason_code'] ) || ( '' !== $decision['reason_code'] && ! array_key_exists( $decision['reason_code'], self::reason_codes() ) ) ) )
			|| ( array_key_exists( 'exact_confirmed', $decision ) && ! is_bool( $decision['exact_confirmed'] ) ) ) {
			return self::error( 'decision_audit_invalid' );
		}
		$request = isset( $location['public_location_request'] ) ? $location['public_location_request'] : null;
		if ( self::VERSION !== $decision['version'] || self::VERSION !== $decision['policy_version']
			|| ! in_array( $decision['mode'], self::allowed_decisions( $request ), true ) ) {
			return self::error( 'precision_not_authorized' );
		}
		if ( ! is_int( $decision['decided_by'] ) || $decision['decided_by'] < 1
			|| ! is_string( $decision['reason'] ) || strlen( $decision['reason'] ) > 1000
			|| ! is_string( $decision['decided_at'] ) ) {
			return self::error( 'decision_audit_invalid' );
		}
		$at = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s\Z', $decision['decided_at'], new DateTimeZone( 'UTC' ) );
		if ( ! $at || $at->format( 'Y-m-d\TH:i:s\Z' ) !== $decision['decided_at'] ) {
			return self::error( 'decision_audit_invalid' );
		}
		if ( null !== $request && $request !== $decision['mode'] && '' === trim( $decision['reason'] ) ) {
			return self::error( 'reduction_reason_required' );
		}
		return true;
	}

	/** Validate a proposed projection; returning true never publishes it. */
	public static function validate_projection( $location, $decision, $projection, $context, $exact_safety_allowed = false ) {
		$valid = self::validate_decision( $location, $decision );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( 'b3' !== $context ) { return self::error( 'b3_required' ); }
		$allowed = array( 'version', 'mode', 'lat', 'lng', 'radius_meters' );
		if ( ! is_array( $projection ) || array_diff( array_keys( $projection ), $allowed )
			|| ! isset( $projection['version'], $projection['mode'] )
			|| self::VERSION !== $projection['version'] || $decision['mode'] !== $projection['mode'] ) {
			return self::error( 'projection_invalid' );
		}
		$lat = array_key_exists( 'lat', $projection );
		$lng = array_key_exists( 'lng', $projection );
		$radius = isset( $projection['radius_meters'] ) ? $projection['radius_meters'] : 0;
		if ( ! is_int( $radius ) || $radius < 0 || $lat !== $lng ) { return self::error( 'projection_invalid' ); }
		if ( 'territory_only' === $projection['mode'] ) {
			return ! $lat && 0 === $radius ? true : self::error( 'territory_coordinates_forbidden' );
		}
		if ( ! $lat && 'municipality' === $projection['mode'] && 0 === $radius ) { return true; }
		if ( ! $lat || ! self::coordinate_pair( $projection['lat'], $projection['lng'] ) ) {
			return self::error( 'projection_coordinates_invalid' );
		}
		$private_valid = isset( $location['exact_lat'], $location['exact_lng'] )
			&& self::coordinate_pair( $location['exact_lat'], $location['exact_lng'] );
		$equals_private = $private_valid && abs( (float) $projection['lat'] - (float) $location['exact_lat'] ) < 0.00001
			&& abs( (float) $projection['lng'] - (float) $location['exact_lng'] ) < 0.00001;
		if ( 'exact_point' === $projection['mode'] ) {
			if ( true !== $exact_safety_allowed || ! $private_valid || ! $equals_private || 0 !== $radius
				|| abs( (float) $projection['lat'] - (float) $location['exact_lat'] ) > 0.0000001
				|| abs( (float) $projection['lng'] - (float) $location['exact_lng'] ) > 0.0000001 ) {
				return self::error( 'exact_not_authorized' );
			}
			return true;
		}
		if ( $radius < 100 || $equals_private ) { return self::error( 'generalized_location_too_precise' ); }
		return true;
	}

	/** Future B3 hook: absence of an explicit safety decision always denies exact. */
	public static function exact_safety_allowed( $event_id, $report_id, $decision ) {
		return true === apply_filters( 'badaround_public_geo_exact_allowed', false, $event_id, $report_id, $decision );
	}

	/** Audit descriptor only; callers record through the existing audit service. */
	public static function audit_descriptor( $location, $decision ) {
		$valid = self::validate_decision( $location, $decision );
		if ( is_wp_error( $valid ) ) { return $valid; }
		$request = isset( $location['public_location_request'] ) ? $location['public_location_request'] : 'legacy_unspecified';
		return array(
			'request' => $request, 'request_version' => isset( $location['public_location_request_version'] ) ? $location['public_location_request_version'] : null,
			'decision' => $decision['mode'], 'precision_reduced' => 'legacy_unspecified' !== $request && $request !== $decision['mode'],
			'reason' => $decision['reason'], 'policy_version' => $decision['policy_version'],
			'reason_code' => $decision['reason_code'] ?? '', 'exact_confirmed' => $decision['exact_confirmed'] ?? false,
			'decided_at' => $decision['decided_at'], 'decided_by' => $decision['decided_by'],
		);
	}

	public static function reason_codes() {
		return array(
			'privacy' => 'Tutela della privacy', 'private_home' => 'Abitazione privata',
			'sensitive_place' => 'Luogo sensibile', 'vulnerable_person' => 'Tutela di persona vulnerabile',
			'unreliable_coordinates' => 'Coordinate non sufficientemente affidabili', 'other' => 'Altro',
		);
	}

	private static function coordinate_pair( $lat, $lng ) {
		return is_numeric( $lat ) && is_numeric( $lng ) && is_finite( (float) $lat ) && is_finite( (float) $lng )
			&& (float) $lat >= -90 && (float) $lat <= 90 && (float) $lng >= -180 && (float) $lng <= 180;
	}

	private static function error( $suffix ) {
		return new WP_Error( 'ba_public_geo_' . $suffix, __( 'La decisione geografica pubblica non soddisfa il contratto di sicurezza.', 'badaround-core' ) );
	}
}
