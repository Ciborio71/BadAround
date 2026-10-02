<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the private report and its pending editorial projection.
 *
 * Field-level persistence is deferred to the approved field-ID mapper; this
 * class never copies an unclassified WPForms payload into a public post.
 */
class BadAround_WPForms_Event_Intake {
	const DEFAULT_FORM_ID = 6;

	public function register_hooks() {
		add_filter( 'badaround_report_form_id', array( $this, 'provide_report_form_id' ) );
		add_action( 'wpforms_process_complete', array( $this, 'create_event_from_submission' ), 10, 4 );
	}

	public function provide_report_form_id() {
		return $this->get_form_id();
	}

	public function create_event_from_submission( $fields, $entry, $form_data, $entry_id ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$entry_id = absint( $entry_id );

		if ( $this->get_form_id() !== $form_id || ! $entry_id ) {
			return;
		}

		$repository = new BadAround_Report_Repository();
		$report_id  = $repository->create_intake_record( $form_id, $entry_id );

		if ( ! $report_id || $this->event_exists_for_report( $report_id ) ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'  => 'pending',
				'post_title'   => sprintf(
					/* translators: %d: internal report ID. */
					__( 'Segnalazione da moderare #%d', 'badaround-core' ),
					$report_id
				),
				'post_content' => '',
				'meta_input'   => array(
					'_ba_primary_report_id' => $report_id,
					'_ba_source_type'       => 'wpforms',
					'_ba_source_form_id'    => $form_id,
					'_ba_source_entry_id'   => $entry_id,
					'_ba_imported_at'       => current_time( 'mysql', true ),
					'_ba_moderation_status' => 'new',
					'_ba_event_status'      => 'open',
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			BadAround_Audit_Log::record( 'report', $report_id, 'event_creation_failed', 'wpforms', $post_id->get_error_code() );
			return;
		}

		$repository->link_event( $report_id, $post_id );
		BadAround_Audit_Log::record( 'event', $post_id, 'event_created', 'wpforms' );
	}

	private function get_form_id() {
		return absint( apply_filters( 'badaround_core_report_form_id', self::DEFAULT_FORM_ID ) );
	}

	private function event_exists_for_report( $report_id ) {
		$event_ids = get_posts(
			array(
				'post_type'              => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'            => 'any',
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_ba_primary_report_id',
				'meta_value'             => absint( $report_id ),
			)
		);

		return ! empty( $event_ids );
	}
}
