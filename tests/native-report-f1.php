<?php
/**
 * F1 Canonical Domain Foundation tests runnable without WordPress.
 */

define( 'ABSPATH', __DIR__ . '/' );

function __( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function sanitize_email( $value ) { return filter_var( (string) $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) { return false !== filter_var( (string) $value, FILTER_VALIDATE_EMAIL ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_time( $type, $gmt = false ) {
	if ( 'timestamp' === $type ) {
		return strtotime( '2026-10-06 12:00:00 UTC' );
	}
	return '2026-10-06 12:00:00';
}
function wp_date( $format, $timestamp = null ) {
	return gmdate( $format, $timestamp ?: strtotime( '2026-10-06 12:00:00 UTC' ) );
}
function wp_generate_uuid4() { return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_unslash( $value ) { return $value; }
function wp_salt( $scheme = 'auth' ) { return 'f1-test-salt-' . $scheme; }

class WP_Error {
	private $code;
	private $message;
	private $data;
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
		$this->message = $message;
		$this->data = $data;
	}
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}

class WP_Term {
	public $term_id;
	public $parent;
	public $slug;
	public function __construct( $id = 1, $parent = 0, $slug = '' ) {
		$this->term_id = $id;
		$this->parent = $parent;
		$this->slug = $slug;
	}
}

class BadAround_Event_Post_Type {
	const POST_TYPE = 'ba_evento';
	const EVENT_TYPE_TAX = 'ba_tipo_evento';
	const TERRITORY_TAX = 'ba_territorio';
}

class BadAround_Audit_Log {
	public static $records = array();
	public static function record() { self::$records[] = func_get_args(); return true; }
	public static function technical_error() { self::$records[] = func_get_args(); return true; }
}

class BadAround_Media_Repository {}

$GLOBALS['ba_test_posts'] = array();
function wp_insert_post( $args, $wp_error = false ) {
	$id = 900 + count( $GLOBALS['ba_test_posts'] );
	$GLOBALS['ba_test_posts'][ $id ] = $args;
	return $id;
}
function wp_set_object_terms() { return array( 1 ); }

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-schema.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-persistence-service.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-normalizer.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-validator.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-repository.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-report-intake-service.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-wpforms-field-mapper.php';
require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-wpforms-report-adapter.php';

function ba_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

function canonical_fixture() {
	return array(
		'schema_version' => 'badaround-report/v1',
		'submission_id' => '123e4567-e89b-42d3-a456-426614174000',
		'event' => array(
			'category' => 'hazard',
			'subtype' => 'hazard_road_obstruction',
		),
		'reporter' => array(
			'relationship' => 'territory_observer',
			'first_name' => 'Mario',
			'last_name' => 'Rossi',
			'email' => 'mario@example.invalid',
			'phone' => '+39 333 1234567',
			'public_identity_mode' => 'anonymous',
			'contact_preference' => 'none',
		),
		'location' => array(
			'exact_address' => 'Via Roma 10, Pomezia',
			'exact_lat' => 41.669,
			'exact_lng' => 12.501,
			'place_id' => 'place-test',
			'area_label' => 'Pomezia',
			'public_precision' => 'area',
			'place_type' => 'road_sidewalk',
			'immediate_danger' => 'no',
		),
		'time' => array(
			'mode' => 'exact',
			'date' => '2026-10-01',
			'knowledge' => 'unknown',
		),
		'content' => array(
			'description' => 'Ostacolo presente sulla carreggiata e visibile dalla strada.',
		),
		'damage' => array(
			'status' => 'no',
		),
		'authority' => array(
			'status' => 'no',
		),
		'media' => array(
			'availability' => 'no',
			'items' => array(),
		),
		'reward' => array(
			'status' => 'none',
		),
		'consents' => array(
			'truthfulness' => true,
			'media_rights' => true,
			'publication_rules' => true,
			'terms' => true,
			'privacy' => true,
			'version' => 'wpforms-6-v3.0-2026-10-02',
		),
	);
}

$schema = BadAround_Report_Schema::definition();
ba_assert( 'badaround-report/v1' === $schema['version'], 'canonical schema is versioned independently from WPForms' );
ba_assert(
	BadAround_Report_Schema::PRIVACY_PRIVATE === BadAround_Report_Schema::field_definition( 'location.exact_address' )['privacy'],
	'exact address is server-classified PRIVATE'
);
ba_assert(
	BadAround_Report_Schema::PRIVACY_QUARANTINE === BadAround_Report_Schema::field_definition( 'media.items' )['privacy'],
	'media intake is server-classified QUARANTINE'
);

$validator = new BadAround_Report_Validator();
$valid = $validator->validate_and_normalize( canonical_fixture() );
ba_assert( ! is_wp_error( $valid ), 'valid canonical hazard report passes authoritative validation' );
ba_assert( ! isset( $valid['client_public_override'] ), 'unknown client keys do not cross canonical boundary' );

$forged = canonical_fixture();
$forged['vehicle'] = array(
	'type' => 'car',
	'make' => 'Should disappear',
	'plate_knowledge' => 'full',
	'plate_raw' => 'AB123CD',
);
$forged['client_public_override'] = true;
$normalized = $validator->validate_and_normalize( $forged );
ba_assert( ! is_wp_error( $normalized ), 'inactive branch values do not make otherwise valid payload fail' );
ba_assert( empty( $normalized['vehicle'] ), 'inactive vehicle branch is stripped server-side' );
ba_assert( ! isset( $normalized['client_public_override'] ), 'client cannot inject an undeclared public field' );

$mismatch = canonical_fixture();
$mismatch['event']['subtype'] = 'vehicle_stolen';
$error = $validator->validate_and_normalize( $mismatch );
ba_assert(
	is_wp_error( $error ) && 'ba_report_category_subtype_mismatch' === $error->get_error_code(),
	'category/subtype mismatch is rejected'
);

$reward = canonical_fixture();
$reward['reward'] = array(
	'status' => 'fixed',
	'conditions' => 'Restituzione verificata',
	'expires_on' => '2026-11-01',
	'confirmed' => true,
);
$error = $validator->validate_and_normalize( $reward );
ba_assert(
	is_wp_error( $error ) && 'ba_report_required_field' === $error->get_error_code(),
	'fixed reward requires amount server-side'
);

$vehicle = canonical_fixture();
$vehicle['event'] = array( 'category' => 'vehicle', 'subtype' => 'vehicle_stolen' );
$vehicle['vehicle'] = array(
	'type' => 'car',
	'plate_knowledge' => 'full',
	'plate_raw' => 'ab 123 cd',
	'color' => 'black',
);
$vehicle['witness'] = array( 'status' => 'unknown' );
$vehicle_valid = $validator->validate_and_normalize( $vehicle );
ba_assert( ! is_wp_error( $vehicle_valid ), 'vehicle branch validates with required vehicle fields' );
ba_assert( 'AB123CD' === $vehicle_valid['vehicle']['plate_raw'], 'plate is normalized server-side' );
$vehicle_meta = ( new BadAround_Report_Intake_Service(
	new F1_Fake_Repository(),
	new BadAround_Report_Validator(),
	new F1_Fake_Taxonomy(),
	new F1_Fake_Territory(),
	new F1_Fake_Publication()
) )->event_meta( $vehicle_valid );
ba_assert( 'f38-c9' === $vehicle_meta['_ba_vehicle_type'], 'canonical vehicle type preserves certified downstream storage code' );
ba_assert( 'Nero' === $vehicle_meta['_ba_vehicle_color'], 'canonical vehicle color preserves certified downstream display value' );

$future = canonical_fixture();
$future['time']['date'] = '2026-10-07';
$error = $validator->validate_and_normalize( $future );
ba_assert(
	is_wp_error( $error ) && 'ba_report_future_date' === $error->get_error_code(),
	'future event date is rejected server-side'
);

$repo = new BadAround_Report_Repository();
$id1 = $repo->native_source_identity( '123e4567-e89b-42d3-a456-426614174000' );
$id2 = $repo->native_source_identity( '123e4567-e89b-42d3-a456-426614174000' );
$id3 = $repo->native_source_identity( '123e4567-e89b-42d3-a456-426614174001' );
ba_assert( ! is_wp_error( $id1 ) && $id1 === $id2, 'native UUID projects to deterministic existing-schema source identity' );
ba_assert( $id1 !== $id3, 'different native UUID produces a different source identity' );

class F1_Fake_Repository {
	public $event_id = 0;
	public $persist_count = 0;
	public $stored_canonical = null;
	public $stored_payload = null;
	public function native_source_identity( $submission_id ) { return array( 'form_id' => 101, 'entry_id' => 202 ); }
	public function create_native_intake_record( $submission_id ) { return 77; }
	public function create_intake_record( $form_id, $entry_id ) { return 77; }
	public function acquire_native_lock( $submission_id ) { return true; }
	public function release_native_lock( $submission_id ) {}
	public function acquire_source_lock( $form_id, $entry_id ) { return true; }
	public function release_source_lock( $form_id, $entry_id ) {}
	public function event_id_for_report( $report_id ) { return $this->event_id; }
	public function persist_canonical_private_data( $report_id, $canonical, $payload = null ) { $this->persist_count++; $this->stored_canonical = $canonical; $this->stored_payload = $payload; return true; }
	public function native_payload_matches_report( $report_id, $canonical ) { return $canonical === $this->stored_canonical; }
	public function link_event( $report_id, $event_id ) { $this->event_id = $event_id; return true; }
}
class F1_Fake_Taxonomy {
	public function assign( $post_id, $report ) { return array( 'category_term_id' => 1, 'subtype_term_id' => 2 ); }
}
class F1_Fake_Territory {
	public function resolve( $area, $address ) { return 42; }
}
class F1_Fake_Publication {
	public function prepare_public_projection( $post_id ) { return true; }
}

$GLOBALS['ba_test_posts'] = array();
$fake_repo = new F1_Fake_Repository();
$service = new BadAround_Report_Intake_Service(
	$fake_repo,
	new BadAround_Report_Validator(),
	new F1_Fake_Taxonomy(),
	new F1_Fake_Territory(),
	new F1_Fake_Publication()
);
$first = $service->ingest( canonical_fixture(), array( 'type' => 'native' ) );
$second = $service->ingest( canonical_fixture(), array( 'type' => 'native' ) );
ba_assert( ! is_wp_error( $first ) && false === $first['duplicate'], 'first native UUID creates domain intake once' );
ba_assert( ! is_wp_error( $second ) && true === $second['duplicate'], 'retry with same native UUID is idempotent' );
ba_assert( 1 === count( $GLOBALS['ba_test_posts'] ), 'same submission_id produces exactly one ba_evento' );
ba_assert( 1 === $fake_repo->persist_count, 'same submission_id produces exactly one private persistence write' );

$conflict_payload = canonical_fixture();
$conflict_payload['content']['description'] = 'Payload diverso con lo stesso submission id.';
$conflict = $service->ingest( $conflict_payload, array( 'type' => 'native' ) );
ba_assert(
	is_wp_error( $conflict ) && 'ba_report_idempotency_conflict' === $conflict->get_error_code(),
	'same submission_id with a different payload is rejected as an idempotency conflict'
);

$meta = $service->event_meta( canonical_fixture() );
ba_assert( 'f32-c6' === $meta['_ba_public_location_precision'], 'canonical area precision preserves certified downstream storage code' );
ba_assert( 'f16-c4' === $meta['_ba_time_precision'], 'canonical exact time mode preserves certified downstream storage code' );

$mapping = BadAround_Event_Taxonomy_Map::mapping();
ba_assert( 'veicoli' === $mapping['categories']['vehicle'], 'canonical vehicle category maps to existing BadAround taxonomy slug' );
ba_assert( 'veicolo-rubato' === $mapping['subtypes']['vehicle_stolen'], 'canonical vehicle_stolen maps to existing subtype slug' );

$adapter = new BadAround_WPForms_Report_Adapter();
$uuid_a = $adapter->wpforms_submission_id( 6, 123 );
$uuid_b = $adapter->wpforms_submission_id( 6, 123 );
$uuid_c = $adapter->wpforms_submission_id( 6, 124 );
ba_assert( $uuid_a === $uuid_b, 'WPForms compatibility adapter creates deterministic canonical submission UUID' );
ba_assert( $uuid_a !== $uuid_c, 'different WPForms entry maps to different canonical UUID' );
ba_assert( 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid_a ), 'WPForms canonical submission identity is UUIDv5-shaped' );

$wp_fields = array(
	2 => array( 'value' => 'Pericoli', 'value_raw' => 'hazard' ),
	8 => array( 'value' => 'Ostacolo pericoloso sulla carreggiata', 'value_raw' => 'hazard_road_obstruction' ),
	11 => array( 'value' => 'Voglio segnalare una situazione presente nel territorio' ),
	16 => array( 'value' => 'In una data precisa' ),
	17 => array( 'value' => '01/10/2026' ),
	18 => array( 'value' => 'No, non conosco l’orario' ),
	29 => array( 'value' => 'Pomezia' ),
	31 => array( 'value' => 'Via Roma 10, Pomezia', 'lat' => 41.669, 'lng' => 12.501, 'place_id' => 'place-test' ),
	32 => array( 'value' => 'Mostra soltanto la zona o il quartiere' ),
	33 => array( 'value' => 'Strada o marciapiede' ),
	34 => array( 'value' => 'No' ),
	55 => array( 'value' => 'Ostacolo presente sulla carreggiata e visibile dalla strada.' ),
	56 => array( 'value' => 'No' ),
	60 => array( 'value' => 'No' ),
	64 => array( 'value' => 'No' ),
	65 => array( 'value' => 'No' ),
	73 => array( 'first' => 'Mario', 'last' => 'Rossi', 'value' => 'Mario Rossi' ),
	74 => array( 'value' => 'mario@example.invalid' ),
	76 => array( 'value' => 'Segnalazione anonima' ),
	78 => array( 'value' => 'No, non desidero ricevere messaggi' ),
	81 => array( 'value' => '1' ),
	82 => array( 'value' => '1' ),
	83 => array( 'value' => '1' ),
	84 => array( 'value' => '1' ),
	85 => array( 'value' => '1' ),
);
$wp_form = array(
	'id' => 6,
	'fields' => array(
		2 => array( 'choices' => array( 9 => array( 'label' => 'Pericoli', 'value' => 'hazard' ) ) ),
		8 => array( 'choices' => array( 68 => array( 'label' => 'Ostacolo pericoloso sulla carreggiata', 'value' => 'hazard_road_obstruction' ) ) ),
		11 => array( 'choices' => array( 90 => array( 'label' => 'Voglio segnalare una situazione presente nel territorio' ) ) ),
		16 => array( 'choices' => array( 4 => array( 'label' => 'In una data precisa' ) ) ),
		18 => array( 'choices' => array( 6 => array( 'label' => 'No, non conosco l’orario' ) ) ),
		32 => array( 'choices' => array( 6 => array( 'label' => 'Mostra soltanto la zona o il quartiere' ) ) ),
		33 => array( 'choices' => array( 8 => array( 'label' => 'Strada o marciapiede' ) ) ),
		34 => array( 'choices' => array( 20 => array( 'label' => 'No' ) ) ),
		56 => array( 'choices' => array( 5 => array( 'label' => 'No' ) ) ),
		60 => array( 'choices' => array( 14 => array( 'label' => 'No' ) ) ),
		64 => array( 'choices' => array( 14 => array( 'label' => 'No' ) ) ),
		65 => array( 'choices' => array( 18 => array( 'label' => 'No' ) ) ),
		76 => array( 'choices' => array( 7 => array( 'label' => 'Segnalazione anonima' ) ) ),
		78 => array( 'choices' => array( 10 => array( 'label' => 'No, non desidero ricevere messaggi' ) ) ),
		81 => array( 'choices' => array( 1 => array( 'label' => 'Dichiaro' ) ) ),
		82 => array( 'choices' => array( 1 => array( 'label' => 'Dichiaro' ) ) ),
		83 => array( 'choices' => array( 1 => array( 'label' => 'Confermo' ) ) ),
		84 => array( 'choices' => array( 1 => array( 'label' => 'Accetto' ) ) ),
		85 => array( 'choices' => array( 1 => array( 'label' => 'Privacy' ) ) ),
	),
);
$wp_canonical = $adapter->to_canonical( $wp_fields, array(), $wp_form, 123 );
ba_assert( 'hazard' === $wp_canonical['event']['category'], 'WPForms category ID is confined to adapter and becomes canonical hazard' );
ba_assert( 'hazard_road_obstruction' === $wp_canonical['event']['subtype'], 'WPForms subtype ID becomes canonical event key' );
ba_assert( 'area' === $wp_canonical['location']['public_precision'], 'WPForms location precision becomes semantic canonical enum' );
ba_assert( 'anonymous' === $wp_canonical['reporter']['public_identity_mode'], 'WPForms public identity choice becomes semantic canonical enum' );
ba_assert( BadAround_WPForms_Field_Mapper::FORM_SCHEMA_VERSION === $wp_canonical['consents']['version'], 'WPForms compatibility path preserves legacy consent version' );
$wp_validated = $validator->validate_and_normalize( $wp_canonical );
ba_assert( ! is_wp_error( $wp_validated ), 'WPForms adapter output satisfies canonical server validator' );

$legacy_payload = $adapter->legacy_payload( $wp_fields, array(), $wp_form );
$legacy_meta = ( new BadAround_WPForms_Field_Mapper() )->event_meta( $legacy_payload );
$new_meta = $service->event_meta( $wp_validated );
foreach ( array( '_ba_time_precision', '_ba_public_location_precision', '_ba_public_place_name', '_ba_reward_available', '_ba_occurred_date' ) as $meta_key ) {
	ba_assert(
		( isset( $legacy_meta[ $meta_key ] ) ? $legacy_meta[ $meta_key ] : null ) === ( isset( $new_meta[ $meta_key ] ) ? $new_meta[ $meta_key ] : null ),
		'WPForms canonical adapter preserves downstream meta ' . $meta_key
	);
}

$wp_repo = new F1_Fake_Repository();
$wp_service = new BadAround_Report_Intake_Service(
	$wp_repo,
	new BadAround_Report_Validator(),
	new F1_Fake_Taxonomy(),
	new F1_Fake_Territory(),
	new F1_Fake_Publication()
);
$wp_result = $wp_service->ingest(
	$wp_canonical,
	array(
		'type' => 'wpforms',
		'form_id' => 6,
		'entry_id' => 123,
		'legacy_payload' => $legacy_payload,
	)
);
ba_assert( ! is_wp_error( $wp_result ), 'WPForms source can feed the canonical domain intake service' );
ba_assert( $legacy_payload === $wp_repo->stored_payload, 'WPForms compatibility path preserves the legacy private content envelope byte-semantically' );

echo "F1 Native Report canonical foundation tests complete.\n";


/**
 * F1.1 Canonical Event Taxonomy Gate.
 * The expected subtype count is always derived from BadAround_Report_Schema.
 */
$GLOBALS['ba_f11_terms'] = array();
$GLOBALS['ba_f11_meta'] = array();
$GLOBALS['ba_f11_next_id'] = 1;
$GLOBALS['ba_f11_mutations'] = 0;

function ba_f11_reset_taxonomy() {
	$GLOBALS['ba_f11_terms'] = array();
	$GLOBALS['ba_f11_meta'] = array();
	$GLOBALS['ba_f11_next_id'] = 1;
	$GLOBALS['ba_f11_mutations'] = 0;
}
function ba_f11_seed_term( $slug, $parent = 0, $canonical_key = '' ) {
	$id = $GLOBALS['ba_f11_next_id']++;
	$GLOBALS['ba_f11_terms'][ $id ] = new WP_Term( $id, $parent, $slug );
	if ( '' !== $canonical_key ) {
		$GLOBALS['ba_f11_meta'][ $id ]['_ba_canonical_event_key'] = $canonical_key;
	}
	return $id;
}
function get_terms( $args ) {
	$out = array();
	foreach ( $GLOBALS['ba_f11_terms'] as $term ) {
		if ( isset( $args['slug'] ) && $args['slug'] !== $term->slug ) {
			continue;
		}
		if ( isset( $args['meta_key'], $args['meta_value'] ) ) {
			$value = isset( $GLOBALS['ba_f11_meta'][ $term->term_id ][ $args['meta_key'] ] )
				? $GLOBALS['ba_f11_meta'][ $term->term_id ][ $args['meta_key'] ]
				: '';
			if ( (string) $args['meta_value'] !== (string) $value ) {
				continue;
			}
		}
		$out[] = $term;
	}
	return $out;
}
function get_term_meta( $term_id, $key, $single = false ) {
	return isset( $GLOBALS['ba_f11_meta'][ $term_id ][ $key ] ) ? $GLOBALS['ba_f11_meta'][ $term_id ][ $key ] : '';
}
function add_term_meta( $term_id, $key, $value, $unique = false ) {
	if ( $unique && isset( $GLOBALS['ba_f11_meta'][ $term_id ][ $key ] ) ) {
		return false;
	}
	$GLOBALS['ba_f11_meta'][ $term_id ][ $key ] = $value;
	$GLOBALS['ba_f11_mutations']++;
	return 10000 + $term_id;
}
function wp_insert_term( $name, $taxonomy, $args = array() ) {
	$slug = isset( $args['slug'] ) ? $args['slug'] : '';
	$parent = isset( $args['parent'] ) ? (int) $args['parent'] : 0;
	foreach ( $GLOBALS['ba_f11_terms'] as $term ) {
		if ( $term->slug === $slug ) {
			return new WP_Error( 'term_exists', 'Term exists', array( 'term_id' => $term->term_id ) );
		}
	}
	$id = $GLOBALS['ba_f11_next_id']++;
	$GLOBALS['ba_f11_terms'][ $id ] = new WP_Term( $id, $parent, $slug );
	$GLOBALS['ba_f11_mutations']++;
	return array( 'term_id' => $id, 'term_taxonomy_id' => $id );
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-initializer.php';

function ba_f11_subtype_count() {
	$count = 0;
	foreach ( BadAround_Report_Schema::category_subtypes() as $subtypes ) {
		$count += count( $subtypes );
	}
	return $count;
}

$f11_schema = BadAround_Report_Schema::category_subtypes();
$f11_mapping = BadAround_Event_Taxonomy_Map::mapping();
ba_assert( 6 === count( $f11_schema ), 'F1.1 schema defines six canonical categories' );
ba_assert( ba_f11_subtype_count() === count( $f11_mapping['subtypes'] ), 'F1.1 subtype count is derived from schema without magic number' );

ba_f11_reset_taxonomy();
$f11_first = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( ! is_wp_error( $f11_first ), 'F1.1 bootstrap succeeds on controlled empty taxonomy' );
ba_assert( 6 === $f11_first['category_count'], 'F1.1 materializes all six canonical categories' );
ba_assert( ba_f11_subtype_count() === $f11_first['subtype_count'], 'F1.1 materializes every schema subtype' );
ba_assert( 6 + ba_f11_subtype_count() === $f11_first['created'], 'F1.1 empty taxonomy creates each canonical term exactly once' );

$f11_ids_after_first = array_keys( $GLOBALS['ba_f11_terms'] );
$f11_mutations_before_second = $GLOBALS['ba_f11_mutations'];
$f11_second = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( ! is_wp_error( $f11_second ), 'F1.1 second bootstrap succeeds' );
ba_assert( 0 === $f11_second['mutation_count'], 'F1.1 second bootstrap reports zero mutations' );
ba_assert( $f11_mutations_before_second === $GLOBALS['ba_f11_mutations'], 'F1.1 second bootstrap changes no taxonomy state' );
ba_assert( $f11_ids_after_first === array_keys( $GLOBALS['ba_f11_terms'] ), 'F1.1 second bootstrap preserves term IDs' );

$f11_runtime = new BadAround_Event_Taxonomy_Map();
foreach ( $f11_schema as $f11_category => $f11_subtypes ) {
	$f11_category_terms = get_terms( array(
		'taxonomy' => 'ba_tipo_evento',
		'hide_empty' => false,
		'meta_key' => '_ba_canonical_event_key',
		'meta_value' => $f11_category,
	) );
	ba_assert( 1 === count( $f11_category_terms ), 'F1.1 category key unique: ' . $f11_category );
	ba_assert( 0 === (int) $f11_category_terms[0]->parent, 'F1.1 category top-level: ' . $f11_category );
	ba_assert( $f11_runtime->resolve_category( $f11_category ) instanceof WP_Term, 'F1.1 runtime resolves category: ' . $f11_category );

	foreach ( $f11_subtypes as $f11_subtype ) {
		$f11_terms = get_terms( array(
			'taxonomy' => 'ba_tipo_evento',
			'hide_empty' => false,
			'meta_key' => '_ba_canonical_event_key',
			'meta_value' => $f11_subtype,
		) );
		ba_assert( 1 === count( $f11_terms ), 'F1.1 subtype key unique: ' . $f11_subtype );
		ba_assert( (int) $f11_category_terms[0]->term_id === (int) $f11_terms[0]->parent, 'F1.1 subtype parent correct: ' . $f11_subtype );
		$f11_before_runtime = $GLOBALS['ba_f11_mutations'];
		ba_assert( $f11_runtime->resolve_subtype( $f11_subtype, $f11_category_terms[0] ) instanceof WP_Term, 'F1.1 runtime resolves subtype: ' . $f11_subtype );
		ba_assert( $f11_before_runtime === $GLOBALS['ba_f11_mutations'], 'F1.1 runtime resolution is read-only: ' . $f11_subtype );
	}
}

ba_f11_reset_taxonomy();
$f11_vehicle = ba_f11_seed_term( 'veicoli', 0, 'vehicle' );
$f11_legacy_subtype = ba_f11_seed_term( 'veicolo-rubato', $f11_vehicle );
$f11_partial = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( ! is_wp_error( $f11_partial ), 'F1.1 safely completes a partially populated taxonomy' );
ba_assert( 1 <= $f11_partial['adopted'], 'F1.1 adopts a unique slug + parent candidate' );
ba_assert( 'vehicle_stolen' === get_term_meta( $f11_legacy_subtype, '_ba_canonical_event_key', true ), 'F1.1 safe adoption adds canonical identity' );

ba_f11_reset_taxonomy();
$f11_vehicle = ba_f11_seed_term( 'veicoli', 0, 'vehicle' );
ba_f11_seed_term( 'veicolo-rubato', $f11_vehicle, 'vehicle_stolen' );
ba_f11_seed_term( 'duplicate-vehicle-stolen', $f11_vehicle, 'vehicle_stolen' );
$f11_conflict = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( is_wp_error( $f11_conflict ) && 'ba_canonical_taxonomy_conflict' === $f11_conflict->get_error_code(), 'F1.1 duplicate canonical key triggers STOP' );
ba_assert( 0 === $GLOBALS['ba_f11_mutations'], 'F1.1 duplicate-key conflict is detected before mutation' );

ba_f11_reset_taxonomy();
$f11_public_space = ba_f11_seed_term( 'spazi-pubblici', 0, 'public_space' );
ba_f11_seed_term( 'veicolo-rubato', $f11_public_space );
$f11_conflict = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( is_wp_error( $f11_conflict ), 'F1.1 wrong-parent slug candidate triggers STOP' );
ba_assert( 0 === $GLOBALS['ba_f11_mutations'], 'F1.1 wrong-parent conflict is detected before mutation' );

ba_f11_reset_taxonomy();
$f11_vehicle = ba_f11_seed_term( 'veicoli', 0, 'vehicle' );
ba_f11_seed_term( 'veicolo-rubato', $f11_vehicle, 'some_other_key' );
$f11_conflict = ( new BadAround_Event_Taxonomy_Initializer() )->run();
ba_assert( is_wp_error( $f11_conflict ), 'F1.1 incompatible canonical meta triggers STOP' );
ba_assert( 0 === $GLOBALS['ba_f11_mutations'], 'F1.1 incompatible-meta conflict is detected before mutation' );

$f11_mapper_source = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php' );
ba_assert( false === strpos( $f11_mapper_source, 'wp_insert_term' ), 'F1.1 runtime mapper contains no term creation call' );
ba_assert( false === strpos( $f11_mapper_source, 'update_term_meta' ), 'F1.1 runtime mapper contains no term-meta update call' );
ba_assert( false === strpos( $f11_mapper_source, 'add_term_meta' ), 'F1.1 runtime mapper contains no term-meta creation call' );

echo "F1.1 Canonical Event Taxonomy Gate tests complete.\n";
