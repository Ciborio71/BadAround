<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stable machine-readable intake result/error contract.
 */
class BadAround_Report_Result {
	public static function success( $submission_id, $report_id, $normalized, $idempotency_state, $duplicate = false ) {
		return array(
			'status' => 'success',
			'submission_id' => (string) $submission_id,
			'schema_version' => BadAround_Report_Schema::VERSION,
			'report_id' => absint( $report_id ),
			'normalized_payload' => $normalized,
			'validation' => array( 'valid' => true ),
			'idempotency_state' => $idempotency_state,
			'duplicate' => (bool) $duplicate,
			'privacy' => self::privacy_map(),
		);
	}

	public static function error( $code, $field = null, $details = array() ) {
		return array(
			'status' => 'error',
			'error' => array(
				'code' => sanitize_key( $code ),
				'field' => $field ? (string) $field : null,
				'message_key' => sanitize_key( $code ),
				'details' => is_array( $details ) ? $details : array(),
			),
		);
	}

	public static function from_wp_error( $error ) {
		if ( ! is_wp_error( $error ) ) {
			return self::error( 'internal_error' );
		}
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$field = isset( $data['field'] ) ? $data['field'] : null;
		$source = $error->get_error_code();

		$map = array(
			'ba_report_invalid_payload' => 'invalid_payload',
			'ba_report_unknown_field' => 'unknown_field',
			'ba_report_invalid_type' => 'invalid_type',
			'ba_report_invalid_uuid' => 'invalid_submission_id',
			'ba_report_required_field' => 'missing_required_field',
			'ba_report_invalid_email' => 'invalid_email',
			'ba_report_invalid_date' => 'invalid_date_time',
			'ba_report_invalid_time' => 'invalid_date_time',
			'ba_report_future_date' => 'invalid_date_time',
			'ba_report_future_time' => 'invalid_date_time',
			'ba_report_invalid_time_range' => 'invalid_date_time',
			'ba_report_category_subtype_mismatch' => 'invalid_category_subtype',
			'ba_report_incomplete_coordinates' => 'invalid_location',
			'ba_report_too_many_items' => 'media_limit_exceeded',
			'ba_report_media_too_large' => 'media_limit_exceeded',
			'ba_report_invalid_media_type' => 'invalid_media',
			'ba_report_invalid_media_metadata' => 'invalid_media',
		);
		$code = isset( $map[ $source ] ) ? $map[ $source ] : preg_replace( '/^ba_report_/', '', $source );

		if ( 'ba_report_invalid_enum' === $source ) {
			$code = 'schema_version' === $field ? 'invalid_schema_version' : 'invalid_enum';
		}
		if ( 'ba_report_required_field' === $source && $field ) {
			$fields = BadAround_Report_Schema::fields();
			if ( ! empty( $fields[ $field ]['conditions'] ) ) {
				$code = 'conditional_field_required';
			}
		}
		$data['source_code'] = $source;
		return self::error( $code, $field, $data );
	}

	private static function privacy_map() {
		$out = array();
		foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
			$out[ $path ] = array(
				'privacy' => $definition['privacy'],
				'exposure' => $definition['exposure'],
			);
		}
		return $out;
	}
}
