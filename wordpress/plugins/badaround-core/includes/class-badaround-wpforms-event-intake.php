<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WPForms #6 intake: private source record -> pending ba_evento projection. */
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
		$form_id    = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$entry_id   = absint( $entry_id );
		$request_id = wp_generate_uuid4();

		if ( $this->get_form_id() !== $form_id || ! $entry_id ) {
			return;
		}

		$repository = new BadAround_Report_Repository();
		if ( ! $repository->acquire_source_lock( $form_id, $entry_id ) ) {
			BadAround_Audit_Log::technical_error( 'report', $entry_id, 'intake_lock_failed', 'source_lock_timeout', $request_id );
			return;
		}

		try {
			$report_id = $repository->create_intake_record( $form_id, $entry_id );
			if ( ! $report_id ) {
				BadAround_Audit_Log::technical_error( 'report', $entry_id, 'intake_record_failed', 'report_insert_failed', $request_id );
				return;
			}

			$existing_event_id = $repository->event_id_for_report( $report_id );
			if ( $existing_event_id ) {
				BadAround_Audit_Log::record( 'report', $report_id, 'intake_duplicate_ignored', 'wpforms', null, $request_id );
				return;
			}

			$mapper     = new BadAround_WPForms_Field_Mapper();
			$normalized = $mapper->normalize( is_array( $fields ) ? $fields : array(), is_array( $entry ) ? $entry : array(), is_array( $form_data ) ? $form_data : array() );

			if ( ! $this->minimum_valid( $normalized ) ) {
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'intake_validation_failed', 'required_fields_missing', $request_id );
				return;
			}

			if ( ! $repository->persist_normalized_private_data( $report_id, $normalized ) ) {
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'private_persistence_failed', 'report_update_failed', $request_id );
				return;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => BadAround_Event_Post_Type::POST_TYPE,
					'post_status'  => 'pending',
					'post_title'   => sprintf( __( 'Segnalazione da moderare #%d', 'badaround-core' ), $report_id ),
					'post_content' => '',
					'post_excerpt' => '',
					'meta_input'   => array_merge(
						array(
							'_ba_primary_report_id' => $report_id,
							'_ba_source_type'       => 'wpforms',
							'_ba_source_form_id'    => $form_id,
							'_ba_source_entry_id'   => $entry_id,
							'_ba_imported_at'       => current_time( 'mysql', true ),
						),
						$mapper->event_meta( $normalized )
					),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'event_creation_failed', $post_id->get_error_code(), $request_id );
				return;
			}

			$type_resolver = new BadAround_Event_Type_Resolver();
			if ( ! $type_resolver->resolve_and_assign( $post_id, $normalized, $form_data ) ) {
				BadAround_Audit_Log::technical_error( 'event', $post_id, 'event_type_mapping_failed', 'event_type_unresolved', $request_id );
			}

			$territory_resolver = new BadAround_Territory_Resolver();
			$territory_id = $territory_resolver->resolve( $normalized['location']['area_label'], $normalized['location']['exact_address'] );
			if ( $territory_id ) {
				wp_set_object_terms( $post_id, array( $territory_id ), BadAround_Event_Post_Type::TERRITORY_TAX, false );
			} else {
				BadAround_Audit_Log::technical_error( 'event', $post_id, 'territory_mapping_failed', 'canonical_territory_unresolved', $request_id );
			}

			$repository->link_event( $report_id, $post_id );

			if ( isset( $fields[63] ) && is_array( $fields[63] ) ) {
				$media = new BadAround_Media_Repository();
				$stored = $media->ingest_wpforms_files( $report_id, $post_id, $fields[63] );
				if ( is_wp_error( $stored ) ) {
					BadAround_Audit_Log::technical_error( 'report', $report_id, 'media_intake_deferred', $stored->get_error_code(), $request_id );
				}
			}

			BadAround_Audit_Log::record( 'report', $report_id, 'submission_normalized', 'wpforms', null, $request_id );
			BadAround_Audit_Log::record( 'event', $post_id, 'event_created_pending_moderation', 'wpforms', null, $request_id );
		} finally {
			$repository->release_source_lock( $form_id, $entry_id );
		}
	}

	private function minimum_valid( $normalized ) {
		$type     = isset( $normalized['event_type'] ) ? $normalized['event_type'] : array();
		$reporter = isset( $normalized['reporter'] ) ? $normalized['reporter'] : array();
		$consents = isset( $normalized['consents'] ) ? $normalized['consents'] : array();
		$location = isset( $normalized['location'] ) ? $normalized['location'] : array();

		return ! empty( $type['category_choice_id'] )
			&& ! empty( $location['exact_address'] )
			&& ! empty( $reporter['email'] )
			&& ! empty( $consents['truthfulness'] )
			&& ! empty( $consents['terms'] )
			&& ! empty( $consents['privacy'] );
	}

	private function get_form_id() {
		return absint( apply_filters( 'badaround_core_report_form_id', self::DEFAULT_FORM_ID ) );
	}
}
