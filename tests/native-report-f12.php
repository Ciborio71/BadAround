<?php
define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-schema.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';

function f12_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$schema = BadAround_Report_Schema::definition();
$fields = BadAround_Report_Schema::fields();
$taxonomy = BadAround_Report_Schema::category_subtypes();
$mapping = BadAround_Event_Taxonomy_Map::mapping();

f12_assert( 'badaround-report/v1' === $schema['version'], 'schema version is badaround-report/v1' );
f12_assert( count( $fields ) === count( array_unique( array_keys( $fields ) ) ), 'canonical field keys are unique' );
f12_assert( 6 === count( $taxonomy ), 'taxonomy has six canonical categories' );
f12_assert( 33 === count( BadAround_Report_Schema::all_subtypes() ), 'taxonomy has 33 canonical subtypes' );
f12_assert( array_keys( $taxonomy ) === array_keys( $mapping['categories'] ), 'category enum derives from F1.1 source of truth' );
f12_assert( BadAround_Report_Schema::all_subtypes() === array_keys( $mapping['subtypes'] ), 'subtype enum derives from F1.1 source of truth' );

foreach ( $fields as $key => $definition ) {
	f12_assert( false === strpos( $key, 'field_' ) && false === strpos( $key, 'choice_' ), 'semantic key: ' . $key );
	foreach ( array( 'type','required','nullable','conditions','privacy','exposure','destination','public_projection','validation_rules','normalization','cardinality' ) as $attribute ) {
		f12_assert( array_key_exists( $attribute, $definition ), $key . ' defines ' . $attribute );
	}
}

$condition_count = 0;
foreach ( $fields as $definition ) {
	foreach ( $definition['conditions'] as $group ) {
		$condition_count++;
		foreach ( $group as $condition ) {
			f12_assert( isset( $condition['path'] ) && isset( $fields[ $condition['path'] ] ), 'conditional rule references a canonical semantic field' );
			f12_assert( false === strpos( $condition['path'], 'field_') && false === strpos( $condition['path'], 'choice_'), 'conditional rule has no WPForms identity' );
		}
	}
}

f12_assert( 'external-reference-only' === $fields['location.place_id']['constraints']['identity'], 'external place_id is not primary identity' );
foreach ( array( 'location.region','location.province','location.municipality','location.locality' ) as $geo_key ) {
	f12_assert( isset( $fields[ $geo_key ] ) && 'ba_territorio' === $fields[ $geo_key ]['destination'], 'canonical territory level exists: ' . $geo_key );
}
f12_assert( 5 === $fields['media.items']['constraints']['max_items'], 'media max items is five' );
f12_assert( 5242880 === $fields['media.items']['constraints']['max_bytes_per_item'], 'media max item size is 5 MB' );
f12_assert( in_array( 'heic', $fields['media.items']['constraints']['allowed_extensions'], true ), 'live HEIC policy is represented' );
f12_assert( 'one-report-one-event' === $fields['submission_id']['constraints']['idempotency'], 'submission UUID carries F0 idempotency contract' );

$json = json_encode( $schema );
f12_assert( is_string( $json ) && '' !== $json, 'contract serializes without WPForms runtime' );
f12_assert( false === stripos( $json, 'wpforms' ), 'canonical schema contains no WPForms runtime identity' );

// The WPForms adapter was already deleted by LEG-RETIRE-01 in the certified
// baseline. Its historical field-inventory assertions cannot load a removed
// runtime. Keep this suite focused on the canonical contract and compatibility.
f12_assert( array( 'point', 'street', 'area', 'municipality' ) === $fields['location.public_precision']['constraints']['enum'], 'legacy precision meanings unchanged' );
foreach ( array( 'location.exact_address', 'location.exact_lat', 'location.exact_lng', 'location.public_location_request', 'location.public_location_request_version' ) as $path ) {
	f12_assert( 'private' === $fields[ $path ]['exposure'] && 'never' === $fields[ $path ]['public_projection'], 'private canonical field never automatically projects: ' . $path );
}
f12_assert( 'public-location-request-v1' === $schema['extensions']['public_location_request'], 'new extension has an explicit independent version' );

echo 'F1.2_FIELD_COUNT=' . count( $fields ) . "\n";
echo 'F1.2_ENUM_GROUPS=' . count( BadAround_Report_Schema::enum_groups() ) . "\n";
echo 'F1.2_CONDITIONAL_RULES=' . $condition_count . "\n";
echo "F1.2 Canonical Report Contract tests complete.\n";

