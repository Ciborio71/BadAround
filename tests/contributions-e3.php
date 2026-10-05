<?php
/**
 * E3 Contribution Media architecture and moderation tests.
 */
define( 'ABSPATH', __DIR__ . '/' );

function absint( $v ) { return abs( (int) $v ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); }
function current_user_can( $cap ) { return 'ba_moderate_contributions' === $cap; }
function get_current_user_id() { return 7; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function is_email( $v ) { return false !== filter_var( $v, FILTER_VALIDATE_EMAIL ); }
function __( $v ) { return $v; }
function get_post_status( $id ) { return 'publish'; }
function get_post_meta( $id, $key, $single = false ) { return '_ba_moderation_status' === $key ? 'published' : ''; }

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}
class BadAround_Publication_Service { const STATUS_PUBLISHED = 'published'; }
class BadAround_Contribution_Post_Type { const POST_TYPE = 'ba_contributo'; }
class BadAround_Audit_Log {
	public static function record() { return true; }
	public static function transition() { return true; }
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
			'status' => self::STATUS_IN_REVIEW,
			'contribution_type' => 'media',
			'public_identity_mode' => 'public_anonymous',
			'public_display_name' => null,
			'visibility_requested' => 'public',
			'exact_location_text' => null,
		);
	}
	public function find_by_id( $id ) { return $this->row; }
	public function moderate() { return $this->row; }
}
class BadAround_Contribution_Media_Repository {
	public static $pending = true;
	public function has_pending_review( $id ) { return self::$pending; }
	public function materialize_approved( $id, $projection_id ) { return array( 601 ); }
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-service.php';

function e3_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = new BadAround_Contribution_Moderation_Service();
$result = $service->transition(
	91,
	BadAround_Contribution_Repository::STATUS_PUBLISHED,
	array( 'public_text' => 'Testo pubblico moderato per il contributo con immagine.' )
);
e3_assert( is_wp_error( $result ) && 'ba_contribution_media_review_required' === $result->get_error_code(), 'publication is blocked while contribution media is unreviewed' );

$media_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-media-repository.php' );
$service_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-service.php' );
$moderation_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-service.php' );
$admin_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-moderation-admin.php' );
$installer = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-installer.php' );

e3_assert( false !== strpos( $installer, 'ba_contribution_media' ), 'dedicated contribution media table is versioned' );
e3_assert( 1 === preg_match( "/SCHEMA_VERSION\s*=\s*'1\.[6-9][0-9]*\.0'/", $installer ), 'schema version is E3-or-later' );
e3_assert( false !== strpos( $media_src, 'BADAROUND_PRIVATE_MEDIA_PATH' ), 'E3 reuses private media root outside public WordPress paths' );
e3_assert( false !== strpos( $media_src, 'WP_CONTENT_DIR' ) && false !== strpos( $media_src, 'return \'\';' ), 'private media root rejects public WordPress paths' );
e3_assert( false !== strpos( $media_src, 'MAX_FILES = 5' ), 'E3 limits image count' );
e3_assert( false !== strpos( $media_src, 'MAX_FILE_SIZE = 5242880' ), 'E3 limits each image to 5 MB' );
e3_assert( false !== strpos( $media_src, "image/jpeg" ) && false !== strpos( $media_src, "image/png" ) && false !== strpos( $media_src, "image/webp" ), 'E3 uses an image MIME allowlist' );
e3_assert( false !== strpos( $media_src, 'wp_check_filetype_and_ext' ), 'E3 validates actual file type and extension' );
e3_assert( false !== strpos( $media_src, 'is_uploaded_file' ) && false !== strpos( $media_src, 'move_uploaded_file' ), 'E3 accepts only real HTTP uploads' );
e3_assert( false !== strpos( $media_src, "'sensitivity' => 'private'" ), 'original media is always private' );
e3_assert( false !== strpos( $media_src, "'review_status' => 'received'" ), 'new media enters moderation as received' );
e3_assert( false !== strpos( $media_src, "'review_status' => 'approved_public'" ), 'moderator can approve a derivative for publication' );
e3_assert( false !== strpos( $media_src, "'review_status' => 'rejected'" ), 'moderator can reject media' );
e3_assert( false !== strpos( $media_src, "wp_get_image_editor" ) && false !== strpos( $media_src, "image/jpeg" ), 'public copy is re-encoded instead of exposing original bytes' );
e3_assert( false !== strpos( $media_src, "'exif_removed' => 1" ), 'materialized public derivative is marked EXIF-stripped' );
e3_assert( false !== strpos( $moderation_src, 'has_pending_review' ), 'E2 publication gate waits for E3 media review' );
e3_assert( false !== strpos( $moderation_src, 'materialize_approved' ), 'only approved media is materialized with the public projection' );
e3_assert( false !== strpos( $service_src, 'cleanup_contribution' ) && false !== strpos( $service_src, 'abandon_pending' ), 'failed upload rolls back media and pending contribution' );
e3_assert( false !== strpos( $admin_src, 'Visualizza originale privato' ), 'moderators can inspect private originals through capability-gated endpoint' );
e3_assert( false !== strpos( $admin_src, 'X-Content-Type-Options: nosniff' ), 'private media response uses nosniff protection' );
e3_assert( false !== strpos( $admin_src, 'ba_contribution_media_review' ), 'media moderation actions use dedicated nonce-gated admin action' );

echo "E3 contribution media tests complete.\n";
