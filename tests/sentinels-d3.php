<?php
/**
 * D3 Sentinel unsubscribe lifecycle tests without WordPress.
 */
define( 'ABSPATH', __DIR__ . '/' );

function wp_salt( $scheme = 'auth' ) { return 'badaround-d3-test-' . $scheme; }
function sanitize_text_field( $value ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $value ); }
function absint( $value ) { return abs( (int) $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function rest_url( $path = '' ) { return 'https://example.invalid/wp-json/' . ltrim( $path, '/' ); }

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
}

class BadAround_Sentinel_Repository {
	const STATUS_PENDING      = 'pending';
	const STATUS_ACTIVE       = 'active';
	const STATUS_PAUSED       = 'paused';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';

	public $row;
	public $unsubscribes = 0;

	public function __construct() {
		$this->row = array(
			'id'            => 77,
			'public_id'     => '7f6ce5fc-1959-4fb7-a735-59d8bd4fb067',
			'email'         => 'person@example.invalid',
			'email_hash'    => hash( 'sha256', 'person@example.invalid' ),
			'status'        => self::STATUS_ACTIVE,
			'confirmed_at'  => '2026-10-04 07:00:00',
			'disabled_at'   => null,
			'deleted_at'    => null,
		);
	}

	public function find_by_public_id( $public_id ) {
		return $this->row && $this->row['public_id'] === $public_id ? $this->row : null;
	}

	public function unsubscribe( $id ) {
		if ( ! $this->row || (int) $this->row['id'] !== (int) $id || self::STATUS_ACTIVE !== $this->row['status'] ) {
			return new WP_Error( 'ba_sentinel_unsubscribe_failed' );
		}
		$this->unsubscribes++;
		$this->row['status'] = self::STATUS_UNSUBSCRIBED;
		$this->row['disabled_at'] = '2026-10-04 08:00:00';
		return $this->row;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-sentinel-service.php';

function d3_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
	echo "PASS: {$message}\n";
}

$service = new BadAround_Sentinel_Service();
$ref = new ReflectionClass( $service );
$prop = $ref->getProperty( 'repository' );
$prop->setAccessible( true );
$repo = $prop->getValue( $service );
$sentinel = $repo->row;

$url = $service->unsubscribe_url_for_sentinel( $sentinel );
d3_assert( is_string( $url ), 'unsubscribe URL is generated' );
d3_assert( false === strpos( $url, 'person@example.invalid' ), 'unsubscribe URL does not expose email' );
d3_assert( false !== strpos( $url, $sentinel['public_id'] ), 'unsubscribe URL uses opaque public identifier' );

$parts = explode( '/', rtrim( $url, '/' ) );
$token = end( $parts );
d3_assert( 64 === strlen( $token ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $token ), 'unsubscribe token is 256-bit opaque hex' );

$result = $service->unsubscribe_token( $sentinel['public_id'] . '.' . $token );
d3_assert( 'unsubscribed' === $result, 'valid unsubscribe token disables sentinel' );
d3_assert( BadAround_Sentinel_Repository::STATUS_UNSUBSCRIBED === $repo->row['status'], 'sentinel becomes non-active' );
d3_assert( ! empty( $repo->row['disabled_at'] ), 'disabled_at is recorded' );
d3_assert( 1 === $repo->unsubscribes, 'unsubscribe transition executes once' );

$result = $service->unsubscribe_token( $sentinel['public_id'] . '.' . $token );
d3_assert( 'already-unsubscribed' === $result, 'second use is idempotent' );
d3_assert( 1 === $repo->unsubscribes, 'second use does not repeat transition' );

$result = $service->unsubscribe_token( $sentinel['public_id'] . '.' . str_repeat( 'a', 64 ) );
d3_assert( is_wp_error( $result ) && 'invalid' === $result->get_error_code(), 'invalid token is rejected without state change' );

$matching = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-sentinel-matching-service.php' );
$template = file_get_contents( dirname( __DIR__ ) . '/wordpress/themes/badaround-child/page-sentinelle.php' );
$repo_src = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-sentinel-repository.php' );

d3_assert( false !== strpos( $matching, 'unsubscribe_url_for_sentinel' ), 'D2 email uses D3 unsubscribe mechanism' );
d3_assert( false !== strpos( $matching, 'Disattiva questa Sentinella' ), 'D2 email includes clear unsubscribe CTA' );
d3_assert( false !== strpos( $matching, 'Ricevi questo avviso perché' ), 'D2 email explains why notification was received' );
d3_assert( false !== strpos( $repo_src, 'STATUS_UNSUBSCRIBED' ) && false !== strpos( $repo_src, 'disabled_at' ), 'D2 canonical eligibility state is reused' );
d3_assert( false === strpos( $template, 'Per D1 non vengono ancora inviate notifiche' ), 'obsolete D1 copy is removed' );
d3_assert( false !== strpos( $template, 'non viene resa pubblica' ), 'Sentinel page states identity is not public' );
d3_assert( false !== strpos( $template, 'disattivare in qualsiasi momento' ), 'Sentinel page explains unsubscribe lifecycle' );

echo "D3 unsubscribe lifecycle tests complete.\n";
