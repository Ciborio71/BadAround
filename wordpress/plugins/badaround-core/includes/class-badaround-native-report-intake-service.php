<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * F1.3 native-only intake foundation.
 *
 * Stops after validated private report persistence. It deliberately does not
 * create ba_evento, assign taxonomies, resolve territory, prepare publication,
 * notify Sentinelle, or trigger any downstream workflow.
 */
class BadAround_Native_Report_Intake_Service {
	private $normalizer;
	private $validator;
	private $repository;
	private $idempotency;

	public function __construct( $normalizer = null, $validator = null, $repository = null, $idempotency = null ) {
		$this->normalizer  = $normalizer ?: new BadAround_Report_Normalizer();
		$this->validator   = $validator ?: new BadAround_Report_Validator();
		$this->repository  = $repository ?: new BadAround_Report_Repository();
		$this->idempotency = $idempotency ?: new BadAround_Report_Idempotency_Service( $this->repository );
	}

	public function intake( $raw_payload ) {
		$request_id = wp_generate_uuid4();
		BadAround_Audit_Log::record( 'report', 0, 'intake_started', 'native', null, $request_id );

		$raw_error = $this->validator->validate_raw_contract( $raw_payload );
		if ( is_wp_error( $raw_error ) ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'validation_failed', $raw_error->get_error_code(), $request_id );
			return BadAround_Report_Result::from_wp_error( $raw_error );
		}

		$normalized = $this->normalizer->normalize( $raw_payload );
		$valid = $this->validator->validate( $normalized );
		if ( is_wp_error( $valid ) ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'validation_failed', $valid->get_error_code(), $request_id );
			return BadAround_Report_Result::from_wp_error( $valid );
		}

		$submission_id = isset( $normalized['submission_id'] ) ? $normalized['submission_id'] : '';
		$reservation = $this->idempotency->reserve( $submission_id );
		if ( is_wp_error( $reservation ) ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'intake_failed', $reservation->get_error_code(), $request_id );
			return BadAround_Report_Result::from_wp_error( $reservation );
		}
		if ( BadAround_Report_Idempotency_Service::STATE_IN_PROGRESS === $reservation['state'] ) {
			BadAround_Audit_Log::record( 'report', 0, 'idempotency_duplicate', 'native', 'submission_in_progress', $request_id );
			return BadAround_Report_Result::error( 'submission_in_progress', 'submission_id', array( 'idempotency_state' => 'IN_PROGRESS' ) );
		}

		$report_id = absint( $reservation['report_id'] );
		try {
			if ( BadAround_Report_Idempotency_Service::STATE_COMPLETED === $reservation['state'] ) {
				$matches = $this->idempotency->stored_payload_matches( $report_id, $normalized );
				if ( true !== $matches ) {
					BadAround_Audit_Log::record( 'report', $report_id, 'idempotency_duplicate', 'native', 'payload_mismatch', $request_id );
					return BadAround_Report_Result::error(
						'duplicate_submission',
						'submission_id',
						array( 'idempotency_state' => 'COMPLETED', 'payload_match' => false )
					);
				}
				BadAround_Audit_Log::record( 'report', $report_id, 'idempotency_duplicate', 'native', null, $request_id );
				return BadAround_Report_Result::success( $submission_id, $report_id, $normalized, 'COMPLETED', true );
			}

			if ( BadAround_Report_Idempotency_Service::STATE_FAILED_RETRYABLE === $reservation['state'] ) {
				$stored_match = $this->idempotency->stored_payload_matches( $report_id, $normalized );
				if ( false === $stored_match ) {
					BadAround_Audit_Log::record( 'report', $report_id, 'idempotency_duplicate', 'native', 'payload_mismatch', $request_id );
					return BadAround_Report_Result::error(
						'duplicate_submission',
						'submission_id',
						array( 'idempotency_state' => 'FAILED_RETRYABLE', 'payload_match' => false )
					);
				}
			}

			if ( ! $this->repository->persist_canonical_private_data( $report_id, $normalized, null ) ) {
				$this->idempotency->fail( $report_id );
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'intake_failed', 'private_persistence_failed', $request_id );
				return BadAround_Report_Result::error( 'intake_persistence_failed', null, array( 'report_id' => $report_id ) );
			}

			if ( ! $this->idempotency->complete( $report_id ) ) {
				$this->idempotency->fail( $report_id );
				BadAround_Audit_Log::technical_error( 'report', $report_id, 'intake_failed', 'status_update_failed', $request_id );
				return BadAround_Report_Result::error( 'intake_state_update_failed', null, array( 'report_id' => $report_id ) );
			}

			BadAround_Audit_Log::record( 'report', $report_id, 'intake_validated', 'native', null, $request_id );
			BadAround_Audit_Log::record( 'report', $report_id, 'intake_completed', 'native', null, $request_id );

			return BadAround_Report_Result::success(
				$submission_id,
				$report_id,
				$normalized,
				$reservation['state'],
				false
			);
		} catch ( Throwable $error ) {
			$this->idempotency->fail( $report_id );
			BadAround_Audit_Log::technical_error( 'report', $report_id, 'intake_failed', 'unexpected_exception', $request_id );
			return BadAround_Report_Result::error( 'intake_failed', null, array( 'report_id' => $report_id ) );
		} finally {
			$this->idempotency->release( $submission_id );
		}
	}
}
