<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Native submission reservation and duplicate detection.
 *
 * Atomicity relies on the existing named MySQL lock plus the existing UNIQUE
 * source_entry(source_type, source_form_id, source_entry_id) storage constraint.
 */
class BadAround_Report_Idempotency_Service {
	const STATE_NEW = 'NEW';
	const STATE_IN_PROGRESS = 'IN_PROGRESS';
	const STATE_COMPLETED = 'COMPLETED';
	const STATE_FAILED_RETRYABLE = 'FAILED_RETRYABLE';

	private $repository;

	public function __construct( $repository = null ) {
		$this->repository = $repository ?: new BadAround_Report_Repository();
	}

	public function reserve( $submission_id ) {
		$submission_id = strtolower( trim( (string) $submission_id ) );
		if ( ! $this->repository->acquire_native_lock( $submission_id, 0 ) ) {
			return array(
				'state' => self::STATE_IN_PROGRESS,
				'report_id' => 0,
				'locked' => false,
			);
		}

		$existing = absint( $this->repository->find_by_native_submission( $submission_id ) );
		if ( $existing ) {
			$status = $this->repository->intake_status( $existing );
			return array(
				'state' => 'validated' === $status ? self::STATE_COMPLETED : self::STATE_FAILED_RETRYABLE,
				'report_id' => $existing,
				'locked' => true,
				'status' => $status,
			);
		}

		$report_id = $this->repository->create_native_intake_record( $submission_id );
		if ( is_wp_error( $report_id ) ) {
			$this->repository->release_native_lock( $submission_id );
			return $report_id;
		}
		$report_id = absint( $report_id );
		if ( ! $report_id ) {
			$this->repository->release_native_lock( $submission_id );
			return new WP_Error( 'ba_report_insert_failed', __( 'Impossibile riservare la segnalazione.', 'badaround-core' ) );
		}

		return array(
			'state' => self::STATE_NEW,
			'report_id' => $report_id,
			'locked' => true,
			'status' => 'received',
		);
	}

	public function complete( $report_id ) {
		return $this->repository->mark_intake_status( $report_id, 'validated' );
	}

	public function fail( $report_id ) {
		return $this->repository->mark_intake_status( $report_id, 'failed' );
	}

	public function release( $submission_id ) {
		$this->repository->release_native_lock( $submission_id );
	}

	public function stored_payload_matches( $report_id, $canonical ) {
		$hash = $this->repository->payload_hash_for_report( $report_id );
		if ( '' === $hash ) {
			return null;
		}
		return $this->repository->native_payload_matches_report( $report_id, $canonical );
	}
}
