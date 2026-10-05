<?php
/**
 * E1 Community Contribution Core contract tests without WordPress.
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

function wp_salt( $scheme = 'auth' ) { return 'badaround-e1-test-' . $scheme; }
function sanitize_text_field( $value ) { return trim( (string) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_textarea_field( $value ) { return trim( (string) $value ); }
function sanitize_email( $value ) { return strtolower( trim( (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function current_time() { return '2026-10-05 13:00:00'; }

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

class BadAround_Audit_Log {
	public static $actions = array();
	public static function record( $object_type, $object_id, $action ) {
		self::$actions[] = array( $object_type, $object_id, $action );
		return true;
	}
	public static function transition( $object_type, $object_id, $action, $old, $new ) {
		self::$actions[] = array( $object_type, $object_id, $action, $old, $new );
		return true;
	}
}

class BadAround_Contribution_Repository {
	const STATUS_PENDING_VERIFICATION = 'pending_verification';
	const STATUS_TO_REVIEW = 'to_review';
	const STATUS_IN_REVIEW = 'in_review';
	const STATUS_PUBLISHED = 'published';
	const STATUS_RESERVED = 'reserved';
	const STATUS_REJECTED = 'rejected';
	const STATUS_EXPIRED = 'expired';

	public $row;
	public $verified = 0;
	public $expired = 0;

	public function __construct() {
		$this->row = array(
			'id' => 91,
			'public_id' => '4e4b5f0d-8f1d-49b7-9851-6b9cabfcd111',
			'event_id' => 220,
			'email' => 'person@example.invalid',
			'email_hash' => hash_hmac( 'sha256', 'person@example.invalid', wp_salt( 'auth' ) ),
			'status' => self::STATUS_PENDING_VERIFICATION,
			'verification_expires_at' => gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS ),
		);
		$token = hash_hmac( 'sha256', 'contribution-verify|' . $this->row['public_id'] . '|' . $this->row['email_hash'] . '|' . $this->row['verification_expires_at'], wp_salt( 'auth' ) );
		$this->row['verify_token_hash'] = hash( 'sha256', $token );
	}

	public function find_by_public_id( $public_id ) {
		return $this->row && $this->row['public_id'] === $public_id ? $this->row : null;
	}
	public function mark_verified( $id ) {
		if ( ! $this->row || (int) $id !== (int) $this->row['id'] || self::STATUS_PENDING_VERIFICATION !== $this->row['status'] ) {
			return new WP_Error( 'verify_failed' );
		}
		$this->verified++;
		$this->row['status'] = self::STATUS_TO_REVIEW;
		$this->row['email_verified_at'] = current_time( 'mysql', true );
		$this->row['verify_token_hash'] = null;
		return $this->row;
	}
	public function mark_expired( $id ) {
		$this->expired++;
		$this->row['status'] = self::STATUS_EXPIRED;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-service.php';

function e1_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = new BadAround_Contribution_Service();
$ref = new ReflectionClass( $service );
$prop = $ref->getProperty( 'repository' );
$prop->setAccessible( true );
$repository = $prop->getValue( $service );
$row = $repository->row;

$method = $ref->getMethod( 'verification_token' );
$method->setAccessible( true );
$token = $method->invoke( $service, $row['public_id'], $row['email_hash'], $row['verification_expires_at'] );

e1_assert( 64 === strlen( $token ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $token ), 'verification token is opaque 256-bit hex' );
e1_assert( false === strpos( $token, 'person@example.invalid' ), 'token does not expose email' );

$result = $service->verify_token( $row['public_id'] . '.' . $token );
e1_assert( 'confirmed' === $result, 'valid token confirms contribution' );
e1_assert( BadAround_Contribution_Repository::STATUS_TO_REVIEW === $repository->row['status'], 'verified contribution moves only to to_review' );
e1_assert( 1 === $repository->verified, 'verification transition executes once' );
e1_assert( empty( $repository->row['verify_token_hash'] ), 'verification token hash is cleared after verification' );

$result = $service->verify_token( $row['public_id'] . '.' . $token );
e1_assert( 'already-confirmed' === $result, 'second verification is idempotent' );
e1_assert( 1 === $repository->verified, 'second verification does not repeat transition' );

$repository->row['status'] = BadAround_Contribution_Repository::STATUS_PENDING_VERIFICATION;
$repository->row['verify_token_hash'] = hash( 'sha256', $token );
$result = $service->verify_token( $row['public_id'] . '.' . str_repeat( 'a', 64 ) );
e1_assert( is_wp_error( $result ) && 'invalid' === $result->get_error_code(), 'invalid token is rejected' );

$service_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-service.php' );
$repo_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-contribution-repository.php' );
$installer = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-installer.php' );
$core = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-core.php' );

e1_assert( false !== strpos( $installer, 'ba_contributions' ), 'dedicated contribution table is versioned in schema' );
e1_assert( false !== strpos( $installer, "SCHEMA_VERSION = '1.4.0'" ), 'schema version advanced for E1' );
e1_assert( false !== strpos( $service_src, "array( 'public_alias', 'public_anonymous' )" ), 'identity modes are distinct from visibility' );
e1_assert( false !== strpos( $service_src, "array( 'public', 'reserved' )" ), 'public/reserved visibility is explicit' );
e1_assert( false !== strpos( $service_src, "BadAround_Publication_Service::STATUS_PUBLISHED" ), 'contributions accept only fully published ba_evento' );
e1_assert( false !== strpos( $service_src, 'ba_contribution_rl_ip_' ) && false !== strpos( $service_src, 'ba_contribution_rl_em_' ), 'rate limits are contribution-specific' );
e1_assert( false !== strpos( $service_src, 'find_recent_duplicate' ), 'duplicate suppression is scoped to contribution core' );
e1_assert( false === strpos( $repo_src, 'ba_sentinels' ) && false === strpos( $service_src, 'BadAround_Sentinel_Repository' ), 'E1 persistence is not coupled to Sentinels' );
e1_assert( false !== strpos( $core, 'BadAround_Contribution_Service' ), 'contribution service is registered independently' );
e1_assert( false === strpos( $service_src, 'wp_insert_post' ) && false === strpos( $service_src, "post_type' => 'ba_contributo" ), 'E1 does not publish public contribution projections' );

echo "E1 contribution core contract tests complete.\n";
