<?php
/** Optional extension tested against real validation, intake and private repository. No live DB. */
require __DIR__ . '/native-report-f13.php';
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
require dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-repository.php';
require dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';

$admin = new BadAround_Moderation_Admin();
function current_user_can( $capability ) { return $GLOBALS['social_private_access'] ?? false; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text ) { return esc_html( $text ); }
ob_start(); $admin->render_private_box( (object) array( 'ID' => 12 ) ); $denied = ob_get_clean();
f13_assert( false !== strpos( $denied, 'Accesso non autorizzato' ) && false === strpos( $denied, 'social-preference' ), 'private moderation evidence is capability gated' );
foreach ( array( null, 'requested', 'declined' ) as $choice ) {
	$input = f13_fixture();
	if ( null !== $choice ) {
		$input['reporter']['social_visibility'] = $choice;
		$input['consents']['social_visibility_version'] = BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
	}
	f13_assert( true === $validator->validate_raw_contract( $input ), 'social raw preflight: ' . ( $choice ?? 'omitted' ) );
	$normalized = $normalizer->normalize( $input );
	f13_assert( true === $validator->validate( $normalized ), 'social normalized validation: ' . ( $choice ?? 'omitted' ) );
	$repo = new F13_Fake_Repository();
	$service = f13_service( $repo );
	$first = $service->intake( $input );
	$again = $service->intake( $input );
	f13_assert( 'success' === $first['status'] && 'success' === $again['status'] && 1 === $repo->create_count && 1 === $repo->persist_count, 'social intake retry creates no duplicate: ' . ( $choice ?? 'omitted' ) );
	if ( null !== $choice ) {
		$changed = $input; $changed['reporter']['social_visibility'] = 'requested' === $choice ? 'declined' : 'requested';
		$result = $service->intake( $changed );
		f13_assert( 'success' !== $result['status'] && $repo->stored['reporter']['social_visibility'] === $choice, 'same UUID cannot silently change social preference' );
	}
	$GLOBALS['wpdb'] = new class {
		public $prefix = 'wp_'; public $row;
		public function __construct() { $parts = (new BadAround_Report_Repository())->native_source_identity(f13_fixture()['submission_id']); $this->row = array('source_type'=>'native','source_form_id'=>$parts['form_id'],'source_entry_id'=>$parts['entry_id'],'content_original'=>null,'payload_hash'=>null); }
		public function update( $table, $data, $where ) { $this->row = array_merge($this->row,$data); return 1; }
		public function prepare( $sql, ...$args ) { return $sql; }
		public function get_var( $sql ) { return strpos($sql,'options') !== false ? null : $this->row['content_original']; }
		public function get_row( $sql, $mode = null ) { $row = array_merge($this->row,array('id'=>77)); return $mode === ARRAY_A ? $row : (object)$row; }
	};
	$repository = new BadAround_Report_Repository();
	f13_assert( true === $repository->persist_canonical_private_data( 77, $normalized ), 'real private repository persists optional evidence' );
	$stored = json_decode( $GLOBALS['wpdb']->row['content_original'], true );
	f13_assert( $stored['canonical'] === $normalized && $repository->native_payload_matches_report( 77, $normalized ), 'private JSON reread and idempotency include exact social choice' );
	f13_assert( '2026-10-06 12:00:00' === $GLOBALS['wpdb']->row['consented_at'], 'acquisition time comes from server, not client' );
	$description = $admin->social_preference_description( $stored );
	f13_assert( false !== strpos( $description, null === $choice ? 'Non acquisita' : ( 'requested' === $choice ? 'Richiesta da valutare' : 'No:' ) ), 'moderation distinguishes yes/no/legacy omission' );
	$GLOBALS['social_private_access'] = true;
	ob_start(); $admin->render_private_box( (object) array( 'ID' => 12 ) ); $html = ob_get_clean();
	f13_assert( false !== strpos( $html, esc_html( $description ) ) && false !== strpos( $html, '2026-10-06 12:00:00' ), 'actual private moderation box renders choice and server time' );
}
foreach ( array( true, array( 'requested' ), 'yes', 'authorized' ) as $invalid ) {
	$input = f13_fixture(); $input['reporter']['social_visibility'] = $invalid;
	$raw = $validator->validate_raw_contract( $input );
	$valid = true === $raw ? $validator->validate( $normalizer->normalize( $input ) ) : $raw;
	f13_assert( is_wp_error( $valid ), 'invalid social type or enum rejected' );
}
foreach ( array( null, 'future-v2' ) as $invalid_version ) {
	$input = f13_fixture(); $input['reporter']['social_visibility'] = 'requested';
	if ( null !== $invalid_version ) { $input['consents']['social_visibility_version'] = $invalid_version; }
	f13_assert( is_wp_error( $validator->validate( $normalizer->normalize( $input ) ) ), 'selected preference requires recognized statement version' );
}
$input = f13_fixture(); $input['consents']['social_visibility_version'] = BadAround_Report_Schema::SOCIAL_PREFERENCE_VERSION;
$normalized = $normalizer->normalize( $input );
f13_assert( ! isset( $normalized['consents']['social_visibility_version'] ), 'orphan metadata cannot become a request' );
foreach ( array( 'reporter.social_visibility', 'consents.social_visibility_version' ) as $path ) {
	$definition = BadAround_Report_Schema::field_definition( $path );
	f13_assert( 'private' === $definition['exposure'] && 'never' === $definition['public_projection'], 'social evidence is private: ' . $path );
}
$unknown = array( 'canonical' => array( 'reporter' => array( 'social_visibility' => 'requested' ), 'consents' => array( 'social_visibility_version' => 'future-v2' ) ) );
f13_assert( false !== strpos( $admin->social_preference_description( $unknown ), 'Non acquisita' ), 'moderation does not treat unknown scope as permission' );
