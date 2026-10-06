<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Temporary WPForms #6 adapter.
 *
 * WPForms remains a compatibility source during migration. All domain intake
 * now flows through CanonicalReport -> BadAround_Report_Intake_Service.
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

		$adapter = new BadAround_WPForms_Report_Adapter();
		$canonical = $adapter->to_canonical(
			is_array( $fields ) ? $fields : array(),
			is_array( $entry ) ? $entry : array(),
			is_array( $form_data ) ? $form_data : array(),
			$entry_id
		);
		$legacy_payload = $adapter->legacy_payload(
			is_array( $fields ) ? $fields : array(),
			is_array( $entry ) ? $entry : array(),
			is_array( $form_data ) ? $form_data : array()
		);

		$service = new BadAround_Report_Intake_Service();
		$result  = $service->ingest(
			$canonical,
			array(
				'type'           => 'wpforms',
				'form_id'        => $form_id,
				'entry_id'       => $entry_id,
				'legacy_payload' => $legacy_payload,
			)
		);

		if ( is_wp_error( $result ) || ! is_array( $result ) || ! empty( $result['duplicate'] ) ) {
			return;
		}

		$report_id = isset( $result['report_id'] ) ? absint( $result['report_id'] ) : 0;
		$event_id  = isset( $result['event_id'] ) ? absint( $result['event_id'] ) : 0;
		if ( ! $report_id || ! $event_id ) {
			return;
		}

		/*
		 * F1 deliberately preserves the certified WPForms upload adapter.
		 * Native secure media intake belongs to a later phase before cut-over.
		 */
		if ( isset( $fields[63] ) && is_array( $fields[63] ) ) {
			$media  = new BadAround_Media_Repository();
			$stored = $media->ingest_wpforms_files( $report_id, $event_id, $fields[63] );
			if ( is_wp_error( $stored ) ) {
				BadAround_Audit_Log::technical_error(
					'report',
					$report_id,
					'media_intake_deferred',
					$stored->get_error_code(),
					wp_generate_uuid4()
				);
			}
		}
	}

	private function get_form_id() {
		return absint( apply_filters( 'badaround_core_report_form_id', self::DEFAULT_FORM_ID ) );
	}
}
