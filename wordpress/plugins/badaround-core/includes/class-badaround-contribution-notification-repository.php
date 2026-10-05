<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Notification_Repository {
	const STATUS_QUEUED = 'queued';
	const STATUS_SENDING = 'sending';
	const STATUS_SENT = 'sent';
	const STATUS_RETRY = 'retry';
	const STATUS_FAILED = 'failed';

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ba_contribution_notifications';
	}

	public function create_or_get( $contribution_id, $event_id, $kind, $recipient_hash, $access_public_id = null, $access_token_hash = null, $access_expires_at = null ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$this->table()} (contribution_id,event_id,notification_kind,recipient_hash,status,access_public_id,access_token_hash,access_expires_at,created_at,updated_at)
				 VALUES (%d,%d,%s,%s,%s,%s,%s,%s,%s,%s)",
				absint( $contribution_id ),
				absint( $event_id ),
				sanitize_key( $kind ),
				sanitize_text_field( $recipient_hash ),
				self::STATUS_QUEUED,
				$access_public_id ? sanitize_text_field( $access_public_id ) : null,
				$access_token_hash ? sanitize_text_field( $access_token_hash ) : null,
				$access_expires_at ? sanitize_text_field( $access_expires_at ) : null,
				$now,
				$now
			)
		);
		return $this->find_by_contribution( $contribution_id );
	}

	public function find_by_contribution( $contribution_id ) {
		global $wpdb;
		$table = $this->table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE contribution_id = %d LIMIT 1", absint( $contribution_id ) ), ARRAY_A );
		return $row ?: null;
	}

	public function find_by_access_public_id( $public_id ) {
		global $wpdb;
		$table = $this->table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE access_public_id = %s LIMIT 1", sanitize_text_field( $public_id ) ), ARRAY_A );
		return $row ?: null;
	}

	public function claim( $id ) {
		global $wpdb;
		$updated = $wpdb->update(
			$this->table(),
			array(
				'status' => self::STATUS_SENDING,
				'last_attempt_at' => current_time( 'mysql', true ),
				'notification_attempts' => new stdClass(),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ), 'status' => self::STATUS_QUEUED )
		);
		if ( false === $updated ) { return false; }
		// Increment atomically after the state claim.
		$wpdb->query( $wpdb->prepare( "UPDATE {$this->table()} SET notification_attempts = notification_attempts + 1 WHERE id = %d", absint( $id ) ) );
		return 1 === (int) $updated;
	}

	public function claim_retry( $id ) {
		global $wpdb;
		$table = $this->table();
		$now = current_time( 'mysql', true );
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status=%s,last_attempt_at=%s,notification_attempts=notification_attempts+1,updated_at=%s
				 WHERE id=%d AND status=%s AND (next_retry_at IS NULL OR next_retry_at <= %s)",
				self::STATUS_SENDING, $now, $now, absint( $id ), self::STATUS_RETRY, $now
			)
		);
		return 1 === (int) $updated;
	}

	public function mark_sent( $id ) {
		global $wpdb;
		return false !== $wpdb->update(
			$this->table(),
			array(
				'status' => self::STATUS_SENT,
				'sent_at' => current_time( 'mysql', true ),
				'next_retry_at' => null,
				'last_error_code' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	public function mark_retry( $id, $error_code, $next_retry_at ) {
		global $wpdb;
		return false !== $wpdb->update(
			$this->table(),
			array(
				'status' => self::STATUS_RETRY,
				'next_retry_at' => sanitize_text_field( $next_retry_at ),
				'last_error_code' => sanitize_key( $error_code ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) )
		);
	}

	public function mark_failed( $id, $error_code ) {
		global $wpdb;
		return false !== $wpdb->update(
			$this->table(),
			array(
				'status' => self::STATUS_FAILED,
				'next_retry_at' => null,
				'last_error_code' => sanitize_key( $error_code ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) )
		);
	}
}
