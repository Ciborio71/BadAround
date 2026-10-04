<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Sentinel_Matching_Service {
	const MATCH_CRON_HOOK = 'badaround_sentinel_match_event';
	const SEND_CRON_HOOK  = 'badaround_sentinel_send_event_notification';
	const MAX_ATTEMPTS     = 4;

	private $sentinels;
	private $matches;

	public function __construct() {
		$this->sentinels = new BadAround_Sentinel_Repository();
		$this->matches   = new BadAround_Sentinel_Match_Repository();
	}

	public function register_hooks() {
		add_action( 'badaround_event_published', array( $this, 'queue_event_matching' ), 10, 1 );
		add_action( 'rest_api_init', array( $this, 'register_qa_route' ) );
		add_action( self::MATCH_CRON_HOOK, array( $this, 'process_event_matching' ), 10, 1 );
		add_action( self::SEND_CRON_HOOK, array( $this, 'send_match_notification' ), 10, 1 );
	}

	public function register_qa_route() {
		if ( ! $this->is_staging() ) {
			return;
		}
		register_rest_route(
			'badaround/v1',
			'/qa/d2/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_qa_publish' ),
				'permission_callback' => function () {
					return current_user_can( 'ba_moderate_events' ) && current_user_can( 'publish_ba_eventi' );
				},
			)
		);
	}

	public function rest_qa_publish( WP_REST_Request $request ) {
		$event_id = absint( $request->get_param( 'event_id' ) );
		$result   = ( new BadAround_Publication_Service() )->publish( $event_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'ok' => true, 'event_id' => $event_id ) );
	}

	public function queue_event_matching( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! $event_id || ! BadAround_Discovery_Contract::is_public_event( $event_id ) ) {
			return false;
		}

		$args = array( $event_id );
		if ( ! wp_next_scheduled( self::MATCH_CRON_HOOK, $args ) ) {
			wp_schedule_single_event( time() + 5, self::MATCH_CRON_HOOK, $args );
			BadAround_Audit_Log::record( 'event', $event_id, 'sentinel_matching_queued', 'sentinel' );
		}
		return true;
	}

	public function process_event_matching( $event_id ) {
		$event_id = absint( $event_id );
		if ( ! BadAround_Discovery_Contract::is_public_event( $event_id ) ) {
			return;
		}

		foreach ( $this->sentinels->find_eligible_active() as $sentinel ) {
			if (
				! BadAround_Discovery_Contract::event_matches_ids(
					$event_id,
					$sentinel['territory_term_id'],
					$sentinel['category_term_id'],
					$sentinel['event_type_term_id']
				)
			) {
				continue;
			}

			$match = $this->matches->create_unique( $sentinel['id'], $event_id );
			if ( is_wp_error( $match ) ) {
				BadAround_Audit_Log::record( 'event', $event_id, 'sentinel_matching_failed', 'sentinel', $match->get_error_code() );
				continue;
			}

			if ( false === $match ) {
				$existing = $this->matches->find_pair( $sentinel['id'], $event_id );
				if ( $existing ) {
					BadAround_Audit_Log::record( 'sentinel_match', $existing['id'], 'sentinel_event_duplicate_skipped', 'sentinel' );
				}
				continue;
			}

			BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_event_matched', 'sentinel' );
			$this->queue_match_notification( $match['id'] );
		}
	}

	public function send_match_notification( $match_id ) {
		$match = $this->matches->claim_for_send( $match_id, self::MAX_ATTEMPTS );
		if ( ! $match ) {
			return;
		}

		$sentinel = $this->sentinels->find_by_id( $match['sentinel_id'] );
		if ( ! $this->sentinel_is_sendable( $sentinel ) ) {
			$this->matches->mark_cancelled( $match['id'], 'sentinel_not_active' );
			BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_cancelled', 'sentinel', 'sentinel_not_active' );
			return;
		}

		if ( ! BadAround_Discovery_Contract::is_public_event( $match['event_id'] ) ) {
			$this->matches->mark_cancelled( $match['id'], 'event_not_public' );
			BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_cancelled', 'sentinel', 'event_not_public' );
			return;
		}

		$projection = ( new BadAround_Discovery_Query() )->public_projection_for_event( $match['event_id'] );
		if ( ! is_array( $projection ) || empty( $projection['permalink'] ) || empty( $projection['title'] ) ) {
			$this->matches->mark_cancelled( $match['id'], 'public_projection_missing' );
			BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_cancelled', 'sentinel', 'public_projection_missing' );
			return;
		}

		$recipient = $this->notification_recipient( $sentinel );
		if ( is_wp_error( $recipient ) ) {
			$this->handle_send_failure( $match, $recipient );
			return;
		}

		$subject = $this->is_staging()
			? '[STAGING BadAround] Nuovo evento nella tua Sentinella'
			: 'Nuovo evento nella tua Sentinella BadAround';

		$territory = ! empty( $projection['territory']['name'] ) ? $projection['territory']['name'] : '';
		$type      = ! empty( $projection['event_type']['name'] ) ? $projection['event_type']['name'] : '';
		$message   = $projection['title'] . "\n";
		if ( $territory ) { $message .= 'Zona: ' . $territory . "\n"; }
		if ( $type ) { $message .= 'Tipo: ' . $type . "\n"; }
		if ( ! empty( $projection['excerpt'] ) ) { $message .= "\n" . $projection['excerpt'] . "\n"; }
		$message .= "\nDettaglio pubblico: " . $projection['permalink'];

		$html  = '<h2>' . esc_html( $projection['title'] ) . '</h2>';
		if ( $territory ) { $html .= '<p><strong>Zona:</strong> ' . esc_html( $territory ) . '</p>'; }
		if ( $type ) { $html .= '<p><strong>Tipo:</strong> ' . esc_html( $type ) . '</p>'; }
		if ( ! empty( $projection['excerpt'] ) ) { $html .= '<p>' . esc_html( $projection['excerpt'] ) . '</p>'; }
		$html .= '<p><a href="' . esc_url( $projection['permalink'] ) . '">Apri il dettaglio su BadAround</a></p>';

		$result = ( new BadAround_Transactional_Mailer() )->send( $recipient, $subject, $message, $html );
		if ( is_wp_error( $result ) ) {
			$this->handle_send_failure( $match, $result );
			return;
		}

		$this->matches->mark_sent( $match['id'] );
		$this->sentinels->mark_notified( $sentinel['id'] );
		BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_sent', 'sentinel' );
	}

	private function queue_match_notification( $match_id, $delay = 5 ) {
		$args = array( absint( $match_id ) );
		if ( ! wp_next_scheduled( self::SEND_CRON_HOOK, $args ) ) {
			wp_schedule_single_event( time() + max( 1, absint( $delay ) ), self::SEND_CRON_HOOK, $args );
			BadAround_Audit_Log::record( 'sentinel_match', $match_id, 'sentinel_notification_queued', 'sentinel' );
		}
	}

	private function handle_send_failure( $match, WP_Error $error ) {
		$code       = sanitize_key( $error->get_error_code() );
		$attempts   = absint( $match['notification_attempts'] );
		$retryable  = $this->is_retryable_error( $error );
		$delays     = array( 1 => 5 * MINUTE_IN_SECONDS, 2 => 30 * MINUTE_IN_SECONDS, 3 => 2 * HOUR_IN_SECONDS );

		if ( $retryable && $attempts < self::MAX_ATTEMPTS && isset( $delays[ $attempts ] ) ) {
			$next = gmdate( 'Y-m-d H:i:s', time() + $delays[ $attempts ] );
			$this->matches->mark_retry( $match['id'], $code, $next );
			BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_retry', 'sentinel', $code );
			$this->queue_match_notification( $match['id'], $delays[ $attempts ] );
			return;
		}

		$this->matches->mark_failed( $match['id'], $code );
		BadAround_Audit_Log::record( 'sentinel_match', $match['id'], 'sentinel_notification_failed', 'sentinel', $code );
	}

	private function is_retryable_error( WP_Error $error ) {
		if ( 'ba_turbosmtp_request_failed' === $error->get_error_code() ) {
			return true;
		}
		if ( 'ba_turbosmtp_rejected' === $error->get_error_code() ) {
			$data = $error->get_error_data();
			$status = is_array( $data ) && isset( $data['http_status'] ) ? absint( $data['http_status'] ) : 0;
			return 429 === $status || $status >= 500;
		}
		return false;
	}

	private function sentinel_is_sendable( $sentinel ) {
		return is_array( $sentinel )
			&& BadAround_Sentinel_Repository::STATUS_ACTIVE === $sentinel['status']
			&& ! empty( $sentinel['confirmed_at'] )
			&& empty( $sentinel['disabled_at'] )
			&& empty( $sentinel['deleted_at'] )
			&& ! empty( $sentinel['email'] )
			&& is_email( $sentinel['email'] );
	}

	private function notification_recipient( $sentinel ) {
		$recipient = sanitize_email( $sentinel['email'] );
		if ( $this->is_staging() ) {
			$override = defined( 'BADAROUND_SENTINEL_STAGING_MAIL_TO' ) ? sanitize_email( BADAROUND_SENTINEL_STAGING_MAIL_TO ) : '';
			if ( ! $override || ! is_email( $override ) ) {
				return new WP_Error( 'ba_sentinel_staging_recipient_missing', 'Controlled staging recipient is not configured.' );
			}
			return $override;
		}
		return $recipient;
	}

	private function is_staging() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return 'production' !== $environment || false !== strpos( $host, 'staging.' );
	}
}
