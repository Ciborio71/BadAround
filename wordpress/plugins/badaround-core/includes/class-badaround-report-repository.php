<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Report_Repository {
	public function create_intake_record( $form_id, $entry_id ) {
		global $wpdb;

		$now    = current_time( 'mysql', true );
		$result = $wpdb->insert(
			$wpdb->prefix . 'ba_reports',
			array(
				'report_type'     => 'initial',
				'source_type'     => 'wpforms',
				'source_form_id'  => absint( $form_id ),
				'source_entry_id' => absint( $entry_id ),
				'user_id'         => get_current_user_id() ?: null,
				'status'          => 'received',
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false !== $result ) {
			return (int) $wpdb->insert_id;
		}

		return $this->find_by_source( $form_id, $entry_id );
	}

	public function persist_normalized_private_data( $report_id, $normalized ) {
		global $wpdb;

		$reporter = isset( $normalized['reporter'] ) ? $normalized['reporter'] : array();
		$location = isset( $normalized['location'] ) ? $normalized['location'] : array();
		$vehicle  = isset( $normalized['vehicle'] ) ? $normalized['vehicle'] : array();
		$consents = isset( $normalized['consents'] ) ? $normalized['consents'] : array();
		$payload  = wp_json_encode( $normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		$data = array(
			'author_name'          => isset( $reporter['first_name'] ) ? $reporter['first_name'] : null,
			'author_surname'       => isset( $reporter['last_name'] ) ? $reporter['last_name'] : null,
			'author_email'         => isset( $reporter['email'] ) ? $reporter['email'] : null,
			'author_phone'         => isset( $reporter['phone'] ) ? $reporter['phone'] : null,
			'contact_preference'   => isset( $reporter['contact_choice'] ) ? 'f78-c' . absint( $reporter['contact_choice'] ) : null,
			'content_original'     => $payload,
			'exact_address'        => isset( $location['exact_address'] ) ? $location['exact_address'] : null,
			'exact_lat'            => isset( $location['exact_lat'] ) && is_numeric( $location['exact_lat'] ) ? (float) $location['exact_lat'] : null,
			'exact_lng'            => isset( $location['exact_lng'] ) && is_numeric( $location['exact_lng'] ) ? (float) $location['exact_lng'] : null,
			'full_plate'           => isset( $vehicle['plate_raw'] ) ? $vehicle['plate_raw'] : null,
			'consent_privacy'      => ! empty( $consents['privacy'] ) ? 1 : 0,
			'consent_publication'  => ! empty( $consents['publishing'] ) ? 1 : 0,
			'consent_contact'      => isset( $reporter['contact_choice'] ) && 10 !== absint( $reporter['contact_choice'] ) ? 1 : 0,
			'consent_version'      => isset( $consents['version'] ) ? $consents['version'] : null,
			'consented_at'         => current_time( 'mysql', true ),
			'payload_hash'         => hash( 'sha256', (string) $payload ),
			'ip_hash'              => $this->request_ip_hash(),
			'updated_at'           => current_time( 'mysql', true ),
		);

		return false !== $wpdb->update(
			$wpdb->prefix . 'ba_reports',
			$data,
			array( 'id' => absint( $report_id ) )
		);
	}

	public function link_event( $report_id, $event_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'ba_reports',
			array(
				'event_id'   => absint( $event_id ),
				'status'     => 'linked',
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $report_id ) ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	public function event_id_for_report( $report_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ba_reports';
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT event_id FROM {$table} WHERE id = %d LIMIT 1", absint( $report_id ) )
		);
	}

	public function find_by_source( $form_id, $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ba_reports';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_type = %s AND source_form_id = %d AND source_entry_id = %d LIMIT 1",
				'wpforms', absint( $form_id ), absint( $entry_id )
			)
		);
	}

	public function acquire_source_lock( $form_id, $entry_id, $timeout = 5 ) {
		global $wpdb;
		$key = sprintf( 'ba:wpforms:%d:%d', absint( $form_id ), absint( $entry_id ) );
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, absint( $timeout ) ) );
	}

	public function release_source_lock( $form_id, $entry_id ) {
		global $wpdb;
		$key = sprintf( 'ba:wpforms:%d:%d', absint( $form_id ), absint( $entry_id ) );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	}

	private function request_ip_hash() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return null;
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
