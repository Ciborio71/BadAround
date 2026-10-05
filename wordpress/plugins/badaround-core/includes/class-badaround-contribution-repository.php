<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Repository {
	const STATUS_PENDING_VERIFICATION = 'pending_verification';
	const STATUS_TO_REVIEW             = 'to_review';
	const STATUS_IN_REVIEW             = 'in_review';
	const STATUS_PUBLISHED             = 'published';
	const STATUS_RESERVED              = 'reserved';
	const STATUS_REJECTED              = 'rejected';
	const STATUS_EXPIRED               = 'expired';

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ba_contributions';
	}

	public function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$result = $wpdb->insert(
			$this->table(),
			array(
				'public_id'               => sanitize_text_field( $data['public_id'] ),
				'event_id'                => absint( $data['event_id'] ),
				'user_id'                 => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'email'                   => strtolower( sanitize_email( $data['email'] ) ),
				'email_hash'              => sanitize_text_field( $data['email_hash'] ),
				'status'                  => self::STATUS_PENDING_VERIFICATION,
				'contribution_type'       => sanitize_key( $data['contribution_type'] ),
				'public_identity_mode'    => sanitize_key( $data['public_identity_mode'] ),
				'public_display_name'     => ! empty( $data['public_display_name'] ) ? sanitize_text_field( $data['public_display_name'] ) : null,
				'visibility_requested'    => sanitize_key( $data['visibility_requested'] ),
				'content_original'        => sanitize_textarea_field( $data['content_original'] ),
				'observed_at'             => ! empty( $data['observed_at'] ) ? sanitize_text_field( $data['observed_at'] ) : null,
				'observed_at_precision'   => ! empty( $data['observed_at_precision'] ) ? sanitize_key( $data['observed_at_precision'] ) : null,
				'exact_location_text'     => ! empty( $data['exact_location_text'] ) ? sanitize_textarea_field( $data['exact_location_text'] ) : null,
				'exact_lat'               => isset( $data['exact_lat'] ) && is_numeric( $data['exact_lat'] ) ? (float) $data['exact_lat'] : null,
				'exact_lng'               => isset( $data['exact_lng'] ) && is_numeric( $data['exact_lng'] ) ? (float) $data['exact_lng'] : null,
				'direction'               => ! empty( $data['direction'] ) ? sanitize_text_field( $data['direction'] ) : null,
				'additional_details'      => ! empty( $data['additional_details'] ) ? sanitize_textarea_field( $data['additional_details'] ) : null,
				'contact_allowed'         => ! empty( $data['contact_allowed'] ) ? 1 : 0,
				'consent_privacy'         => ! empty( $data['consent_privacy'] ) ? 1 : 0,
				'consent_version'         => ! empty( $data['consent_version'] ) ? sanitize_text_field( $data['consent_version'] ) : null,
				'consented_at'            => $now,
				'verify_token_hash'       => sanitize_text_field( $data['verify_token_hash'] ),
				'verification_expires_at' => sanitize_text_field( $data['verification_expires_at'] ),
				'payload_hash'            => sanitize_text_field( $data['payload_hash'] ),
				'ip_hash'                 => ! empty( $data['ip_hash'] ) ? sanitize_text_field( $data['ip_hash'] ) : null,
				'source'                  => ! empty( $data['source'] ) ? sanitize_key( $data['source'] ) : 'event_detail',
				'created_at'              => $now,
				'updated_at'              => $now,
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'ba_contribution_create_failed', __( 'Impossibile salvare il contributo.', 'badaround-core' ) );
		}
		return $this->find_by_id( (int) $wpdb->insert_id );
	}

	public function find_by_id( $id ) {
		global $wpdb;
		$table = $this->table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL LIMIT 1", absint( $id ) ), ARRAY_A );
		return $row ?: null;
	}

	public function find_by_public_id( $public_id ) {
		global $wpdb;
		$table = $this->table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE public_id = %s AND deleted_at IS NULL LIMIT 1", sanitize_text_field( $public_id ) ), ARRAY_A );
		return $row ?: null;
	}

	public function find_recent_duplicate( $event_id, $email_hash, $payload_hash, $since_gmt ) {
		global $wpdb;
		$table = $this->table();
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE event_id = %d AND email_hash = %s AND payload_hash = %s AND created_at >= %s AND deleted_at IS NULL ORDER BY id DESC LIMIT 1",
				absint( $event_id ),
				sanitize_text_field( $email_hash ),
				sanitize_text_field( $payload_hash ),
				sanitize_text_field( $since_gmt )
			),
			ARRAY_A
		);
		return $row ?: null;
	}

	public function refresh_verification( $id, $token_hash, $expires_at ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->table(),
			array(
				'verify_token_hash'       => sanitize_text_field( $token_hash ),
				'verification_expires_at' => sanitize_text_field( $expires_at ),
				'updated_at'              => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'status' => self::STATUS_PENDING_VERIFICATION )
		);
		return false === $updated ? new WP_Error( 'ba_contribution_verification_refresh_failed', __( 'Impossibile aggiornare la verifica.', 'badaround-core' ) ) : $this->find_by_id( $id );
	}

	public function mark_verified( $id ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'            => self::STATUS_TO_REVIEW,
				'email_verified_at' => current_time( 'mysql', true ),
				'verify_token_hash' => null,
				'updated_at'        => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'status' => self::STATUS_PENDING_VERIFICATION )
		);
		return false === $updated ? new WP_Error( 'ba_contribution_verify_failed', __( 'Impossibile confermare il contributo.', 'badaround-core' ) ) : $this->find_by_id( $id );
	}

	public function mark_expired( $id ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'     => self::STATUS_EXPIRED,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'status' => self::STATUS_PENDING_VERIFICATION )
		);
		return false !== $updated;
	}
	public function moderate( $id, $expected_status, $target_status, array $data ) {
		global $wpdb;
		$update = array(
			'status' => sanitize_key( $target_status ),
			'visibility_decided' => ! empty( $data['visibility_decided'] ) ? sanitize_key( $data['visibility_decided'] ) : null,
			'public_projection_id' => ! empty( $data['public_projection_id'] ) ? absint( $data['public_projection_id'] ) : null,
			'moderated_by' => ! empty( $data['moderated_by'] ) ? absint( $data['moderated_by'] ) : null,
			'moderated_at' => current_time( 'mysql', true ),
			'moderation_reason' => ! empty( $data['reason'] ) ? sanitize_textarea_field( $data['reason'] ) : null,
			'updated_at' => current_time( 'mysql', true ),
		);

		$updated = $wpdb->update(
			$this->table(),
			$update,
			array(
				'id' => absint( $id ),
				'status' => sanitize_key( $expected_status ),
			)
		);
		if ( false === $updated || 0 === $updated ) {
			return new WP_Error( 'ba_contribution_moderation_update_failed', __( 'Impossibile aggiornare lo stato del contributo.', 'badaround-core' ) );
		}
		return $this->find_by_id( $id );
	}

	public function list_for_moderation( $limit = 100 ) {
		global $wpdb;
		$table = $this->table();
		$limit = max( 1, min( 200, absint( $limit ) ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE deleted_at IS NULL AND status IN (%s,%s,%s,%s,%s) ORDER BY created_at DESC LIMIT %d",
				self::STATUS_TO_REVIEW,
				self::STATUS_IN_REVIEW,
				self::STATUS_PUBLISHED,
				self::STATUS_RESERVED,
				self::STATUS_REJECTED,
				$limit
			),
			ARRAY_A
		);
	}
}
