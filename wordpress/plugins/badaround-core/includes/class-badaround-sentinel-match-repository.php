<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Sentinel_Match_Repository {
	const MATCH_MATCHED = 'matched';
	const MATCH_CANCELLED = 'cancelled';

	const NOTIFY_QUEUED = 'queued';
	const NOTIFY_SENDING = 'sending';
	const NOTIFY_RETRY = 'retry_pending';
	const NOTIFY_SENT = 'sent';
	const NOTIFY_FAILED = 'failed';
	const NOTIFY_CANCELLED = 'cancelled';

	private function table() {
		global $wpdb;
		return $wpdb->prefix . 'ba_sentinel_event_matches';
	}

	public function find_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", absint( $id ) ),
			ARRAY_A
		);
	}

	public function find_pair( $sentinel_id, $event_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE sentinel_id = %d AND event_id = %d LIMIT 1",
				absint( $sentinel_id ),
				absint( $event_id )
			),
			ARRAY_A
		);
	}

	public function create_unique( $sentinel_id, $event_id ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$this->table()}
				 (sentinel_id,event_id,match_status,notification_status,notification_attempts,matched_at,created_at,updated_at)
				 VALUES (%d,%d,%s,%s,0,%s,%s,%s)",
				absint( $sentinel_id ),
				absint( $event_id ),
				self::MATCH_MATCHED,
				self::NOTIFY_QUEUED,
				$now,
				$now,
				$now
			)
		);

		if ( false === $inserted ) {
			return new WP_Error( 'ba_sentinel_match_insert_failed', 'Unable to persist sentinel event match.' );
		}
		if ( 0 === (int) $inserted ) {
			return false;
		}
		return $this->find_by_id( (int) $wpdb->insert_id );
	}

	public function claim_for_send( $id, $max_attempts ) {
		global $wpdb;
		$id = absint( $id );
		$now = current_time( 'mysql', true );

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table()}
				 SET notification_status = %s,
				     notification_attempts = notification_attempts + 1,
				     last_attempt_at = %s,
				     updated_at = %s
				 WHERE id = %d
				   AND notification_status IN (%s,%s)
				   AND notification_attempts < %d",
				self::NOTIFY_SENDING,
				$now,
				$now,
				$id,
				self::NOTIFY_QUEUED,
				self::NOTIFY_RETRY,
				absint( $max_attempts )
			)
		);

		if ( 1 !== (int) $updated ) {
			return false;
		}
		return $this->find_by_id( $id );
	}

	public function mark_sent( $id ) {
		return $this->update_state(
			$id,
			array(
				'notification_status' => self::NOTIFY_SENT,
				'sent_at'             => current_time( 'mysql', true ),
				'next_retry_at'       => null,
				'last_error_code'     => null,
			)
		);
	}

	public function mark_retry( $id, $error_code, $next_retry_at ) {
		return $this->update_state(
			$id,
			array(
				'notification_status' => self::NOTIFY_RETRY,
				'last_error_code'     => sanitize_key( $error_code ),
				'next_retry_at'       => sanitize_text_field( $next_retry_at ),
			)
		);
	}

	public function mark_failed( $id, $error_code ) {
		return $this->update_state(
			$id,
			array(
				'notification_status' => self::NOTIFY_FAILED,
				'last_error_code'     => sanitize_key( $error_code ),
				'next_retry_at'       => null,
			)
		);
	}

	public function mark_cancelled( $id, $reason ) {
		return $this->update_state(
			$id,
			array(
				'match_status'        => self::MATCH_CANCELLED,
				'notification_status' => self::NOTIFY_CANCELLED,
				'last_error_code'     => sanitize_key( $reason ),
				'next_retry_at'       => null,
			)
		);
	}

	private function update_state( $id, $fields ) {
		global $wpdb;
		$fields['updated_at'] = current_time( 'mysql', true );
		$formats = array();
		foreach ( $fields as $value ) {
			$formats[] = is_int( $value ) ? '%d' : '%s';
		}
		$result = $wpdb->update(
			$this->table(),
			$fields,
			array( 'id' => absint( $id ) ),
			$formats,
			array( '%d' )
		);
		return false !== $result;
	}
}
