<?php
define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['f15_routes'] = array();
$GLOBALS['f15_transients'] = array();
$GLOBALS['f15_audit'] = array();

function __( $text ) { return $text; }
function add_action() { return true; }
function register_rest_route( $namespace, $route, $args ) {
	$GLOBALS['f15_routes'][ $namespace . $route ] = $args;
	return true;
}
function home_url( $path = '/' ) { return 'https://staging.badaround.it' . $path; }
function wp_generate_uuid4() { static $i = 0; $i++; return sprintf( '11111111-1111-4111-8111-%012d', $i ); }
function wp_salt( $scheme = 'auth' ) { return 'f15-test-salt-' . $scheme; }
function get_transient( $key ) { return isset( $GLOBALS['f15_transients'][ $key ] ) ? $GLOBALS['f15_transients'][ $key ] : false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['f15_transients'][ $key ] = $value; return true; }
function sanitize_text_field( $value ) { return trim( strip_tags( (string) $value ) ); }
function wp_unslash( $value ) { return $value; }
function wp_parse_url( $value ) { return parse_url( $value ); }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function absint( $value ) { return abs( (int) $value ); }

class WP_REST_Server {
	const CREATABLE = 'POST';
}
class WP_Error {
	private $code;
	private $data;
	public function __construct( $code, $message = '', $data = null ) { $this->code = $code; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_data() { return $this->data; }
}
class WP_REST_Request {
	private $headers;
	private $body;
	public function __construct( $body = '', $headers = array() ) {
		$this->body = $body;
		$this->headers = array();
		foreach ( $headers as $key => $value ) { $this->headers[ strtolower( $key ) ] = $value; }
	}
	public function get_header( $name ) { $key = strtolower( $name ); return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : ''; }
	public function get_body() { return $this->body; }
}
class WP_REST_Response {
	public $data;
	public $status;
	public $headers = array();
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
	public function header( $name, $value ) { $this->headers[ $name ] = $value; }
}
class BadAround_Report_Schema {
	const VERSION = 'badaround-report/v1';
}
class BadAround_Audit_Log {
	public static function record() { $GLOBALS['f15_audit'][] = func_get_args(); return true; }
	public static function technical_error() { $GLOBALS['f15_audit'][] = func_get_args(); return true; }
}
class F15_Fake_Golden_Path {
	public $result;
	public $throw = false;
	public $received = null;
	public function __construct( $result = null ) { $this->result = $result; }
	public function submit( $payload ) {
		$this->received = $payload;
		if ( $this->throw ) { throw new RuntimeException( 'SECRET_SQL_STACK email@example.test AB123CD' ); }
		if ( is_callable( $this->result ) ) { return call_user_func( $this->result, $payload ); }
		return $this->result;
	}
}

require_once dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php';

function f15_assert( $condition, $message ) {
	if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
	echo "PASS: {$message}\n";
}
function f15_request( $payload, $extra_headers = array() ) {
	$headers = array_merge(
		array(
			'Content-Type' => 'application/json; charset=utf-8',
			'X-BadAround-Intake' => 'badaround-report/v1',
			'Origin' => 'https://staging.badaround.it',
			'Sec-Fetch-Site' => 'same-origin',
		),
		$extra_headers
	);
	$body = is_string( $payload ) ? $payload : json_encode( $payload );
	return new WP_REST_Request( $body, $headers );
}
function f15_payload() {
	return array(
		'schema_version' => 'badaround-report/v1',
		'submission_id' => '123e4567-e89b-42d3-a456-426614174000',
		'event' => array( 'category' => 'hazard', 'subtype' => 'hazard_road_obstruction' ),
		'reporter' => array( 'email' => 'private@example.test', 'phone' => '+39 333 0000000' ),
		'location' => array( 'exact_address' => 'Via Privata 10', 'exact_lat' => 41.1, 'exact_lng' => 12.2 ),
		'content' => array( 'description' => 'Ostacolo sulla carreggiata.' ),
	);
}
function f15_success( $duplicate = false ) {
	return array(
		'status' => 'success',
		'submission_id' => '123e4567-e89b-42d3-a456-426614174000',
		'schema_version' => 'badaround-report/v1',
		'report_id' => 77,
		'event_id' => 901,
		'duplicate' => $duplicate,
		'normalized_payload' => f15_payload(),
		'privacy' => array( 'reporter.email' => array( 'privacy' => 'private' ) ),
		'persistence' => array( 'completed' => true, 'duplicate' => $duplicate ),
	);
}
function f15_error( $code, $field = null, $secret = null ) {
	return array(
		'status' => 'error',
		'error' => array(
			'code' => $code,
			'field' => $field,
			'message_key' => $code,
			'details' => $secret ? array( 'secret' => $secret, 'sql' => 'SELECT * FROM private' ) : array(),
		),
	);
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.44';

$fake = new F15_Fake_Golden_Path( f15_success( false ) );
$controller = new BadAround_Native_Report_REST_Controller( $fake );
$controller->register_rest_routes();
f15_assert( isset( $GLOBALS['f15_routes']['badaround/v1/reports'] ), 'route is registered' );
f15_assert( 'POST' === $GLOBALS['f15_routes']['badaround/v1/reports']['methods'], 'wrong HTTP methods are not registered' );
f15_assert( array( $controller, 'permission_check' ) === $GLOBALS['f15_routes']['badaround/v1/reports']['permission_callback'], 'anonymous permission gate is intentional, not __return_true' );

$valid_request = f15_request( f15_payload() );
f15_assert( true === $controller->permission_check( $valid_request ), 'valid anonymous same-origin request passes integrity gate' );
$response = $controller->rest_create( $valid_request );
f15_assert( 201 === $response->status, 'first valid request returns HTTP 201' );
f15_assert( 'success' === $response->data['status'] && 'received_for_moderation' === $response->data['intake_state'], 'success response contract is stable' );
f15_assert( 'moderation_pending' === $response->data['next_state'], 'resulting event semantic state is Da moderare' );
f15_assert( 901 === $response->data['event_id'], 'technical event identifier is returned' );
f15_assert( ! isset( $response->data['report_id'] ) && ! isset( $response->data['normalized_payload'] ) && ! isset( $response->data['privacy'] ), 'success response exposes no private report payload' );
f15_assert( f15_payload() === $fake->received, 'controller forwards canonical JSON unchanged to frozen Golden Path service' );

$missing_marker = f15_request( f15_payload(), array( 'X-BadAround-Intake' => '' ) );
f15_assert( $controller->permission_check( $missing_marker ) instanceof WP_Error, 'missing request-integrity marker is rejected' );
$cross = f15_request( f15_payload(), array( 'Origin' => 'https://evil.example', 'Sec-Fetch-Site' => 'cross-site' ) );
f15_assert( $controller->permission_check( $cross ) instanceof WP_Error, 'cross-site browser request is rejected' );

$wrong_ct = f15_request( f15_payload(), array( 'Content-Type' => 'text/plain' ) );
$r = $controller->rest_create( $wrong_ct );
f15_assert( 415 === $r->status && 'unsupported_media_type' === $r->data['error']['code'], 'wrong content type rejected' );

$r = $controller->rest_create( f15_request( '{"broken":' ) );
f15_assert( 400 === $r->status && 'malformed_request' === $r->data['error']['code'], 'malformed JSON rejected' );

$oversized = str_repeat( 'x', BadAround_Native_Report_REST_Controller::MAX_PAYLOAD_BYTES + 1 );
$r = $controller->rest_create( f15_request( $oversized ) );
f15_assert( 413 === $r->status && 'payload_too_large' === $r->data['error']['code'], 'oversized payload rejected before domain intake' );

$error_cases = array(
	'invalid_schema_version' => array( 'field' => 'schema_version', 'status' => 422 ),
	'missing_required_field' => array( 'field' => 'submission_id', 'status' => 422 ),
	'invalid_payload' => array( 'field' => null, 'status' => 400 ),
	'unknown_field' => array( 'field' => 'arbitrary', 'status' => 422 ),
	'invalid_category_subtype' => array( 'field' => 'event.subtype', 'status' => 422 ),
	'invalid_submission_id' => array( 'field' => 'submission_id', 'status' => 422 ),
	'invalid_location' => array( 'field' => 'location.exact_lat', 'status' => 422 ),
);
foreach ( $error_cases as $code => $expected ) {
	$f = new F15_Fake_Golden_Path( f15_error( $code, $expected['field'] ) );
	$c = new BadAround_Native_Report_REST_Controller( $f );
	$rr = $c->rest_create( f15_request( f15_payload() ) );
	f15_assert( $expected['status'] === $rr->status && $code === $rr->data['error']['code'], 'domain error mapped safely: ' . $code );
}

$retryFake = new F15_Fake_Golden_Path( f15_success( true ) );
$retryController = new BadAround_Native_Report_REST_Controller( $retryFake );
$retry = $retryController->rest_create( f15_request( f15_payload() ) );
f15_assert( 200 === $retry->status && true === $retry->data['duplicate'], 'same-id retry returns deterministic completed response without new object semantics' );

$busyFake = new F15_Fake_Golden_Path( f15_error( 'submission_in_progress', 'submission_id' ) );
$busy = ( new BadAround_Native_Report_REST_Controller( $busyFake ) )->rest_create( f15_request( f15_payload() ) );
f15_assert( 409 === $busy->status && true === $busy->data['error']['retryable'], 'in-progress/concurrent duplicate returns HTTP 409 retryable response' );

$conflictFake = new F15_Fake_Golden_Path( f15_error( 'duplicate_submission', 'submission_id' ) );
$conflict = ( new BadAround_Native_Report_REST_Controller( $conflictFake ) )->rest_create( f15_request( f15_payload() ) );
f15_assert( 409 === $conflict->status, 'same id with conflicting payload maps to HTTP 409' );

$GLOBALS['f15_transients'] = array();
$rateController = new BadAround_Native_Report_REST_Controller( new F15_Fake_Golden_Path( f15_success() ) );
for ( $i = 0; $i < BadAround_Native_Report_REST_Controller::RATE_IP_LIMIT; $i++ ) {
	$ok = $rateController->rest_create( f15_request( f15_payload() ) );
	f15_assert( $ok->status < 400, 'request below rate limit accepted #' . ( $i + 1 ) );
}
$limited = $rateController->rest_create( f15_request( f15_payload() ) );
f15_assert( 429 === $limited->status && 'rate_limited' === $limited->data['error']['code'], 'rate limit enforced with structured response' );

$GLOBALS['f15_transients'] = array();
$secret = 'private@example.test AB123CD Via Privata 10 41.1 12.2';
$secretFake = new F15_Fake_Golden_Path( f15_error( 'persistence_failed', null, $secret ) );
$secretResponse = ( new BadAround_Native_Report_REST_Controller( $secretFake ) )->rest_create( f15_request( f15_payload() ) );
f15_assert( false === strpos( json_encode( $secretResponse->data ), 'private@example.test' ) && false === strpos( json_encode( $secretResponse->data ), 'AB123CD' ), 'private data absent from error output' );

$throwFake = new F15_Fake_Golden_Path();
$throwFake->throw = true;
$internal = ( new BadAround_Native_Report_REST_Controller( $throwFake ) )->rest_create( f15_request( f15_payload() ) );
f15_assert( 500 === $internal->status && 'internal_error' === $internal->data['error']['code'], 'internal exception is sanitized' );
f15_assert( false === strpos( json_encode( $internal->data ), 'SECRET_SQL_STACK' ), 'stack/SQL/private exception detail is not exposed' );

$negativePayloads = array(
	'script_markup' => array_replace_recursive( f15_payload(), array( 'content' => array( 'description' => '<script>alert(1)</script>Ostacolo' ) ) ),
	'sql_like_string' => array_replace_recursive( f15_payload(), array( 'content' => array( 'description' => "Robert'); DROP TABLE ba_reports;--" ) ) ),
);
foreach ( $negativePayloads as $name => $payload ) {
	$f = new F15_Fake_Golden_Path( f15_success() );
	$c = new BadAround_Native_Report_REST_Controller( $f );
	$rr = $c->rest_create( f15_request( $payload ) );
	f15_assert( 201 === $rr->status && $payload === $f->received, $name . ' is transported as data and delegated to frozen sanitization/validation' );
	f15_assert( false === strpos( json_encode( $rr->data ), '<script>' ) && false === strpos( json_encode( $rr->data ), 'DROP TABLE' ), $name . ' is never reflected in public response' );
}

$nestedFake = new F15_Fake_Golden_Path( function( $payload ) {
	if ( isset( $payload['unexpected']['nested'] ) ) return f15_error( 'unknown_field', 'unexpected' );
	return f15_success();
} );
$nested = f15_payload(); $nested['unexpected'] = array( 'nested' => array( 'x' => 1 ) );
$nestedResponse = ( new BadAround_Native_Report_REST_Controller( $nestedFake ) )->rest_create( f15_request( $nested ) );
f15_assert( 422 === $nestedResponse->status && 'unknown_field' === $nestedResponse->data['error']['code'], 'unexpected nested object reaches strict F1.3 unknown-field rejection' );

$longFake = new F15_Fake_Golden_Path( f15_error( 'too_long', 'content.description' ) );
$longPayload = f15_payload(); $longPayload['content']['description'] = str_repeat( 'A', 20000 );
$longResponse = ( new BadAround_Native_Report_REST_Controller( $longFake ) )->rest_create( f15_request( $longPayload ) );
f15_assert( 422 === $longResponse->status, 'excessively long field is rejected by domain validation mapping' );

$source = file_get_contents( dirname( __DIR__ ) . '/wordpress/plugins/badaround-core/includes/class-badaround-native-report-rest-controller.php' );
f15_assert( false === stripos( $source, 'wpforms' ), 'public native endpoint has no WPForms runtime dependency' );
f15_assert( false === strpos( $source, 'wp_insert_term' ) && false === strpos( $source, 'wp_set_object_terms' ), 'controller performs no taxonomy mutation' );
f15_assert( false === strpos( $source, "post_status' => 'publish" ) && false === strpos( $source, 'wp_publish_post' ), 'controller cannot auto-publish' );
f15_assert( false === strpos( $source, 'Access-Control-Allow-Origin' ), 'controller does not open wildcard CORS' );
f15_assert( false === strpos( $source, '__return_true' ), 'permission callback is not unconditional' );
f15_assert( false === strpos( $source, 'normalized_payload' ) || false === strpos( $source, "'normalized_payload' =>" ), 'controller does not construct private normalized payload in response' );

$all_audit = json_encode( $GLOBALS['f15_audit'] );
f15_assert( false === strpos( $all_audit, 'private@example.test' ) && false === strpos( $all_audit, 'AB123CD' ) && false === strpos( $all_audit, 'Via Privata 10' ), 'audit contains no private payload fields' );

echo "F1.5_ROUTE=/wp-json/badaround/v1/reports\n";
echo "F1.5_DUPLICATE_REPORTS=0 (delegated invariant: F1.3/F1.4 regression suites)\n";
echo "F1.5_DUPLICATE_EVENTS=0 (delegated invariant: F1.4 regression suite)\n";
echo "F1.5_AUTO_PUBLISH=0\n";
echo "F1.5 Native Submission API tests complete.\n";
