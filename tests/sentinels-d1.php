<?php
/**
 * D1 Sentinel lifecycle tests runnable without a WordPress installation.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

function wp_salt( $scheme = 'auth' ) {
	return 'badaround-test-secret-' . $scheme;
}

function sanitize_text_field( $value ) {
	return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value );
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

class WP_Error {
	private $code;
	public function __construct( $code, $message = '', $data = null ) {
		$this->code = $code;
	}
	public function get_error_code() {
		return $this->code;
	}
}

class BadAround_Audit_Log {
	public static function record() {
		return true;
	}
}

class BadAround_Sentinel_Repository {
	const STATUS_PENDING      = 'pending';
	const STATUS_ACTIVE       = 'active';
	const STATUS_PAUSED       = 'paused';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';

	public $row;
	public $activations = 0;

	public function __construct() {
		$this->row = null;
	}

	public function find_by_public_id( $public_id ) {
		if ( ! $this->row || $this->row['public_id'] !== $public_id ) {
			return null;
		}
		return $this->row;
	}

	public function activate( $id ) {
		if ( ! $this->row || (int) $this->row['id'] !== (int) $id ) {
			return new WP_Error( 'activation-failed' );
		}
		$this->activations++;
		$this->row['status'] = self::STATUS_ACTIVE;
		$this->row['confirmed_at'] = gmdate( 'Y-m-d H:i:s' );
		$this->row['verification_expires_at'] = null;
		return $this->row;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-sentinel-service.php';

function ba_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = new BadAround_Sentinel_Service();
$service_reflection = new ReflectionClass( $service );

$token_method = $service_reflection->getMethod( 'verification_token' );
$token_method->setAccessible( true );

$repo_property = $service_reflection->getProperty( 'repository' );
$repo_property->setAccessible( true );
$repo = $repo_property->getValue( $service );

$public_id = '7f6ce5fc-1959-4fb7-a735-59d8bd4fb067';
$email_hash = hash( 'sha256', 'test@example.invalid' );
$expires = gmdate( 'Y-m-d H:i:s', time() + 3600 );

$token = $token_method->invoke( $service, $public_id, $email_hash, $expires );
ba_assert( 64 === strlen( $token ), 'verification token has 256-bit hex length' );
ba_assert( 1 === preg_match( '/^[a-f0-9]{64}$/', $token ), 'verification token is opaque hexadecimal data' );
ba_assert( false === strpos( $token, 'test@example.invalid' ), 'verification token does not expose email' );

$other = $token_method->invoke( $service, $public_id, hash( 'sha256', 'other@example.invalid' ), $expires );
ba_assert( ! hash_equals( $token, $other ), 'token changes when protected identity input changes' );

$repo->row = array(
	'id'                      => 42,
	'public_id'               => $public_id,
	'email_hash'              => $email_hash,
	'status'                  => BadAround_Sentinel_Repository::STATUS_PENDING,
	'verify_token_hash'       => hash( 'sha256', $token ),
	'verification_expires_at' => $expires,
);

$result = $service->verify_token( $public_id . '.' . $token );
ba_assert( 'confirmed' === $result, 'valid token activates pending sentinel' );
ba_assert( BadAround_Sentinel_Repository::STATUS_ACTIVE === $repo->row['status'], 'state transitions pending to active' );
ba_assert( 1 === $repo->activations, 'activation occurs exactly once' );

$result = $service->verify_token( $public_id . '.' . $token );
ba_assert( 'already-active' === $result, 'repeated valid token is safely idempotent' );
ba_assert( 1 === $repo->activations, 'repeated token does not reactivate sentinel' );

$result = $service->verify_token( $public_id . '.' . str_repeat( 'a', 64 ) );
ba_assert( is_wp_error( $result ) && 'invalid' === $result->get_error_code(), 'wrong token cannot claim active sentinel' );

$expired_at = gmdate( 'Y-m-d H:i:s', time() - 60 );
$expired_token = $token_method->invoke( $service, $public_id, $email_hash, $expired_at );
$repo->row = array(
	'id'                      => 43,
	'public_id'               => $public_id,
	'email_hash'              => $email_hash,
	'status'                  => BadAround_Sentinel_Repository::STATUS_PENDING,
	'verify_token_hash'       => hash( 'sha256', $expired_token ),
	'verification_expires_at' => $expired_at,
);
$result = $service->verify_token( $public_id . '.' . $expired_token );
ba_assert( is_wp_error( $result ) && 'expired' === $result->get_error_code(), 'expired token is rejected' );

$repo->row = array(
	'id'                      => 44,
	'public_id'               => $public_id,
	'email_hash'              => $email_hash,
	'status'                  => BadAround_Sentinel_Repository::STATUS_PENDING,
	'verify_token_hash'       => hash( 'sha256', $token ),
	'verification_expires_at' => $expires,
);
$result = $service->verify_token( $public_id . '.' . str_repeat( 'b', 64 ) );
ba_assert( is_wp_error( $result ) && 'invalid' === $result->get_error_code(), 'tampered token is rejected' );

$result = $service->verify_token( 'malformed-token' );
ba_assert( is_wp_error( $result ) && 'invalid' === $result->get_error_code(), 'malformed token is rejected' );

echo "D1 local lifecycle tests complete.\n";
