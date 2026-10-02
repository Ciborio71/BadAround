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

	public function find_by_source( $form_id, $entry_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'ba_reports';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_type = %s AND source_form_id = %d AND source_entry_id = %d LIMIT 1",
				'wpforms',
				absint( $form_id ),
				absint( $entry_id )
			)
		);
	}
}
