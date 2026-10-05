<?php
/**
 * E2 Community Contribution Moderation tests without WordPress.
 */
define( 'ABSPATH', __DIR__ . '/' );

function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function current_user_can( $cap ) { return in_array( $cap, array( 'ba_moderate_contributions', 'ba_view_private_contributions' ), true ); }
function get_current_user_id() { return 7; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_email( $v ) { return false !== filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function __( $v ) { return $v; }
function get_post_status( $id ) { return 220 === (int) $id ? 'publish' : false; }
function get_post_meta( $id, $key, $single = false ) {
	if ( 220 === (int) $id && '_ba_moderation_status' === $key ) { return 'published'; }
	return '';
}
$GLOBALS['e2_posts'] = array();
$GLOBALS['e2_meta'] = array();
function wp_insert_post( $data, $wp_error = false ) {
	$id = 501;
	$GLOBALS['e2_posts'][$id] = $data;
	return $id;
}
function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['e2_meta'][$post_id][$key] = $value;
	return true;
}
function wp_delete_post( $post_id, $force = false ) {
	unset( $GLOBALS['e2_posts'][$post_id], $GLOBALS['e2_meta'][$post_id] );
	return true;
}

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

class BadAround_Publication_Service { const STATUS_PUBLISHED = 'published'; }
class BadAround_Contribution_Post_Type { const POST_TYPE = 'ba_contributo'; }
class BadAround_Contribution_Media_Repository {
	public function has_pending_review( $id ) { return false; }
	public function materialize_approved( $id, $projection_id ) { return array(); }
}

class BadAround_Audit_Log {
	public static $actions = array();
	public static function record( $type, $id, $action ) { self::$actions[] = $action; return true; }
	public static function transition( $type, $id, $action, $old, $new ) { self::$actions[] = $action . ':' . $old . '>' . $new; return true; }
}

class BadAround_Contribution_Repository {
	const STATUS_PENDING_VERIFICATION = 'pending_verification';
	const STATUS_TO_REVIEW = 'to_review';
	const STATUS_IN_REVIEW = 'in_review';
	const STATUS_ON_HOLD = 'on_hold';
	const STATUS_PUBLISHED = 'published';
	const STATUS_RESERVED = 'reserved';
	const STATUS_REJECTED = 'rejected';
	const STATUS_EXPIRED = 'expired';

	public $row;
	public function __construct() {
		$this->row = array(
			'id' => 91,
			'event_id' => 220,
			'email' => 'private@example.invalid',
			'status' => self::STATUS_TO_REVIEW,
			'contribution_type' => 'sighting',
			'public_identity_mode' => 'public_anonymous',
			'public_display_name' => null,
			'visibility_requested' => 'public',
			'content_original' => 'Testo originale privato con dettagli da non pubblicare automaticamente.',
			'exact_location_text' => 'Via Segreta 12',
			'exact_lat' => '41.1234567',
			'exact_lng' => '12.1234567',
			'public_projection_id' => null,
		);
	}
	public function find_by_id( $id ) { return (int) $id === (int) $this->row['id'] ? $this->row : null; }
	public function save_moderated_draft( $id, $draft ) {
		$this->row['moderated_draft'] = $draft;
		return $this->row;
	}
	public function archive( $id, $reason = '' ) { return true; }
	public function moderate( $id, $expected, $target, array $data ) {
		if ( (int) $id !== (int) $this->row['id'] || $expected !== $this->row['status'] ) {
			return new WP_Error( 'ba_contribution_moderation_update_failed' );
		}
		$this->row['status'] = $target;
		$this->row['visibility_decided'] = $data['visibility_decided'];
		$this->row['public_projection_id'] = $data['public_projection_id'];
		$this->row['moderation_reason'] = $data['reason'];
		return $this->row;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-service.php';

function e2_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = new BadAround_Contribution_Moderation_Service();
$ref = new ReflectionClass( $service );
$prop = $ref->getProperty( 'repository' );
$prop->setAccessible( true );
$repository = $prop->getValue( $service );

$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_IN_REVIEW );
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_IN_REVIEW === $repository->row['status'], 'to_review moves to in_review' );

$result = $service->transition(
	91,
	BadAround_Contribution_Repository::STATUS_PUBLISHED,
	array( 'public_text' => 'Avvistamento segnalato nella zona pubblica dell’evento.' )
);
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_PUBLISHED === $repository->row['status'], 'in_review can publish moderated projection' );
e2_assert( 501 === (int) $repository->row['public_projection_id'], 'published contribution links a separate projection' );
e2_assert( 'ba_contributo' === $GLOBALS['e2_posts'][501]['post_type'], 'projection uses dedicated ba_contributo entity' );
e2_assert( 'Avvistamento segnalato nella zona pubblica dell’evento.' === $GLOBALS['e2_posts'][501]['post_content'], 'projection contains moderator-authored public text' );
e2_assert( false === strpos( $GLOBALS['e2_posts'][501]['post_content'], 'Via Segreta 12' ), 'private exact location is not copied to projection' );
e2_assert( false === strpos( $GLOBALS['e2_posts'][501]['post_content'], 'private@example.invalid' ), 'private email is not copied to projection' );
e2_assert( 'Contributo anonimo' === $GLOBALS['e2_meta'][501]['_ba_public_display_name'], 'anonymous public identity is preserved' );

