<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * F1.4 orchestration: native validation/intake -> Golden Path persistence.
 */
class BadAround_Native_Report_Golden_Path_Service {
	private $intake;
	private $persistence;
	private $repository;

	public function __construct( $intake = null, $persistence = null, $repository = null ) {
		$this->repository  = $repository ?: new BadAround_Report_Repository();
		$this->intake      = $intake ?: new BadAround_Native_Report_Intake_Service();
		$this->persistence = $persistence ?: new BadAround_Report_Persistence_Service( $this->repository );
	}

	public function submit( $raw_payload ) {
		$intake = $this->intake->intake( $raw_payload );
		if ( ! is_array( $intake ) || 'success' !== ( isset( $intake['status'] ) ? $intake['status'] : '' ) ) {
			return $intake;
		}

		$submission_id = isset( $intake['submission_id'] ) ? strtolower( trim( (string) $intake['submission_id'] ) ) : '';
		$report_id     = isset( $intake['report_id'] ) ? absint( $intake['report_id'] ) : 0;
		$normalized    = isset( $intake['normalized_payload'] ) && is_array( $intake['normalized_payload'] ) ? $intake['normalized_payload'] : array();

		if ( ! $report_id || ! $submission_id ) {
			return BadAround_Report_Result::error( 'persistence_invalid_intake' );
		}

		if ( ! $this->repository->acquire_native_lock( $submission_id, 0 ) ) {
			return BadAround_Report_Result::error(
				'submission_in_progress',
				'submission_id',
				array( 'phase' => 'persistence' )
			);
		}

		try {
			$persisted = $this->persistence->persist(
				$report_id,
				$normalized,
				array(
					'type'          => 'native',
					'submission_id' => $submission_id,
				),
				array( 'prepare_public_projection' => false )
			);
			if ( is_wp_error( $persisted ) ) {
				$this->repository->mark_intake_status( $report_id, 'failed' );
				return BadAround_Report_Result::error(
					'persistence_failed',
					null,
					array(
						'report_id' => $report_id,
						'source_code' => $persisted->get_error_code(),
					)
				);
			}

			$this->repository->mark_intake_status( $report_id, 'validated' );
			return array_merge(
				$intake,
				array(
					'event_id' => absint( $persisted['event_id'] ),
					'persistence' => array(
						'completed' => true,
						'duplicate' => ! empty( $persisted['duplicate'] ),
						'recovered' => ! empty( $persisted['recovered'] ),
					),
				)
			);
		} finally {
			$this->repository->release_native_lock( $submission_id );
		}
	}
}
