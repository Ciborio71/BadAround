<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
require_once __DIR__ . '/class-badaround-report-conditional-contract.php';
require_once __DIR__ . '/class-badaround-public-geo-contract.php';

/** V2 deterministic snapshot. Only proven inactive fields are removed. */
class BadAround_Report_V2_Normalizer extends BadAround_Report_Normalizer {
	public function normalize( $report ) {
		if ( ! is_array( $report ) ) { return array(); }
		$radius_aware = BadAround_Public_Geo_Contract::radius_aware( $report['location'] ?? null );
		$request = $radius_aware ? ( $report['location']['public_location_request'] ?? null ) : null;
		$report = BadAround_Public_Geo_Contract::validation_report( $report );
		$fields = BadAround_Report_Conditional_Contract::fields(); $basic = array();
		foreach ( $fields as $path => $field ) {
			if ( $this->has_path( $report, $path ) ) { $this->set_path( $basic, $path, $this->normalize_value( $path, $this->get_path( $report, $path ), $field ) ); }
		}
		$row = BadAround_Report_Conditional_Contract::row( $basic );
		// Invalid category/subtype must survive for validation, never become an empty accepted report.
		if ( ! $row ) { return $basic; }
		$out = array();
		foreach ( $fields as $path => $field ) {
			if ( ! BadAround_Report_Conditional_Contract::active( $path, $basic, $row ) || ! $this->has_path( $basic, $path ) ) { continue; }
			$value = $this->get_path( $basic, $path );
			if ( '' === $value || ( is_array( $value ) && ! $value && 'media.items' !== $path ) ) { continue; }
			$this->set_path( $out, $path, $value );
		}
		if ( $radius_aware ) {
			if ( is_array( $request ) && 'exact_point' === ( $request['mode'] ?? null ) && null === ( $request['radius_m'] ?? null ) ) { unset( $request['radius_m'] ); }
			$out['location']['public_location_request'] = $request;
			$out['location']['public_location_request_version'] = BadAround_Public_Geo_Contract::RADIUS_REQUEST_VERSION;
		}
		return $out;
	}
	protected function normalize_value( $path, $value, $definition ) {
		if ( in_array( $definition['type'], array( 'string', 'phone', 'email', 'enum', 'uuid', 'plate' ), true ) ) {
			if ( ! is_string( $value ) ) { return $value; }
			$value = preg_replace( '/^\s+|\s+$/u', '', $value );
			if ( null === $value ) { return ''; } // Raw validation rejects invalid UTF-8 first.
			if ( in_array( $definition['type'], array( 'email', 'enum', 'uuid' ), true ) ) { return strtolower( $value ); }
			if ( 'plate' === $definition['type'] ) { return strtoupper( preg_replace( '/\s/u', '', $value ) ); }
			return $value;
		}
		if ( 'array' === $definition['type'] && isset( $definition['constraints']['item_enum'] ) && is_array( $value ) ) {
			$items = array_map( static function( $item ) { return is_string( $item ) ? strtolower( trim( $item ) ) : $item; }, $value );
			return array_values( array_unique( $items, SORT_REGULAR ) );
		}
		if ( 'time' === $definition['type'] ) { return is_string( $value ) && preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)(?::00)?$/', trim( $value ), $m ) ? sprintf( '%02d:%02d', $m[1], $m[2] ) : $value; }
		return parent::normalize_value( $path, $value, $definition );
	}
}
