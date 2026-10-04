<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Sentinel_Repository {
	const STATUS_PENDING      = 'pending';
	const STATUS_ACTIVE       = 'active';
	const STATUS_PAUSED       = 'paused';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ba_sentinels';
	}

	public function find_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", absint( $id ) ),
			ARRAY_A
		);
	}

	public function find_by_public_id( $public_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE public_id = %s LIMIT 1", sanitize_text_field( $public_id ) ),
			ARRAY_A
		);
	}

	public function find_duplicate( $email_hash, $criteria_hash ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE email_hash = %s AND criteria_hash = %s LIMIT 1",
				$email_hash,
				$criteria_hash
			),
			ARRAY_A
		);
	}

	public function find_eligible_active() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$this->table()}
			 WHERE status = '" . self::STATUS_ACTIVE . "'
			   AND confirmed_at IS NOT NULL
			   AND disabled_at IS NULL
			   AND deleted_at IS NULL
			 ORDER BY id ASC",
			ARRAY_A
		);
	}

	public function create( $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'public_id'                => sanitize_text_field( $data['public_id'] ),
				'user_id'                  => ! empty( $data['user_id'] ) ? absint( $data['user_id'] ) : null,
				'email'                    => sanitize_email( $data['email'] ),
				'email_hash'               => $data['email_hash'],
				'status'                   => self::STATUS_PENDING,
				'territory_term_id'        => absint( $data['territory_term_id'] ),
				'category_term_id'         => ! empty( $data['category_term_id'] ) ? absint( $data['category_term_id'] ) : null,
				'event_type_term_id'       => ! empty( $data['event_type_term_id'] ) ? absint( $data['event_type_term_id'] ) : null,
				'criteria_json'             => wp_json_encode( $data['criteria'] ),
				'criteria_hash'             => $data['criteria_hash'],
				'verify_token_hash'         => $data['verify_token_hash'],
				'verification_expires_at'   => $data['verification_expires_at'],
				'created_at'                => $now,
				'updated_at'                => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'ba_sentinel_insert_failed', 'Unable to create sentinel.' );
		}

		return $this->find_by_id( (int) $wpdb->insert_id );
	}

	public function mark_notified( $id ) {
		global $wpdb;
		return false !== $wpdb->update(
			$this->table(),
			array(
				'last_notification_at' => current_time( 'mysql', true ),
				'updated_at'           => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	public function refresh_verification( $id, $token_hash, $expires_at ) {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'                  => self::STATUS_PENDING,
				'verify_token_hash'       => $token_hash,
				'verification_expires_at' => $expires_at,
				'updated_at'              => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'ba_sentinel_update_failed', 'Unable to refresh sentinel verification.' );
		}

		return $this->find_by_id( $id );
	}

	public function unsubscribe( $id ) {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'      => self::STATUS_UNSUBSCRIBED,
				'disabled_at' => current_time( 'mysql', true ),
				'updated_at'  => current_time( 'mysql', true ),
			),
			array(
				'id'     => absint( $id ),
				'status' => self::STATUS_ACTIVE,
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'ba_sentinel_unsubscribe_failed', 'Unable to unsubscribe sentinel.' );
		}

		return $this->find_by_id( $id );
	}

	public function activate( $id ) {
		global $wpdb;

		$updated = $wpdb->update(
			$this->table(),
			array(
				'status'                  => self::STATUS_ACTIVE,
				'confirmed_at'            => current_time( 'mysql', true ),
				'verification_expires_at' => null,
				'updated_at'              => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'ba_sentinel_activation_failed', 'Unable to activate sentinel.' );
		}

		return $this->find_by_id( $id );
	}
}
