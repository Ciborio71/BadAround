<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Explicit dispatch; VERSION on the historical schema deliberately stays v1. */
class BadAround_Report_Version_Registry {
	const V1 = 'badaround-report/v1';
	const V2 = 'badaround-report/v2';
	public static function version( $report ) {
		return is_array( $report ) && isset( $report['schema_version'] ) && is_string( $report['schema_version'] ) ? trim( $report['schema_version'] ) : '';
	}
	public static function supported( $version ) { return in_array( $version, array( self::V1, self::V2 ), true ); }
	public static function components( $report ) {
		if ( self::V2 === self::version( $report ) ) {
			require_once __DIR__ . '/class-badaround-report-v2-validator.php';
			return array( new BadAround_Report_V2_Normalizer(), new BadAround_Report_V2_Validator() );
		}
		// Missing/unsupported markers are rejected by the original v1 error path,
		// preserving its raw preflight/error precedence; they are never accepted as v1.
		return array( new BadAround_Report_Normalizer(), new BadAround_Report_Validator() );
	}
	public static function prepare( $report ) {
		list( $normalizer, $validator ) = self::components( $report );
		$error = $validator->validate_raw_contract( $report ); if ( is_wp_error( $error ) ) { return $error; }
		$canonical = $normalizer->normalize( $report ); $error = $validator->validate( $canonical );
		return is_wp_error( $error ) ? $error : $canonical;
	}
}
