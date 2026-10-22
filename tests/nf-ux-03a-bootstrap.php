<?php
/** Run with wp eval-file after normal active-plugin loading. No class preloads. */
function nf03a_check( $ok, $message ) {
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	echo "PASS: $message\n";
}
$nf03a_core = WP_PLUGIN_DIR . '/badaround-core/';
nf03a_check( in_array( realpath( $nf03a_core . 'badaround-core.php' ), get_included_files(), true ), 'Standard active Core entry loaded by WordPress' );
// This disposable CI WordPress starts without the canonical term fixtures.
$nf03a_registry = ( new BadAround_Event_Taxonomy_Initializer() )->run();
nf03a_check( ! is_wp_error( $nf03a_registry ), 'Isolated canonical registry initialized' );
require getenv( 'NF03A_THEME_CONFIG' );
if ( '1' === getenv( 'NF03A_EXPECT_ABSENT' ) ) {
	nf03a_check( ! class_exists( 'BadAround_Report_Conditional_Contract', false ), 'Baseline class absent before Native config' );
	try {
		badaround_native_report_config();
		throw new RuntimeException( 'Baseline unexpectedly rendered Native configuration' );
	} catch ( Error $error ) {
		nf03a_check( str_contains( $error->getMessage(), 'BadAround_Report_Conditional_Contract' ), 'Original Native runtime failure reproduced' );
	}
	return;
}
nf03a_check( class_exists( 'BadAround_Report_Conditional_Contract', false ), 'Class exists without manual include' );
$nf03a_included = get_included_files();
nf03a_check( array_search( realpath( $nf03a_core . 'includes/class-badaround-report-schema.php' ), $nf03a_included, true ) < array_search( realpath( $nf03a_core . 'includes/class-badaround-report-conditional-contract.php' ), $nf03a_included, true ), 'Schema loaded before conditional contract' );
$nf03a_hash = 'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c';
nf03a_check( BadAround_Report_Conditional_Contract::SHA256 === $nf03a_hash && hash_file( 'sha256', $nf03a_core . 'contracts/nf-conditional-v1.0.json' ) === $nf03a_hash, 'Frozen constant and exact JSON bytes' );
$nf03a_definition = BadAround_Report_Conditional_Contract::definition();
nf03a_check( count( $nf03a_definition['matrix'] ) === 33, 'Definition loads' );
$nf03a_config = badaround_native_report_config();
$nf03a_expected = array_column( array_filter( $nf03a_definition['matrix'], static function ( $row ) { return $row['emergency']['automatic_subtype_notice']; } ), 'subtype' );
nf03a_check( count( $nf03a_expected ) === 9 && $nf03a_config['emergencyNotice']['subtypes'] === $nf03a_expected, 'Native configuration exposes exact nine frozen automatic triggers' );
$nf03a_danger = $nf03a_config['emergencyNotice']['dangerValues'];
sort( $nf03a_danger );
nf03a_check( $nf03a_danger === array( 'unsure', 'yes' ), 'Native global triggers exactly yes and unsure' );
nf03a_check( $nf03a_config['schemaVersion'] === 'badaround-report/v1', 'Native still emits v1' );
$nf03a_components = BadAround_Report_Version_Registry::components( array( 'schema_version' => 'badaround-report/v1' ) );
nf03a_check( get_class( $nf03a_components[0] ) === 'BadAround_Report_Normalizer' && get_class( $nf03a_components[1] ) === 'BadAround_Report_Validator', 'V1 retains original normalizer and validator' );
nf03a_check( ! class_exists( 'BadAround_Report_V2_Normalizer', false ) && ! class_exists( 'BadAround_Report_V2_Validator', false ), 'Contract availability does not activate v2 intake' );
