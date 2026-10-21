<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-badaround-report-v2-normalizer.php';

/** Version-specific activation/requiredness with inherited, unchanged validation primitives. */
class BadAround_Report_V2_Validator extends BadAround_Report_Validator {
	public function validate_raw_contract( $report ) {
		if ( ! is_array( $report ) ) { return new WP_Error( 'ba_report_invalid_payload', 'Invalid report.' ); }
		$fields = BadAround_Report_Conditional_Contract::fields();
		$unknown = $this->unknown_paths( $report, $fields );
		if ( $unknown ) { return new WP_Error( 'ba_report_unknown_field', 'Unknown field.', array( 'field' => $unknown[0] ) ); }
		$basic = ( new BadAround_Report_V2_Normalizer() )->normalize( $report );
		$row = BadAround_Report_Conditional_Contract::row( $basic );
		foreach ( $report as $domain => $values ) {
			if ( isset( $fields[ $domain ] ) || is_array( $values ) ) { continue; }
			foreach ( $fields as $path => $definition ) {
				if ( 0 === strpos( $path, $domain . '.' ) && ( ! $row || BadAround_Report_Conditional_Contract::active( $path, $basic, $row ) ) ) {
					return $this->field_error( 'ba_report_invalid_type', $domain, 'Invalid report object.' );
				}
			}
		}
		foreach ( $fields as $path => $definition ) {
			if ( ! $this->has_path( $report, $path ) || ( $row && ! BadAround_Report_Conditional_Contract::active( $path, $basic, $row ) ) ) { continue; }
			$value = $this->get_path( $report, $path );
			$is_text = in_array( $definition['type'], array( 'string', 'email', 'phone', 'enum', 'uuid', 'date', 'time', 'plate' ), true );
			if ( ( $is_text && ! is_string( $value ) ) || ! $this->raw_type_matches( $value, $definition['type'] ) ) { return $this->field_error( 'ba_report_invalid_type', $path, 'Invalid field type.' ); }
			if ( is_string( $value ) && ( ! preg_match( '//u', $value ) || strip_tags( $value ) !== $value || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value ) || ( isset( $definition['constraints']['single_line'] ) && preg_match( '/[\r\n\x{0085}\x{2028}\x{2029}]/u', $value ) ) ) ) { return $this->field_error( 'ba_report_invalid_type', $path, 'Invalid plain text.' ); }
			if ( is_string( $value ) && isset( $definition['constraints']['single_line'] ) && '' !== trim( $value ) && ! preg_match( '/[^\p{Z}\p{C}\s]/u', $value ) ) { return $this->field_error( 'ba_report_invalid_type', $path, 'Specification must contain visible text.' ); }
			if ( 'media.items' === $path && is_array( $value ) ) {
				foreach ( $value as $descriptor ) {
					if ( ! is_array( $descriptor ) || count( $descriptor ) !== 4 || array_diff( array_keys( $descriptor ), array( 'media_id', 'mime_type', 'file_size', 'extension' ) ) ) { return $this->field_error( 'ba_report_invalid_media_metadata', $path, 'Invalid media descriptor.' ); }
				}
			}
		}
		return true;
	}
	public function normalize( $report ) { return ( new BadAround_Report_V2_Normalizer() )->normalize( $report ); }
	public function validate( $report ) {
		$fields = BadAround_Report_Conditional_Contract::fields();
		foreach ( array( 'schema_version', 'submission_id', 'event.category', 'event.subtype' ) as $path ) {
			if ( ! $this->has_meaningful_value( $report, $path ) ) { return $this->field_error( 'ba_report_required_field', $path, 'Required field.' ); }
			$valid = $this->validate_field( $path, $this->get_path( $report, $path ), $fields[ $path ] );
			if ( is_wp_error( $valid ) ) { return $valid; }
		}
		$row = BadAround_Report_Conditional_Contract::row( $report );
		if ( ! $row ) { return $this->field_error( 'ba_report_category_subtype_mismatch', 'event.subtype', 'Invalid category/subtype.' ); }
		$geo = $this->validate_public_location_request( $report ); if ( is_wp_error( $geo ) ) { return $geo; }
		foreach ( $fields as $path => $definition ) {
			if ( ! BadAround_Report_Conditional_Contract::active( $path, $report, $row ) ) { continue; }
			$rule = $row['fields'][ $path ]; $present = $this->has_meaningful_value( $report, $path );
			if ( $rule['required_when_active'] && ! $present ) { return new WP_Error( 'ba_report_required_field', 'Required active field.', array( 'field' => $path, 'conditional' => 'CONDITIONAL' === $rule['state'] ) ); }
			if ( ! $present ) { continue; }
			if ( isset( $rule['constraints_override'] ) ) { $definition['constraints'] = array_merge( $definition['constraints'], $rule['constraints_override'] ); }
			$value = $this->get_path( $report, $path );
			if ( is_string( $value ) ) {
				$length = preg_match_all( '/./us', $value );
				foreach ( array( 'min_length' => 'ba_report_value_too_short', 'max_length' => 'ba_report_value_too_long' ) as $bound => $code ) {
					if ( isset( $definition['constraints'][ $bound ] ) && ( 'min_length' === $bound ? $length < $definition['constraints'][ $bound ] : $length > $definition['constraints'][ $bound ] ) ) { return $this->field_error( $code, $path, 'Invalid text length.' ); }
					unset( $definition['constraints'][ $bound ] );
				}
			}
			$error = $this->validate_field( $path, $this->get_path( $report, $path ), $definition );
			if ( is_wp_error( $error ) ) { return $error; }
		}
		if ( $this->has_meaningful_value( $report, 'location.exact_lat' ) xor $this->has_meaningful_value( $report, 'location.exact_lng' ) ) { return $this->field_error( 'ba_report_incomplete_coordinates', 'location.exact_lat', 'Coordinate pair required.' ); }
		foreach ( array( $this->validate_time_rules( $report, BadAround_Report_Schema::validation_rules() ), $this->validate_reward_rules( $report ) ) as $check ) { if ( is_wp_error( $check ) ) { return $check; } }
		foreach ( array( 'truthfulness', 'media_rights', 'publication_rules', 'terms', 'privacy' ) as $consent ) { if ( true !== $this->get_path( $report, 'consents.' . $consent ) ) { return $this->field_error( 'ba_report_consent_required', 'consents.' . $consent, 'Consent required.' ); } }
		return true;
	}
}
