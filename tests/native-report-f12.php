<?php
define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-schema.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-wpforms-contract-map.php';

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

$wp = BadAround_WPForms_Contract_Map::fields();
$counts = BadAround_WPForms_Contract_Map::counts();
f12_assert( 80 === count( $wp ), 'live WPForms #6 inventory contains 80 field objects' );
f12_assert( 67 === $counts[ BadAround_WPForms_Contract_Map::CANONICAL ], '67 WPForms fields map to canonical domain data' );
f12_assert( 13 === $counts[ BadAround_WPForms_Contract_Map::PRESENTATION_ONLY ], '13 WPForms fields are presentation-only' );
f12_assert( 0 === $counts[ BadAround_WPForms_Contract_Map::LEGACY_ONLY ], 'no live field is legacy-only' );
f12_assert( 0 === $counts[ BadAround_WPForms_Contract_Map::DERIVED ], 'no live user field is classified derived' );
f12_assert( 0 === $counts[ BadAround_WPForms_Contract_Map::INTERNAL ], 'no live user field is classified internal' );
foreach ( $wp as $field_id => $item ) {
	if ( BadAround_WPForms_Contract_Map::CANONICAL !== $item['classification'] ) {
		continue;
	}
	$targets = is_array( $item['canonical'] ) ? $item['canonical'] : array( $item['canonical'] );
	foreach ( $targets as $target ) {
		f12_assert( isset( $fields[ $target ] ), 'WPForms field ' . $field_id . ' maps to canonical field ' . $target );
	}
}

echo 'F1.2_FIELD_COUNT=' . count( $fields ) . "\n";
echo 'F1.2_ENUM_GROUPS=' . count( BadAround_Report_Schema::enum_groups() ) . "\n";
echo 'F1.2_CONDITIONAL_RULES=' . $condition_count . "\n";
echo "F1.2 Canonical Report Contract tests complete.\n";
