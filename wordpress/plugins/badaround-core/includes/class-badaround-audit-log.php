<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Audit_Log {
	public static function record( $object_type, $object_id, $action, $context = 'system', $reason = null, $request_id = null ) {
		global $wpdb;

		return $wpdb->insert(
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
