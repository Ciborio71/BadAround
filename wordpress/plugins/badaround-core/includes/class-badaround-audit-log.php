<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-badaround-public-geo-contract.php';

class BadAround_Audit_Log {
	public static function record( $object_type, $object_id, $action, $context = 'system', $reason = null, $request_id = null ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ba_audit_log',
			array(
				'actor_user_id' => get_current_user_id() ?: null,
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => absint( $object_id ),
				'action'        => sanitize_key( $action ),
				'reason'        => $reason ? sanitize_textarea_field( $reason ) : null,
				'context'       => sanitize_key( $context ),
				'request_id'    => $request_id ? sanitize_text_field( $request_id ) : wp_generate_uuid4(),
				'ip_hash'       => self::request_ip_hash(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log(
				sprintf(
					'BadAround audit insert failed: %s',
					sanitize_text_field( $wpdb->last_error )
				)
			);
		}

		return $result;
	}

	public static function transition( $object_type, $object_id, $action, $old_value, $new_value, $context = 'moderation', $reason = null, $request_id = null ) {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ba_audit_log',
			array(
				'actor_user_id' => get_current_user_id() ?: null,
				'object_type'   => sanitize_key( $object_type ),
				'object_id'     => absint( $object_id ),
				'action'        => sanitize_key( $action ),
				'old_value'     => wp_json_encode( array( 'moderation_status' => sanitize_key( $old_value ) ) ),
				'new_value'     => wp_json_encode( array( 'moderation_status' => sanitize_key( $new_value ) ) ),
				'reason'        => $reason ? sanitize_textarea_field( $reason ) : null,
				'context'       => sanitize_key( $context ),
				'request_id'    => $request_id ? sanitize_text_field( $request_id ) : wp_generate_uuid4(),
				'ip_hash'       => self::request_ip_hash(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			error_log( sprintf( 'BadAround audit transition insert failed: %s', sanitize_text_field( $wpdb->last_error ) ) );
		}

		return $result;
	}

	/** Coordinate-free history; committed together with the protected metadata. */
	public static function geographic_decision( $event_id, $location, $before, $after ) {
		global $wpdb;
		$new_value = BadAround_Public_Geo_Contract::audit_descriptor( $location, $after );
		if ( is_wp_error( $new_value ) ) { return false; }
		$previous = null;
		if ( is_array( $before ) ) {
			$previous = is_wp_error( BadAround_Public_Geo_Contract::validate_decision( $location, $before ) )
				? array( 'invalid_previous_decision' => true )
				: array_intersect_key( $before, array_flip( array( 'version', 'mode', 'reason_code', 'policy_version', 'decided_at', 'decided_by', 'exact_confirmed' ) ) );
		}
		return $wpdb->insert( $wpdb->prefix . 'ba_audit_log', array(
			'actor_user_id' => get_current_user_id(), 'object_type' => 'event', 'object_id' => absint( $event_id ),
			'action' => 'geo_location_decision_changed', 'context' => 'geo_moderation',
			'old_value' => wp_json_encode( $previous ), 'new_value' => wp_json_encode( $new_value ),
			'reason' => $after['reason_code'] ?: null, 'request_id' => wp_generate_uuid4(),
			'ip_hash' => self::request_ip_hash(), 'created_at' => current_time( 'mysql', true ),
		), array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	/** B3 projection publication, without private or public coordinate duplication. */
	public static function geographic_publication( $event_id, $context, $projection ) {
		global $wpdb;
		$value = array( 'user_request' => $context['location']['public_location_request'] ?? 'legacy_unspecified',
			'moderator_decision' => $context['decision']['mode'], 'public_projection_mode' => $projection['mode'],
			'radius_meters' => $projection['radius_meters'] ?? null, 'policy_version' => $projection['version'], 'publication_event' => 'event_published' );
		if ( BadAround_Public_Geo_Contract::is_user_authority( $context['decision'] ) ) {
			unset( $value['moderator_decision'] );
			$value['geo_authority'] = BadAround_Public_Geo_Contract::USER_AUTHORITY;
		}
		return $wpdb->insert( $wpdb->prefix . 'ba_audit_log', array(
			'actor_user_id' => get_current_user_id(), 'object_type' => 'event', 'object_id' => absint( $event_id ),
			'action' => 'public_geo_projection_published', 'context' => 'publication',
			'new_value' => wp_json_encode( $value ), 'request_id' => wp_generate_uuid4(),
			'ip_hash' => self::request_ip_hash(), 'created_at' => current_time( 'mysql', true ),
		), array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function technical_error( $object_type, $object_id, $action, $code, $request_id = null ) {
		$code = sanitize_key( $code );
		self::record( $object_type, $object_id, $action, 'wpforms', $code, $request_id );
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( 'BadAround intake error [%s] object=%s:%d request=%s', $code, sanitize_key( $object_type ), absint( $object_id ), sanitize_text_field( (string) $request_id ) ) );
		}
	}

	private static function request_ip_hash() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return null;
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
