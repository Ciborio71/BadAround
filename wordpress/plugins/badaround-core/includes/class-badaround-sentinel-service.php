<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadAround_Sentinel_Service {
	const REST_NAMESPACE = 'badaround/v1';
	const REST_ROUTE     = '/sentinels';
	const VERIFY_TTL     = DAY_IN_SECONDS;
	const RATE_IP_LIMIT  = 10;
	const RATE_IP_TTL    = 15 * MINUTE_IN_SECONDS;
	const RATE_EMAIL_LIMIT = 3;
	const RATE_EMAIL_TTL   = HOUR_IN_SECONDS;

	private $repository;

	public function __construct() {
		$this->repository = new BadAround_Sentinel_Repository();
	}

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'template_redirect', array( $this, 'maybe_verify_from_link' ), 1 );
		add_action( 'badaround_sentinel_send_verification', array( $this, 'send_verification_email' ), 10, 1 );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_create' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/verify/(?P<public_id>[a-f0-9-]{36})/(?P<token>[a-f0-9]{64})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_verify' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE . '/unsubscribe/(?P<public_id>[a-f0-9-]{36})/(?P<token>[a-f0-9]{64})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_unsubscribe' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function rest_unsubscribe( WP_REST_Request $request ) {
		$public_id = sanitize_text_field( (string) $request->get_param( 'public_id' ) );
		$token     = sanitize_text_field( (string) $request->get_param( 'token' ) );

		$result = $this->unsubscribe_token( $public_id . '.' . $token );
		$status = is_wp_error( $result ) ? 'unsubscribe-invalid' : $result;
		$allowed = array( 'unsubscribed', 'already-unsubscribed', 'unsubscribe-invalid' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'unsubscribe-invalid';
		}

		$response = new WP_REST_Response( null, 302 );
		$response->header(
			'Location',
			add_query_arg( 'ba_sentinel_status', rawurlencode( $status ), home_url( '/sentinelle/' ) )
		);
		return $response;
	}

	public function unsubscribe_url_for_sentinel( $sentinel ) {
		if ( ! is_array( $sentinel ) || empty( $sentinel['public_id'] ) || empty( $sentinel['email_hash'] ) || empty( $sentinel['confirmed_at'] ) ) {
			return new WP_Error( 'ba_sentinel_unsubscribe_unavailable', 'Sentinel unsubscribe link is unavailable.' );
		}

		$token = $this->unsubscribe_token_value(
			$sentinel['public_id'],
			$sentinel['email_hash'],
			$sentinel['confirmed_at']
		);

		return rest_url(
			self::REST_NAMESPACE . self::REST_ROUTE . '/unsubscribe/' .
			rawurlencode( $sentinel['public_id'] ) . '/' .
			rawurlencode( $token )
		);
	}

	public function unsubscribe_token( $compound_token ) {
		$parts = explode( '.', (string) $compound_token, 2 );
		if ( 2 !== count( $parts ) ) {
			return new WP_Error( 'invalid', 'Invalid unsubscribe token.' );
		}

		$public_id = sanitize_text_field( $parts[0] );
		$token     = sanitize_text_field( $parts[1] );
		$sentinel  = $this->repository->find_by_public_id( $public_id );

		if ( ! $sentinel || empty( $sentinel['confirmed_at'] ) ) {
			return new WP_Error( 'invalid', 'Invalid unsubscribe token.' );
		}

		$expected = $this->unsubscribe_token_value(
			$sentinel['public_id'],
			$sentinel['email_hash'],
			$sentinel['confirmed_at']
		);

		if ( ! hash_equals( $expected, $token ) ) {
			BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_unsubscribe_invalid', 'sentinel' );
			return new WP_Error( 'invalid', 'Invalid unsubscribe token.' );
		}

		if ( BadAround_Sentinel_Repository::STATUS_UNSUBSCRIBED === $sentinel['status'] || ! empty( $sentinel['disabled_at'] ) ) {
			return 'already-unsubscribed';
		}

		if ( BadAround_Sentinel_Repository::STATUS_ACTIVE !== $sentinel['status'] ) {
			return new WP_Error( 'invalid', 'Invalid unsubscribe token.' );
		}

		$updated = $this->repository->unsubscribe( $sentinel['id'] );
		if ( is_wp_error( $updated ) || ! is_array( $updated ) || BadAround_Sentinel_Repository::STATUS_UNSUBSCRIBED !== $updated['status'] ) {
			return new WP_Error( 'invalid', 'Unable to unsubscribe sentinel.' );
		}

		BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_unsubscribed', 'sentinel' );
		return 'unsubscribed';
	}

	public function rest_create( WP_REST_Request $request ) {
		$email = strtolower( trim( sanitize_email( (string) $request->get_param( 'email' ) ) ) );
		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'ba_sentinel_invalid_email', 'Inserisci un indirizzo email valido.', array( 'status' => 400 ) );
		}

		$rate = $this->consume_rate_limit( $email );
		if ( is_wp_error( $rate ) ) {
			return $rate;
		}

		$criteria = $this->normalize_criteria(
			array(
				'territory_term_id'  => $request->get_param( 'territory_term_id' ),
				'category_term_id'   => $request->get_param( 'category_term_id' ),
				'event_type_term_id' => $request->get_param( 'event_type_term_id' ),
			)
		);
		if ( is_wp_error( $criteria ) ) {
			return $criteria;
		}

		$email_hash    = hash_hmac( 'sha256', $email, wp_salt( 'auth' ) );
		$criteria_hash = hash( 'sha256', wp_json_encode( $criteria ) );
		$duplicate     = $this->repository->find_duplicate( $email_hash, $criteria_hash );

		if ( $duplicate ) {
			if ( BadAround_Sentinel_Repository::STATUS_PENDING === $duplicate['status'] ) {
				$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::VERIFY_TTL );
				$token      = $this->verification_token( $duplicate['public_id'], $email_hash, $expires_at );
				$duplicate  = $this->repository->refresh_verification( $duplicate['id'], hash( 'sha256', $token ), $expires_at );
				if ( ! is_wp_error( $duplicate ) ) {
					$this->queue_verification_email( $duplicate['id'] );
					BadAround_Audit_Log::record( 'sentinel', $duplicate['id'], 'sentinel_verification_reissued', 'sentinel' );
				}
			}

			return rest_ensure_response(
				array(
					'ok'      => true,
					'message' => 'Se i dati sono validi, riceverai un messaggio per confermare la Sentinella.',
				)
			);
		}

		$public_id  = wp_generate_uuid4();
		$expires_at = gmdate( 'Y-m-d H:i:s', time() + self::VERIFY_TTL );
		$token       = $this->verification_token( $public_id, $email_hash, $expires_at );

		$sentinel = $this->repository->create(
			array(
				'public_id'              => $public_id,
				'user_id'                => get_current_user_id() ?: null,
				'email'                  => $email,
				'email_hash'             => $email_hash,
				'territory_term_id'      => $criteria['territory']['id'],
				'category_term_id'       => ! empty( $criteria['category']['id'] ) ? $criteria['category']['id'] : null,
				'event_type_term_id'     => ! empty( $criteria['event_type']['id'] ) ? $criteria['event_type']['id'] : null,
				'criteria'               => $criteria,
				'criteria_hash'          => $criteria_hash,
				'verify_token_hash'      => hash( 'sha256', $token ),
				'verification_expires_at'=> $expires_at,
			)
		);

		if ( is_wp_error( $sentinel ) ) {
			$concurrent = $this->repository->find_duplicate( $email_hash, $criteria_hash );
			if ( $concurrent ) {
				return rest_ensure_response(
					array(
						'ok'      => true,
						'message' => 'Se i dati sono validi, riceverai un messaggio per confermare la Sentinella.',
					)
				);
			}
			return new WP_Error( 'ba_sentinel_create_failed', 'Non è stato possibile creare la Sentinella.', array( 'status' => 500 ) );
		}

		BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_created', 'sentinel' );
		$this->queue_verification_email( $sentinel['id'] );

		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => 'Se i dati sono validi, riceverai un messaggio per confermare la Sentinella.',
			)
		);
	}

	public function rest_verify( WP_REST_Request $request ) {
		$public_id = sanitize_text_field( (string) $request->get_param( 'public_id' ) );
		$token     = sanitize_text_field( (string) $request->get_param( 'token' ) );

		$result = $this->verify_token( $public_id . '.' . $token );
		$status = is_wp_error( $result ) ? $result->get_error_code() : $result;
		$allowed = array( 'confirmed', 'already-active', 'expired', 'invalid' );
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'invalid';
		}

		$response = new WP_REST_Response( null, 302 );
		$response->header(
			'Location',
			add_query_arg( 'ba_sentinel_status', rawurlencode( $status ), home_url( '/sentinelle/' ) )
		);
		return $response;
	}

	public function maybe_verify_from_link() {
		if ( empty( $_GET['ba_sentinel_verify'] ) ) {
			return;
		}

		$result = $this->verify_token( sanitize_text_field( wp_unslash( $_GET['ba_sentinel_verify'] ) ) );
		$status = is_wp_error( $result ) ? $result->get_error_code() : $result;
		$allowed = array(
			'confirmed',
			'already-active',
			'expired',
			'invalid',
		);
		if ( ! in_array( $status, $allowed, true ) ) {
			$status = 'invalid';
		}

		wp_safe_redirect( add_query_arg( 'ba_sentinel_status', rawurlencode( $status ), home_url( '/sentinelle/' ) ) );
		exit;
	}

	public function verify_token( $compound_token ) {
		$parts = explode( '.', (string) $compound_token, 2 );
		if ( 2 !== count( $parts ) ) {
			return new WP_Error( 'invalid', 'Invalid verification token.' );
		}

		$public_id = sanitize_text_field( $parts[0] );
		$token     = sanitize_text_field( $parts[1] );
		$sentinel  = $this->repository->find_by_public_id( $public_id );

		if ( ! $sentinel ) {
			return new WP_Error( 'invalid', 'Invalid verification token.' );
		}

		if ( BadAround_Sentinel_Repository::STATUS_ACTIVE === $sentinel['status'] ) {
			if (
				empty( $sentinel['verify_token_hash'] ) ||
				! hash_equals( (string) $sentinel['verify_token_hash'], hash( 'sha256', $token ) )
			) {
				BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_verification_invalid', 'sentinel' );
				return new WP_Error( 'invalid', 'Invalid verification token.' );
			}
			return 'already-active';
		}

		if ( BadAround_Sentinel_Repository::STATUS_PENDING !== $sentinel['status'] ) {
			return new WP_Error( 'invalid', 'Invalid verification token.' );
		}

		$expires = strtotime( $sentinel['verification_expires_at'] . ' UTC' );
		if ( ! $expires || $expires < time() ) {
			BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_verification_expired', 'sentinel' );
			return new WP_Error( 'expired', 'Verification token expired.' );
		}

		$expected = $this->verification_token( $sentinel['public_id'], $sentinel['email_hash'], $sentinel['verification_expires_at'] );
		if (
			! hash_equals( (string) $sentinel['verify_token_hash'], hash( 'sha256', $token ) ) ||
			! hash_equals( $expected, $token )
		) {
			BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_verification_invalid', 'sentinel' );
			return new WP_Error( 'invalid', 'Invalid verification token.' );
		}

		$activated = $this->repository->activate( $sentinel['id'] );
		if ( is_wp_error( $activated ) ) {
			return new WP_Error( 'invalid', 'Unable to activate sentinel.' );
		}

		BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_confirmed', 'sentinel' );
		return 'confirmed';
	}

	public function send_verification_email( $sentinel_id ) {
		$sentinel = $this->repository->find_by_id( $sentinel_id );
		if ( ! $sentinel || BadAround_Sentinel_Repository::STATUS_PENDING !== $sentinel['status'] ) {
			return;
		}

		$expires = strtotime( $sentinel['verification_expires_at'] . ' UTC' );
		if ( ! $expires || $expires < time() ) {
			return;
		}

		$token = $this->verification_token( $sentinel['public_id'], $sentinel['email_hash'], $sentinel['verification_expires_at'] );
		if ( ! hash_equals( (string) $sentinel['verify_token_hash'], hash( 'sha256', $token ) ) ) {
			return;
		}

		$recipient = $sentinel['email'];
		if ( $this->is_staging() ) {
			$override = defined( 'BADAROUND_SENTINEL_STAGING_MAIL_TO' ) ? sanitize_email( BADAROUND_SENTINEL_STAGING_MAIL_TO ) : '';
			if ( ! $override || ! is_email( $override ) ) {
				BadAround_Audit_Log::record( 'sentinel', $sentinel['id'], 'sentinel_verification_email_blocked_staging', 'sentinel' );
				return;
			}
			$recipient = $override;
		}

		$url = rest_url(
			self::REST_NAMESPACE . self::REST_ROUTE . '/verify/' .
			rawurlencode( $sentinel['public_id'] ) . '/' .
			rawurlencode( $token )
		);

		$criteria = json_decode( $sentinel['criteria_json'], true );
		$territory = ! empty( $criteria['territory']['name'] ) ? $criteria['territory']['name'] : 'la zona scelta';
		$subject = $this->is_staging() ? '[STAGING BadAround] Conferma la tua Sentinella' : 'Conferma la tua Sentinella BadAround';
		$message = "Hai chiesto di seguire {$territory} su BadAround.\n\n";
		$message .= "Conferma la Sentinella aprendo questo link:\n{$url}\n\n";
		$message .= "Il link scade tra 24 ore. Se non hai richiesto questa Sentinella, ignora questa email.";

		$html_message  = '<p>Hai chiesto di seguire <strong>' . esc_html( $territory ) . '</strong> su BadAround.</p>';
		$html_message .= '<p><a href="' . esc_url( $url ) . '" style="display:inline-block;padding:12px 18px;background:#111;color:#fff;text-decoration:none;border-radius:6px;">Conferma la Sentinella</a></p>';
		$html_message .= '<p>Se il pulsante non funziona, copia e incolla questo indirizzo nel browser:<br><a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a></p>';
		$html_message .= '<p>Il link scade tra 24 ore. Se non hai richiesto questa Sentinella, ignora questa email.</p>';

		$sent = ( new BadAround_Transactional_Mailer() )->send( $recipient, $subject, $message, $html_message );
		BadAround_Audit_Log::record(
			'sentinel',
			$sentinel['id'],
			is_wp_error( $sent ) ? 'sentinel_verification_email_failed' : 'sentinel_verification_email_sent',
			'sentinel'
		);
	}

	/**
	 * Send a transactional email through the turboSMTP HTTP API.
	 *
	 * Credentials intentionally live in environment/wp-config constants only.
	 * Nothing is persisted in WordPress options or exposed to the public API.
	 */
	private function queue_verification_email( $sentinel_id ) {
		$args = array( absint( $sentinel_id ) );
		if ( ! wp_next_scheduled( 'badaround_sentinel_send_verification', $args ) ) {
			wp_schedule_single_event( time() + 5, 'badaround_sentinel_send_verification', $args );
		}
	}

	private function normalize_criteria( $input ) {
		$territory_id = absint( $input['territory_term_id'] );
		$category_id  = absint( $input['category_term_id'] );
		$event_type_id = absint( $input['event_type_term_id'] );

		$territory = get_term( $territory_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( ! $territory instanceof WP_Term ) {
			return new WP_Error( 'ba_sentinel_invalid_territory', 'Seleziona un territorio valido.', array( 'status' => 400 ) );
		}

		$category = null;
		if ( $category_id ) {
			$category = get_term( $category_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $category instanceof WP_Term || 0 !== (int) $category->parent ) {
				return new WP_Error( 'ba_sentinel_invalid_category', 'Seleziona una categoria valida.', array( 'status' => 400 ) );
			}
		}

		$event_type = null;
		if ( $event_type_id ) {
			$event_type = get_term( $event_type_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $event_type instanceof WP_Term || 0 === (int) $event_type->parent ) {
				return new WP_Error( 'ba_sentinel_invalid_event_type', 'Seleziona una tipologia valida.', array( 'status' => 400 ) );
			}
			if ( $category && ! term_is_ancestor_of( $category->term_id, $event_type->term_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX ) ) {
				return new WP_Error( 'ba_sentinel_invalid_event_type', 'La tipologia non appartiene alla categoria scelta.', array( 'status' => 400 ) );
			}
			if ( ! $category ) {
				$parent = get_term( $event_type->parent, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
				if ( $parent instanceof WP_Term ) {
					$category = $parent;
				}
			}
		}

		return array(
			'territory' => array(
				'id'   => (int) $territory->term_id,
				'slug' => $territory->slug,
				'name' => $territory->name,
			),
			'category' => $category ? array(
				'id'   => (int) $category->term_id,
				'slug' => $category->slug,
				'name' => $category->name,
			) : null,
			'event_type' => $event_type ? array(
				'id'   => (int) $event_type->term_id,
				'slug' => $event_type->slug,
				'name' => $event_type->name,
			) : null,
		);
	}

	private function consume_rate_limit( $email ) {
		$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$ip_key = 'ba_sentinel_rl_ip_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
		$email_key = 'ba_sentinel_rl_email_' . substr( hash_hmac( 'sha256', strtolower( $email ), wp_salt( 'nonce' ) ), 0, 32 );

		if ( ! $this->increment_limit( $ip_key, self::RATE_IP_LIMIT, self::RATE_IP_TTL ) ) {
			return new WP_Error( 'ba_sentinel_rate_limited', 'Troppe richieste. Riprova più tardi.', array( 'status' => 429 ) );
		}
		if ( ! $this->increment_limit( $email_key, self::RATE_EMAIL_LIMIT, self::RATE_EMAIL_TTL ) ) {
			return new WP_Error( 'ba_sentinel_rate_limited', 'Troppe richieste. Riprova più tardi.', array( 'status' => 429 ) );
		}

		return true;
	}

	private function increment_limit( $key, $limit, $ttl ) {
		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}
		set_transient( $key, $count + 1, $ttl );
		return true;
	}

	private function unsubscribe_token_value( $public_id, $email_hash, $confirmed_at ) {
		return hash_hmac(
			'sha256',
			'unsubscribe|' . (string) $public_id . '|' . (string) $email_hash . '|' . (string) $confirmed_at,
			wp_salt( 'auth' )
		);
	}

	private function verification_token( $public_id, $email_hash, $expires_at ) {
		return hash_hmac(
			'sha256',
			(string) $public_id . '|' . (string) $email_hash . '|' . (string) $expires_at,
			wp_salt( 'auth' )
		);
	}

	private function is_staging() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return 'production' !== $environment || false !== strpos( $host, 'staging.' );
	}
}
