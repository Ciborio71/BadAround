<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Service {
	const REST_NAMESPACE = 'badaround/v1';
	const REST_ROUTE = '/contributions';
	const VERIFY_TTL = DAY_IN_SECONDS;
	const DUPLICATE_WINDOW = HOUR_IN_SECONDS;
	const RATE_IP_LIMIT = 8;
	const RATE_IP_TTL = 15 * MINUTE_IN_SECONDS;
	const RATE_EMAIL_EVENT_LIMIT = 4;
	const RATE_EMAIL_EVENT_TTL = HOUR_IN_SECONDS;

	private $repository;

	public function __construct() {
		$this->repository = new BadAround_Contribution_Repository();
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'badaround_contribution_send_verification', array( $this, 'send_verification_email' ), 10, 1 );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'rest_create' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/verify/(?P<public_id>[a-f0-9-]{36})/(?P<token>[a-f0-9]{64})',
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'rest_verify' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_create( WP_REST_Request $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$event = get_post( $event_id );
		if ( ! $event || BadAround_Event_Post_Type::POST_TYPE !== $event->post_type || 'publish' !== $event->post_status || BadAround_Publication_Service::STATUS_PUBLISHED !== sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) ) ) {
			return new WP_Error( 'ba_contribution_invalid_event', 'La segnalazione non è disponibile per i contributi.', array( 'status' => 400 ) );
		}

		$email = strtolower( trim( sanitize_email( (string) $request->get_param( 'email' ) ) ) );
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'ba_contribution_invalid_email', 'Inserisci un indirizzo email valido.', array( 'status' => 400 ) );
		}

		$type = sanitize_key( (string) $request->get_param( 'contribution_type' ) );
		if ( ! in_array( $type, array( 'information', 'sighting', 'witness', 'media', 'detail' ), true ) ) {
			return new WP_Error( 'ba_contribution_invalid_type', 'Seleziona un tipo di contributo valido.', array( 'status' => 400 ) );
		}

		$content = trim( sanitize_textarea_field( (string) $request->get_param( 'content' ) ) );
		if ( mb_strlen( $content ) < 10 || mb_strlen( $content ) > 5000 ) {
			return new WP_Error( 'ba_contribution_invalid_content', 'Descrivi l’informazione con almeno 10 caratteri.', array( 'status' => 400 ) );
		}

		$identity_mode = sanitize_key( (string) $request->get_param( 'public_identity_mode' ) );
		if ( ! in_array( $identity_mode, array( 'public_alias', 'public_anonymous' ), true ) ) {
			$identity_mode = 'public_anonymous';
		}
		$display_name = trim( sanitize_text_field( (string) $request->get_param( 'public_display_name' ) ) );
		if ( 'public_alias' === $identity_mode && ( mb_strlen( $display_name ) < 2 || mb_strlen( $display_name ) > 80 ) ) {
			return new WP_Error( 'ba_contribution_invalid_alias', 'Inserisci un nome o alias valido.', array( 'status' => 400 ) );
		}
		if ( 'public_alias' !== $identity_mode ) {
			$display_name = '';
		}

		$visibility = sanitize_key( (string) $request->get_param( 'visibility_requested' ) );
		if ( ! in_array( $visibility, array( 'public', 'reserved' ), true ) ) {
			$visibility = 'public';
		}
		$consent_privacy = rest_sanitize_boolean( $request->get_param( 'consent_privacy' ) );
		if ( ! $consent_privacy ) {
			return new WP_Error( 'ba_contribution_privacy_required', 'È necessario accettare l’informativa privacy.', array( 'status' => 400 ) );
		}

		$rate = $this->consume_rate_limit( $email, $event_id );
		if ( is_wp_error( $rate ) ) { return $rate; }

		$email_hash = hash_hmac( 'sha256', $email, wp_salt( 'auth' ) );
		$normalized_payload = array(
			'event_id' => $event_id,
			'type' => $type,
			'content' => $content,
			'observed_at' => sanitize_text_field( (string) $request->get_param( 'observed_at' ) ),
			'exact_location_text' => sanitize_textarea_field( (string) $request->get_param( 'exact_location_text' ) ),
			'direction' => sanitize_text_field( (string) $request->get_param( 'direction' ) ),
		);
		$payload_hash = hash( 'sha256', wp_json_encode( $normalized_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		$duplicate = $this->repository->find_recent_duplicate( $event_id, $email_hash, $payload_hash, gmdate( 'Y-m-d H:i:s', time() - self::DUPLICATE_WINDOW ) );
		if ( $duplicate ) {
			if ( BadAround_Contribution_Repository::STATUS_PENDING_VERIFICATION === $duplicate['status'] ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::VERIFY_TTL );
				$token = $this->verification_token( $duplicate['public_id'], $duplicate['email_hash'], $expires_at );
				$duplicate = $this->repository->refresh_verification( $duplicate['id'], hash( 'sha256', $token ), $expires_at );
				if ( ! is_wp_error( $duplicate ) ) {
					$this->queue_verification_email( $duplicate['id'] );
					BadAround_Audit_Log::record( 'contribution', $duplicate['id'], 'contribution_verification_reissued', 'contribution' );
				}
			}
			return rest_ensure_response( array( 'ok' => true, 'message' => 'Se i dati sono validi, riceverai un messaggio per confermare il contributo.' ) );
		}

		$public_id = wp_generate_uuid4();
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::VERIFY_TTL );
		$token = $this->verification_token( $public_id, $email_hash, $expires_at );
		$lat = $request->get_param( 'exact_lat' );
		$lng = $request->get_param( 'exact_lng' );
		if ( '' !== (string) $lat && ( ! is_numeric( $lat ) || (float) $lat < -90 || (float) $lat > 90 ) ) {
			return new WP_Error( 'ba_contribution_invalid_latitude', 'Posizione non valida.', array( 'status' => 400 ) );
		}
		if ( '' !== (string) $lng && ( ! is_numeric( $lng ) || (float) $lng < -180 || (float) $lng > 180 ) ) {
			return new WP_Error( 'ba_contribution_invalid_longitude', 'Posizione non valida.', array( 'status' => 400 ) );
		}
		$observed_at = trim( sanitize_text_field( (string) $request->get_param( 'observed_at' ) ) );
		if ( $observed_at ) {
			$parsed_observed_at = strtotime( $observed_at );
			if ( false === $parsed_observed_at ) {
				return new WP_Error( 'ba_contribution_invalid_observed_at', 'Data o ora non valida.', array( 'status' => 400 ) );
			}
			$observed_at = gmdate( 'Y-m-d H:i:s', $parsed_observed_at );
		}

		$contribution = $this->repository->create( array(
			'public_id' => $public_id,
			'event_id' => $event_id,
			'user_id' => get_current_user_id() ?: null,
			'email' => $email,
			'email_hash' => $email_hash,
			'contribution_type' => $type,
			'public_identity_mode' => $identity_mode,
			'public_display_name' => $display_name ?: null,
			'visibility_requested' => $visibility,
			'content_original' => $content,
			'observed_at' => $observed_at ?: null,
			'observed_at_precision' => sanitize_key( (string) $request->get_param( 'observed_at_precision' ) ),
			'exact_location_text' => $normalized_payload['exact_location_text'] ?: null,
			'exact_lat' => is_numeric( $lat ) ? (float) $lat : null,
			'exact_lng' => is_numeric( $lng ) ? (float) $lng : null,
			'direction' => $normalized_payload['direction'] ?: null,
			'additional_details' => sanitize_textarea_field( (string) $request->get_param( 'additional_details' ) ),
			'contact_allowed' => rest_sanitize_boolean( $request->get_param( 'contact_allowed' ) ),
			'consent_privacy' => true,
			'consent_version' => sanitize_text_field( (string) $request->get_param( 'consent_version' ) ) ?: 'community-contribution-v1.0',
			'verify_token_hash' => hash( 'sha256', $token ),
			'verification_expires_at' => $expires_at,
			'payload_hash' => $payload_hash,
			'ip_hash' => $this->request_ip_hash(),
			'source' => 'event_detail',
		) );
		if ( is_wp_error( $contribution ) ) { return $contribution; }

		$files = method_exists( $request, 'get_file_params' ) ? $request->get_file_params() : array();
		if ( ! empty( $files ) ) {
			$media_repository = new BadAround_Contribution_Media_Repository();
			$media = $media_repository->ingest_rest_files( $contribution['id'], $event_id, $files );
			if ( is_wp_error( $media ) ) {
				$media_repository->cleanup_contribution( $contribution['id'] );
				$this->repository->abandon_pending( $contribution['id'] );
				BadAround_Audit_Log::record( 'contribution', $contribution['id'], 'contribution_media_ingest_failed', 'contribution', $media->get_error_code() );
				return $media;
			}
		}

		BadAround_Audit_Log::record( 'contribution', $contribution['id'], 'contribution_created', 'contribution' );
		$this->queue_verification_email( $contribution['id'] );

		return rest_ensure_response( array( 'ok' => true, 'message' => 'Se i dati sono validi, riceverai un messaggio per confermare il contributo.' ) );
	}

	public function rest_verify( WP_REST_Request $request ) {
		$result = $this->verify_token( sanitize_text_field( (string) $request->get_param( 'public_id' ) ) . '.' . sanitize_text_field( (string) $request->get_param( 'token' ) ) );
		$status = is_wp_error( $result ) ? $result->get_error_code() : $result;
		if ( ! in_array( $status, array( 'confirmed', 'already-confirmed', 'expired', 'invalid' ), true ) ) { $status = 'invalid'; }
		$event_id = 0;
		$row = $this->repository->find_by_public_id( sanitize_text_field( (string) $request->get_param( 'public_id' ) ) );
		if ( $row ) { $event_id = absint( $row['event_id'] ); }
		$url = $event_id ? get_permalink( $event_id ) : home_url( '/' );
		$response = new WP_REST_Response( null, 302 );
		$response->header( 'Location', add_query_arg( 'ba_contribution_status', rawurlencode( $status ), $url ) );
		return $response;
	}

	public function verify_token( $compound_token ) {
		$parts = explode( '.', (string) $compound_token, 2 );
		if ( 2 !== count( $parts ) ) { return new WP_Error( 'invalid', 'Invalid verification token.' ); }
		$public_id = sanitize_text_field( $parts[0] );
		$token = sanitize_text_field( $parts[1] );
		$row = $this->repository->find_by_public_id( $public_id );
		if ( ! $row ) { return new WP_Error( 'invalid', 'Invalid verification token.' ); }

		if ( BadAround_Contribution_Repository::STATUS_TO_REVIEW === $row['status'] || BadAround_Contribution_Repository::STATUS_IN_REVIEW === $row['status'] || in_array( $row['status'], array( BadAround_Contribution_Repository::STATUS_PUBLISHED, BadAround_Contribution_Repository::STATUS_RESERVED, BadAround_Contribution_Repository::STATUS_REJECTED ), true ) ) {
			return 'already-confirmed';
		}
		if ( BadAround_Contribution_Repository::STATUS_PENDING_VERIFICATION !== $row['status'] ) { return new WP_Error( 'invalid', 'Invalid verification token.' ); }

		$expires = strtotime( $row['verification_expires_at'] . ' UTC' );
		if ( ! $expires || $expires < time() ) {
			$this->repository->mark_expired( $row['id'] );
			BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_verification_expired', 'contribution' );
			return new WP_Error( 'expired', 'Verification token expired.' );
		}

		$expected = $this->verification_token( $row['public_id'], $row['email_hash'], $row['verification_expires_at'] );
		if ( empty( $row['verify_token_hash'] ) || ! hash_equals( (string) $row['verify_token_hash'], hash( 'sha256', $token ) ) || ! hash_equals( $expected, $token ) ) {
			BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_verification_invalid', 'contribution' );
			return new WP_Error( 'invalid', 'Invalid verification token.' );
		}

		$verified = $this->repository->mark_verified( $row['id'] );
		if ( is_wp_error( $verified ) || ! $verified || BadAround_Contribution_Repository::STATUS_TO_REVIEW !== $verified['status'] ) {
			return new WP_Error( 'invalid', 'Unable to verify contribution.' );
		}
		BadAround_Audit_Log::transition( 'contribution', $row['id'], 'contribution_verified', BadAround_Contribution_Repository::STATUS_PENDING_VERIFICATION, BadAround_Contribution_Repository::STATUS_TO_REVIEW, 'contribution' );
		return 'confirmed';
	}

	public function send_verification_email( $contribution_id ) {
		$row = $this->repository->find_by_id( $contribution_id );
		if ( ! $row || BadAround_Contribution_Repository::STATUS_PENDING_VERIFICATION !== $row['status'] ) { return; }
		$expires = strtotime( $row['verification_expires_at'] . ' UTC' );
		if ( ! $expires || $expires < time() ) { return; }

		$token = $this->verification_token( $row['public_id'], $row['email_hash'], $row['verification_expires_at'] );
		if ( ! hash_equals( (string) $row['verify_token_hash'], hash( 'sha256', $token ) ) ) { return; }

		$recipient = $row['email'];
		if ( $this->is_staging() ) {
			$recipient = defined( 'BADAROUND_CONTRIBUTION_STAGING_MAIL_TO' ) ? sanitize_email( BADAROUND_CONTRIBUTION_STAGING_MAIL_TO ) : '';
			if ( ! $recipient && defined( 'BADAROUND_SENTINEL_STAGING_MAIL_TO' ) ) { $recipient = sanitize_email( BADAROUND_SENTINEL_STAGING_MAIL_TO ); }
			if ( ! $recipient || ! is_email( $recipient ) ) {
				BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_verification_email_blocked_staging', 'contribution' );
				return;
			}
		}

		$url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE . '/verify/' . rawurlencode( $row['public_id'] ) . '/' . rawurlencode( $token ) );
		$title = get_the_title( absint( $row['event_id'] ) );
		$subject = $this->is_staging() ? '[STAGING BadAround] Conferma il tuo contributo' : 'Conferma il tuo contributo su BadAround';
		$message = "Hai inviato un contributo relativo a: {$title}\n\nConferma il contributo aprendo questo link:\n{$url}\n\nIl link scade tra 24 ore. Se non hai inviato questo contributo, ignora questa email.";
		$html = '<p>Hai inviato un contributo relativo a <strong>' . esc_html( $title ) . '</strong>.</p>';
		$html .= '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 18px;background:#111;color:#fff;text-decoration:none;border-radius:6px;">Conferma il contributo</a></p>';
		$html .= '<p>Il link scade tra 24 ore. Se non hai inviato questo contributo, ignora questa email.</p>';

		$sent = ( new BadAround_Transactional_Mailer() )->send( $recipient, $subject, $message, $html );
		BadAround_Audit_Log::record( 'contribution', $row['id'], is_wp_error( $sent ) ? 'contribution_verification_email_failed' : 'contribution_verification_email_sent', 'contribution' );
	}

	private function queue_verification_email( $id ) {
		$args = array( absint( $id ) );
		if ( ! wp_next_scheduled( 'badaround_contribution_send_verification', $args ) ) {
			wp_schedule_single_event( time() + 5, 'badaround_contribution_send_verification', $args );
		}
	}

	private function consume_rate_limit( $email, $event_id ) {
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ip_key = 'ba_contribution_rl_ip_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$email_event_key = 'ba_contribution_rl_em_' . substr( hash_hmac( 'sha256', strtolower( $email ) . '|' . absint( $event_id ), wp_salt( 'nonce' ) ), 0, 32 );
		if ( ! $this->increment_limit( $ip_key, self::RATE_IP_LIMIT, self::RATE_IP_TTL ) || ! $this->increment_limit( $email_event_key, self::RATE_EMAIL_EVENT_LIMIT, self::RATE_EMAIL_EVENT_TTL ) ) {
			return new WP_Error( 'ba_contribution_rate_limited', 'Troppe richieste. Riprova più tardi.', array( 'status' => 429 ) );
		}
		return true;
	}

	private function increment_limit( $key, $limit, $ttl ) {
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) { return false; }
		set_transient( $key, $count + 1, $ttl );
		return true;
	}

	private function verification_token( $public_id, $email_hash, $expires_at ) {
		return hash_hmac( 'sha256', 'contribution-verify|' . $public_id . '|' . $email_hash . '|' . $expires_at, wp_salt( 'auth' ) );
	}

	private function request_ip_hash() {
		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) { return null; }
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		return hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
	}

	private function is_staging() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return 'production' !== $environment || false !== strpos( $host, 'staging.' );
	}
}