$repository->row['status'] = BadAround_Contribution_Repository::STATUS_IN_REVIEW;
$repository->row['public_projection_id'] = null;
$repository->row['visibility_requested'] = 'reserved';
$result = $service->transition(
	91,
	BadAround_Contribution_Repository::STATUS_PUBLISHED,
	array( 'public_text' => 'Questo non deve essere pubblicato.' )
);
e2_assert( is_wp_error( $result ) && 'ba_contribution_reserved_cannot_publish' === $result->get_error_code(), 'reserved request cannot be published' );

$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_RESERVED );
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_RESERVED === $repository->row['status'], 'in_review can become reserved' );
e2_assert( empty( $repository->row['public_projection_id'] ), 'reserved contribution creates no public projection' );

$repository->row['status'] = BadAround_Contribution_Repository::STATUS_IN_REVIEW;
$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_ON_HOLD );
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_ON_HOLD === $repository->row['status'], 'in_review can move to on_hold' );

$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_IN_REVIEW );
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_IN_REVIEW === $repository->row['status'], 'on_hold can resume to in_review' );

$result = $service->save_draft( 91, 'Bozza moderata persistente.' );
e2_assert( ! is_wp_error( $result ) && 'Bozza moderata persistente.' === $repository->row['moderated_draft'], 'moderated draft can be saved separately from original content' );

$repository->row['status'] = BadAround_Contribution_Repository::STATUS_IN_REVIEW;
$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_REJECTED );
e2_assert( is_wp_error( $result ) && 'ba_contribution_reason_required' === $result->get_error_code(), 'rejection requires internal reason' );

$result = $service->transition( 91, BadAround_Contribution_Repository::STATUS_REJECTED, array( 'reason' => 'Contenuto non pertinente.' ) );
e2_assert( ! is_wp_error( $result ) && BadAround_Contribution_Repository::STATUS_REJECTED === $repository->row['status'], 'in_review can be rejected with reason' );

$service_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-service.php' );
$post_type_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-post-type.php' );
$admin_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-admin.php' );
$installer = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-installer.php' );

e2_assert( false !== strpos( $post_type_src, "'publicly_queryable' => false" ), 'contribution projection is not directly publicly queryable' );
e2_assert( false !== strpos( $post_type_src, "'show_in_rest' => false" ), 'contribution projection is not exposed through public REST' );
e2_assert( false !== strpos( $service_src, 'validate_public_text' ), 'public projection passes privacy validation' );
$projection_segment = strstr( $service_src, 'private function create_public_projection' );
e2_assert( false !== $projection_segment && false === strpos( $projection_segment, "['content_original']" ), 'public projection does not copy original private content automatically' );
e2_assert( false !== strpos( $admin_src, 'Non copiare automaticamente dati riservati' ), 'moderation UI warns against copying private data' );
e2_assert( false !== strpos( $admin_src, 'Testo pubblico moderato' ), 'publication requires explicit moderator-authored public text' );
e2_assert( false !== strpos( $admin_src, 'Bozza moderata' ), 'moderation UI exposes a separate editable draft' );
e2_assert( false !== strpos( $admin_src, 'Metti in stand-by' ) && false !== strpos( $admin_src, 'Riprendi revisione' ), 'moderation UI exposes hold and resume actions' );
e2_assert( false !== strpos( $admin_src, 'Archivia contributo' ), 'moderation UI exposes soft archive action' );
e2_assert( false !== strpos( $installer, 'moderated_draft longtext' ), 'moderated draft has dedicated schema field' );
e2_assert( false !== strpos( $installer, 'ba_moderate_contributions' ) && false !== strpos( $installer, 'ba_view_private_contributions' ), 'E2 capabilities are versioned' );
e2_assert( 1 === preg_match( "/SCHEMA_VERSION\s*=\s*'1\.[5-9][0-9]*\.0'/", $installer ), 'schema/capability version is E2-or-later' );

echo "E2 contribution moderation tests complete.\n";
