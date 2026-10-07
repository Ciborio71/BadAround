<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * F1.5 public anonymous transport adapter for badaround-report/v1.
 *
 * Domain behavior remains in the frozen F1.3/F1.4 services.
 */
class BadAround_Native_Report_REST_Controller {
	const REST_NAMESPACE       = 'badaround/v1';
	const REST_ROUTE           = '/reports';
	const REQUEST_HEADER       = 'x-badaround-intake';
	const REQUEST_HEADER_VALUE = 'badaround-report/v1';
	const MAX_PAYLOAD_BYTES    = 131072; // 128 KiB JSON envelope, no binary upload.
	const RATE_IP_LIMIT        = 12;
	const RATE_IP_TTL          = 900;

	private $golden_path;
	private $media_factory;

	public function __construct( $golden_path = null, $media_factory = null ) {
		$this->media_factory = $media_factory;
		$this->golden_path = $golden_path ?: new BadAround_Native_Report_Golden_Path_Service();
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create' ),
				'permission_callback' => array( $this, 'permission_check' ),
			)
		);
	}

	/**
	 * Anonymous is intentional, but requests must carry the native intake marker
	 * and must not be an explicitly cross-site browser request.
	 */
	public function permission_check( WP_REST_Request $request ) {
		$marker = strtolower( trim( (string) $request->get_header( self::REQUEST_HEADER ) ) );
		if ( self::REQUEST_HEADER_VALUE !== $marker ) {
			return new WP_Error( 'ba_native_request_integrity', 'Request integrity check failed.', array( 'status' => 403 ) );
		}

		$fetch_site = strtolower( trim( (string) $request->get_header( 'sec-fetch-site' ) ) );
		if ( 'cross-site' === $fetch_site ) {
			return new WP_Error( 'ba_native_cross_site', 'Cross-site requests are not accepted.', array( 'status' => 403 ) );
		}

		$origin = trim( (string) $request->get_header( 'origin' ) );
		if ( $origin && ! $this->same_origin( $origin, home_url( '/' ) ) ) {
			return new WP_Error( 'ba_native_cross_origin', 'Cross-origin requests are not accepted.', array( 'status' => 403 ) );
		}

		return true;
	}

	public function rest_create( WP_REST_Request $request ) {
		$request_id = wp_generate_uuid4();
		$started_at = microtime( true );
		BadAround_Audit_Log::record( 'report', 0, 'native_api_request_received', 'native_api', null, $request_id );

		$content_type = strtolower( trim( (string) $request->get_header( 'content-type' ) ) );
		$media_type = trim( explode( ';', $content_type, 2 )[0] );
		if ( 'application/json' !== $media_type ) {
			return $this->error_response( 'unsupported_media_type', null, 415, $request_id );
		}

		$body = (string) $request->get_body();
		if ( '' === trim( $body ) ) {
			return $this->error_response( 'malformed_request', null, 400, $request_id );
		}
		if ( strlen( $body ) > self::MAX_PAYLOAD_BYTES ) {
			return $this->error_response( 'payload_too_large', null, 413, $request_id );
		}

		if ( ! $this->consume_rate_limit() ) {
			BadAround_Audit_Log::record( 'report', 0, 'native_api_rate_limited', 'native_api', null, $request_id );
			return $this->error_response( 'rate_limited', null, 429, $request_id, array( 'retryable' => true ) );
		}

		$root = json_decode( $body );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $root ) ) {
			return $this->error_response( 'malformed_request', null, 400, $request_id );
		}
		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
			return $this->error_response( 'malformed_request', null, 400, $request_id );
		}

		$submission_id = isset( $payload['submission_id'] ) && is_scalar( $payload['submission_id'] )
			? strtolower( trim( (string) $payload['submission_id'] ) )
			: '';
		if ( $submission_id ) {
			BadAround_Audit_Log::record( 'report', 0, 'native_api_submission_received', 'native_api', $submission_id, $request_id );
		}

		try {
			// Empty/absent media delegates directly; no media construction or lookup.
			$items = isset($payload['media']['items']) ? $payload['media']['items'] : null;
			if (is_array($items) && count($items)>0) {
				$validator = new BadAround_Report_Validator();
				$valid = $validator->validate_raw_contract($payload);
				if (!is_wp_error($valid)) {
					$canonical = (new BadAround_Report_Normalizer())->normalize($payload);
					$valid = $validator->validate($canonical);
				}
				if (is_wp_error($valid)) {
					$result = BadAround_Report_Result::from_wp_error($valid);
				} else {
					$home=wp_parse_url(home_url('/'));
					$fetch_site=strtolower(trim((string)$request->get_header('sec-fetch-site')));
					if (!is_ssl() || !isset($home['scheme']) || $home['scheme']!=='https' || ($fetch_site!=='' && !in_array($fetch_site,array('same-origin','none'),true))) {
						return $this->error_response('media_capability_invalid',null,401,$request_id);
					}
					$adapter = $this->media_factory ? call_user_func($this->media_factory) : new BadAround_Native_Media_Report_Adapter();
					$result = $adapter->submit($payload, $request->get_header('x-badaround-media-capability'));
				}
			} else {
				$result = $this->golden_path->submit( $payload );
			}
		} catch ( Throwable $error ) {
			BadAround_Audit_Log::technical_error( 'report', 0, 'native_api_internal_error', 'unexpected_exception', $request_id );
			return $this->error_response( 'internal_error', null, 500, $request_id );
		}

		if ( ! is_array( $result ) || 'success' !== ( isset( $result['status'] ) ? $result['status'] : '' ) ) {
			$code  = isset( $result['error']['code'] ) ? sanitize_key( $result['error']['code'] ) : 'internal_error';
			$field = isset( $result['error']['field'] ) && is_scalar( $result['error']['field'] ) ? (string) $result['error']['field'] : null;
			$status = $this->http_status_for_error( $code );

			BadAround_Audit_Log::record(
				'report',
				0,
				$this->audit_action_for_error( $code ),
				'native_api',
				$code,
				$request_id
			);

			return $this->error_response( $code, $field, $status, $request_id, array( 'retryable' => $this->is_retryable( $code ) ) );
		}

		$duplicate = ! empty( $result['duplicate'] ) || ! empty( $result['persistence']['duplicate'] );
		$event_id  = isset( $result['event_id'] ) ? absint( $result['event_id'] ) : 0;
		$response  = array(
			'status'          => 'success',
			'submission_id'   => isset( $result['submission_id'] ) ? (string) $result['submission_id'] : $submission_id,
			'schema_version'  => BadAround_Report_Schema::VERSION,
			'intake_state'    => 'received_for_moderation',
			'event_id'        => $event_id ?: null,
			'duplicate'       => (bool) $duplicate,
			'next_state'      => 'moderation_pending',
		);

		BadAround_Audit_Log::record(
			'event',
			$event_id,
			$duplicate ? 'native_api_duplicate_completed' : 'native_api_intake_success',
			'native_api',
			null,
			$request_id
		);

		$http = $duplicate ? 200 : 201;
		$rest = new WP_REST_Response( $response, $http );
		$rest->header( 'Cache-Control', 'no-store, private' );
		$rest->header( 'X-Content-Type-Options', 'nosniff' );
		$rest->header( 'X-BadAround-Request-ID', $request_id );
		$rest->header( 'Server-Timing', 'intake;dur=' . max( 0, round( ( microtime( true ) - $started_at ) * 1000, 1 ) ) );
		return $rest;
	}

	private function consume_rate_limit() {
		global $wpdb;

		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key = 'ba_native_report_rl_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$lock_name = 'ba_native_rl_' . substr( hash( 'sha256', $key ), 0, 32 );

		// Serialize transient increments across PHP workers. Fail closed when the
		// short lock cannot be acquired: under contention a 429 is safer than
		// allowing an unbounded anonymous burst.
		$locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 1 ) );
		if ( 1 !== $locked ) {
			return false;
		}

		try {
			$count = (int) get_transient( $key );
			if ( $count >= self::RATE_IP_LIMIT ) {
				return false;
			}
			return (bool) set_transient( $key, $count + 1, self::RATE_IP_TTL );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	private function same_origin( $left, $right ) {
		$a = wp_parse_url( $left );
		$b = wp_parse_url( $right );
		if ( ! is_array( $a ) || ! is_array( $b ) || empty( $a['scheme'] ) || empty( $a['host'] ) || empty( $b['scheme'] ) || empty( $b['host'] ) ) {
			return false;
		}
		$aport = isset( $a['port'] ) ? (int) $a['port'] : ( 'https' === strtolower( $a['scheme'] ) ? 443 : 80 );
		$bport = isset( $b['port'] ) ? (int) $b['port'] : ( 'https' === strtolower( $b['scheme'] ) ? 443 : 80 );
		return strtolower( $a['scheme'] ) === strtolower( $b['scheme'] )
			&& strtolower( $a['host'] ) === strtolower( $b['host'] )
			&& $aport === $bport;
	}

	private function error_response( $code, $field, $status, $request_id, $details = array() ) {
		$body = array(
			'status' => 'error',
			'error'  => array(
				'code'        => sanitize_key( $code ),
				'field'       => $field ? (string) $field : null,
				'message_key' => sanitize_key( $code ),
				'retryable'   => ! empty( $details['retryable'] ),
			),
		);
		$response = new WP_REST_Response( $body, absint( $status ) );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-BadAround-Request-ID', $request_id );
		if ($code==='media_rate_limited') $response->header('Retry-After','900');
		return $response;
	}

	private function http_status_for_error( $code ) {
		if (class_exists('BadAround_Native_Media_REST_Controller') && BadAround_Native_Media_REST_Controller::report_error($code)) return BadAround_Native_Media_REST_Controller::status_code($code);
		if ( 'submission_in_progress' === $code || 'duplicate_submission' === $code ) return 409;
		if ( 'rate_limited' === $code ) return 429;
		if ( in_array( $code, array( 'internal_error', 'intake_failed', 'intake_persistence_failed', 'intake_state_update_failed', 'persistence_failed' ), true ) ) return 500;
		if ( in_array( $code, array( 'malformed_request', 'invalid_payload' ), true ) ) return 400;
		return 422;
	}

	private function audit_action_for_error( $code ) {
		if ( 'rate_limited' === $code ) return 'native_api_rate_limited';
		if ( 'submission_in_progress' === $code || 'duplicate_submission' === $code ) return 'native_api_duplicate';
		if ( 500 === $this->http_status_for_error( $code ) ) return 'native_api_persistence_failed';
		return 'native_api_validation_rejected';
	}

	private function is_retryable( $code ) {
		if (class_exists('BadAround_Native_Media_REST_Controller') && BadAround_Native_Media_REST_Controller::report_error($code)) return BadAround_Native_Media_REST_Controller::retryable($code);
		return in_array( $code, array( 'submission_in_progress', 'rate_limited', 'internal_error', 'intake_failed', 'intake_persistence_failed', 'intake_state_update_failed', 'persistence_failed' ), true );
	}
}
