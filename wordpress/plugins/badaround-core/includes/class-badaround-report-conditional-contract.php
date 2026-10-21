<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only, hash-pinned NF-CONDITIONAL-CONTRACT v1.0 evaluator. Never used by v1. */
class BadAround_Report_Conditional_Contract {
	const SHA256 = 'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c';
	private static $contract;
	private static $rows;
	public static function definition() {
		if ( null === self::$contract ) {
			$bytes = file_get_contents( dirname( __DIR__ ) . '/contracts/nf-conditional-v1.0.json' );
			if ( ! is_string( $bytes ) || ! hash_equals( self::SHA256, hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'Conditional contract integrity failed.' ); }
			self::$contract = json_decode( $bytes, true, 512, JSON_THROW_ON_ERROR );
			self::$rows = array();
			foreach ( self::$contract['matrix'] as $row ) { self::$rows[ $row['subtype'] ] = $row; }
		}
		return self::$contract;
	}
	public static function fields() {
		$c = self::definition();
		$out = BadAround_Report_Schema::fields();
		foreach ( $c['fields'] as $path => $field ) {
			if ( isset( $field['source_field'] ) ) {
				$out[ $path ] = array( 'type' => 'string', 'required' => false, 'nullable' => false, 'conditions' => array(), 'constraints' => $field['constraints'],
					'privacy' => 'PRIVATE ONLY' === $field['classification'] ? 'private' : 'moderation-only', 'exposure' => 'private', 'destination' => 'ba_reports.content_original', 'public_projection' => 'none' );
			}
		}
		// Explicit technical overlay from NF-UX-02A; frozen v1 schema is untouched.
		$out['schema_version']['constraints']['enum'] = array( 'badaround-report/v2' );
		return $out;
	}
	public static function row( $report ) {
		self::definition();
		$subtype = self::value( $report, 'event.subtype' );
		$category = self::value( $report, 'event.category' );
		$row = is_string( $subtype ) ? ( self::$rows[ $subtype ] ?? null ) : null;
		return $row && $row['category'] === $category ? $row : null;
	}
	public static function value( $report, $path ) {
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $report ) || ! array_key_exists( $part, $report ) ) { return null; }
			$report = $report[ $part ];
		}
		return $report;
	}
	public static function put( &$out, $path, $value ) {
		$parts = explode( '.', $path ); $cursor =& $out;
		foreach ( $parts as $i => $part ) {
			if ( $i === count( $parts ) - 1 ) { $cursor[ $part ] = $value; return; }
			if ( ! isset( $cursor[ $part ] ) || ! is_array( $cursor[ $part ] ) ) { $cursor[ $part ] = array(); }
			$cursor =& $cursor[ $part ];
		}
	}
	public static function active( $path, $report, $row, $seen = array() ) {
		// NF-UX-02A technical envelope overlay: questionnaire visibility cannot
		// strip the explicit discriminator or the existing global UUID identity.
		if ( in_array( $path, array( 'schema_version', 'submission_id' ), true ) ) { return true; }
		if ( ! $row || isset( $seen[ $path ] ) || ! isset( $row['fields'][ $path ] ) ) { return false; }
		$seen[ $path ] = true; $rule = $row['fields'][ $path ];
		if ( 'HIDDEN' === $rule['state'] ) { return false; }
		return 'CONDITIONAL' !== $rule['state'] || self::matches( $rule['condition'], $report, $row, $seen );
	}
	private static function matches( $expr, $report, $row, $seen ) {
		if ( isset( $expr['all'] ) ) { foreach ( $expr['all'] as $item ) { if ( ! self::matches( $item, $report, $row, $seen ) ) { return false; } } return true; }
		if ( isset( $expr['any'] ) ) { foreach ( $expr['any'] as $item ) { if ( self::matches( $item, $report, $row, $seen ) ) { return true; } } return false; }
		$path = $expr['field'];
		if ( 'active' === $expr['op'] ) { return self::active( $path, $report, $row, $seen ); }
		if ( ! self::active( $path, $report, $row, $seen ) ) { return false; }
		$value = self::value( $report, $path );
		if ( null === $value ) { return false; }
		if ( 'eq' === $expr['op'] ) { return $value === $expr['value']; }
		if ( 'in' === $expr['op'] ) { return in_array( $value, $expr['values'], true ); }
		if ( 'contains' === $expr['op'] ) { return is_array( $value ) && in_array( $expr['value'], $value, true ); }
		throw new RuntimeException( 'Unsupported frozen condition.' );
	}
}
