<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Report_Repository {
	public function create_intake_record( $form_id, $entry_id ) {
		return $this->create_source_record( 'wpforms', absint( $form_id ), absint( $entry_id ) );
	}

	public function create_native_intake_record( $submission_id ) {
		$parts = $this->native_source_parts( $submission_id );
		if ( is_wp_error( $parts ) ) {
			return $parts;
		}
		return $this->create_source_record( 'native', $parts['form_id'], $parts['entry_id'] );
	}

	public function create_source_record( $source_type, $source_form_id, $source_entry_id ) {
		global $wpdb;

		$source_type = sanitize_key( $source_type );
		$now         = current_time( 'mysql', true );
		$result      = $wpdb->insert(
			$wpdb->prefix . 'ba_reports',
			array(
				'report_type'     => 'initial',
				'source_type'     => $source_type,
				'source_form_id'  => absint( $source_form_id ),
				'source_entry_id' => absint( $source_entry_id ),
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

		return $this->find_by_source_identity( $source_type, $source_form_id, $source_entry_id );
	}

	/**
	 * Legacy persistence method kept unchanged for historical WPForms callers.
	 */
	public function persist_normalized_private_data( $report_id, $normalized ) {
		$reporter = isset( $normalized['reporter'] ) ? $normalized['reporter'] : array();
		$location = isset( $normalized['location'] ) ? $normalized['location'] : array();
		$vehicle  = isset( $normalized['vehicle'] ) ? $normalized['vehicle'] : array();
		$consents = isset( $normalized['consents'] ) ? $normalized['consents'] : array();

		$canonical = array(
			'reporter' => array(
				'first_name'         => isset( $reporter['first_name'] ) ? $reporter['first_name'] : '',
				'last_name'          => isset( $reporter['last_name'] ) ? $reporter['last_name'] : '',
				'email'              => isset( $reporter['email'] ) ? $reporter['email'] : '',
				'phone'              => isset( $reporter['phone'] ) ? $reporter['phone'] : '',
				'contact_preference' => isset( $reporter['contact_choice'] ) ? $this->legacy_contact_preference( $reporter['contact_choice'] ) : 'none',
			),
			'location' => array(
				'exact_address' => isset( $location['exact_address'] ) ? $location['exact_address'] : '',
				'exact_lat'     => isset( $location['exact_lat'] ) ? $location['exact_lat'] : null,
				'exact_lng'     => isset( $location['exact_lng'] ) ? $location['exact_lng'] : null,
			),
			'vehicle' => array(
				'plate_raw' => isset( $vehicle['plate_raw'] ) ? $vehicle['plate_raw'] : '',
			),
			'consents' => array(
				'privacy'          => ! empty( $consents['privacy'] ),
				'publication_rules'=> ! empty( $consents['publishing'] ),
				'version'          => isset( $consents['version'] ) ? $consents['version'] : '',
			),
		);

		return $this->persist_canonical_private_data( $report_id, $canonical, $normalized );
	}

	public function persist_canonical_private_data( $report_id, $canonical, $content_payload = null ) {
		global $wpdb;

		$canonical = is_array( $canonical ) ? $canonical : array();
		$reporter  = isset( $canonical['reporter'] ) && is_array( $canonical['reporter'] ) ? $canonical['reporter'] : array();
		$location  = isset( $canonical['location'] ) && is_array( $canonical['location'] ) ? $canonical['location'] : array();
		$vehicle   = isset( $canonical['vehicle'] ) && is_array( $canonical['vehicle'] ) ? $canonical['vehicle'] : array();
		$consents  = isset( $canonical['consents'] ) && is_array( $canonical['consents'] ) ? $canonical['consents'] : array();

		if ( null === $content_payload ) {
			$content_payload = $this->canonical_storage_envelope( $canonical );
		}

		$payload = wp_json_encode( $content_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$contact = isset( $reporter['contact_preference'] ) ? sanitize_key( $reporter['contact_preference'] ) : 'none';

		$data = array(
			'author_name'          => isset( $reporter['first_name'] ) ? sanitize_text_field( $reporter['first_name'] ) : null,
			'author_surname'       => isset( $reporter['last_name'] ) ? sanitize_text_field( $reporter['last_name'] ) : null,
			'author_email'         => isset( $reporter['email'] ) ? sanitize_email( $reporter['email'] ) : null,
			'author_phone'         => isset( $reporter['phone'] ) ? sanitize_text_field( $reporter['phone'] ) : null,
			'contact_preference'   => $this->canonical_contact_storage_code( $contact ),
			'content_original'     => $payload,
			'exact_address'        => isset( $location['exact_address'] ) ? sanitize_textarea_field( $location['exact_address'] ) : null,
			'exact_lat'            => isset( $location['exact_lat'] ) && is_numeric( $location['exact_lat'] ) ? (float) $location['exact_lat'] : null,
			'exact_lng'            => isset( $location['exact_lng'] ) && is_numeric( $location['exact_lng'] ) ? (float) $location['exact_lng'] : null,
			'full_plate'           => isset( $vehicle['plate_raw'] ) ? sanitize_text_field( $vehicle['plate_raw'] ) : null,
			'consent_privacy'      => ! empty( $consents['privacy'] ) ? 1 : 0,
			'consent_publication'  => ! empty( $consents['publication_rules'] ) ? 1 : 0,
			'consent_contact'      => 'none' !== $contact ? 1 : 0,
			'consent_version'      => isset( $consents['version'] ) ? sanitize_text_field( $consents['version'] ) : null,
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
		return $this->find_by_source_identity( 'wpforms', absint( $form_id ), absint( $entry_id ) );
	}

	public function find_by_native_submission( $submission_id ) {
		$parts = $this->native_source_parts( $submission_id );
		if ( is_wp_error( $parts ) ) {
			return 0;
		}
		return $this->find_by_source_identity( 'native', $parts['form_id'], $parts['entry_id'] );
	}

	public function find_by_source_identity( $source_type, $source_form_id, $source_entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'ba_reports';
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_type = %s AND source_form_id = %d AND source_entry_id = %d LIMIT 1",
				sanitize_key( $source_type ),
				absint( $source_form_id ),
				absint( $source_entry_id )
			)
		);
	}

	public function acquire_source_lock( $form_id, $entry_id, $timeout = 5 ) {
		return $this->acquire_identity_lock( sprintf( 'wpforms:%d:%d', absint( $form_id ), absint( $entry_id ) ), $timeout );
	}

	public function release_source_lock( $form_id, $entry_id ) {
		$this->release_identity_lock( sprintf( 'wpforms:%d:%d', absint( $form_id ), absint( $entry_id ) ) );
	}

	public function acquire_native_lock( $submission_id, $timeout = 5 ) {
		return $this->acquire_identity_lock( 'native:' . strtolower( trim( (string) $submission_id ) ), $timeout );
	}

	public function release_native_lock( $submission_id ) {
		$this->release_identity_lock( 'native:' . strtolower( trim( (string) $submission_id ) ) );
	}

	public function native_source_identity( $submission_id ) {
		return $this->native_source_parts( $submission_id );
	}

	private function native_source_parts( $submission_id ) {
		$submission_id = strtolower( trim( (string) $submission_id ) );
		if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $submission_id ) ) {
			return new WP_Error( 'ba_report_invalid_uuid', __( 'Identificatore submission non valido.', 'badaround-core' ) );
		}

		$digest = hash( 'sha256', $submission_id );
		/*
		 * The existing schema remains 1.8.0. Native UUID identity is projected
		 * into the already unique (source_type, source_form_id, source_entry_id)
		 * key using 88 deterministic digest bits, while the full UUID remains
		 * in the canonical private payload and in the named lock.
		 */
		$form_id  = hexdec( substr( $digest, 0, 7 ) );
		$entry_id = hexdec( substr( $digest, 7, 15 ) );

		return array(
			'form_id'  => (int) $form_id,
			'entry_id' => (int) $entry_id,
		);
	}

	private function acquire_identity_lock( $identity, $timeout ) {
		global $wpdb;
		$key = 'ba:' . hash( 'sha256', (string) $identity );
		return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, absint( $timeout ) ) );
	}

	private function release_identity_lock( $identity ) {
		global $wpdb;
		$key = 'ba:' . hash( 'sha256', (string) $identity );
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
	}

	private function canonical_storage_envelope( $canonical ) {
		$description = '';
		if ( isset( $canonical['content']['description'] ) ) {
			$description = sanitize_textarea_field( $canonical['content']['description'] );
		}

		return array(
			'schema_version' => BadAround_Report_Schema::VERSION,
			'submission_id'  => isset( $canonical['submission_id'] ) ? $canonical['submission_id'] : '',
			'canonical'      => $canonical,
			/*
			 * Temporary read-compatibility for the certified moderation view.
			 * This is storage compatibility only; numeric WPForms IDs never
			 * enter the canonical report contract itself.
			 */
			'private_fields' => array(
				55 => $description,
			),
		);
	}

	private function canonical_contact_storage_code( $contact ) {
		$map = array(
			'community'      => 'f78-c8',
			'badaround_only' => 'f78-c9',
			'none'           => 'f78-c10',
		);
		return isset( $map[ $contact ] ) ? $map[ $contact ] : null;
	}

	private function legacy_contact_preference( $choice_id ) {
		$map = array(
			8  => 'community',
			9  => 'badaround_only',
			10 => 'none',
		);
		$choice_id = absint( $choice_id );
		return isset( $map[ $choice_id ] ) ? $map[ $choice_id ] : 'none';
	}

	private function request_ip_hash() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return null;
		}
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}
}
