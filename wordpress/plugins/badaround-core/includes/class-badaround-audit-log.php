<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Audit_Log {
	public static function record( $object_type, $object_id, $action, $context = 'system', $reason = null ) {
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
				'request_id'    => wp_generate_uuid4(),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}
