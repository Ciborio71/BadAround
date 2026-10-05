<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Notification_Service {
	const ACCESS_TTL = 172800;
	const MAX_ATTEMPTS = 3;

	private $contributions;
	private $notifications;

	public function __construct() {
		$this->contributions = new BadAround_Contribution_Repository();
		$this->notifications = new BadAround_Contribution_Notification_Repository();
	}

	public function register_hooks() {
		add_action( 'badaround_contribution_notify_reporter', array( $this, 'send_notification' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'maybe_render_reserved_access' ), 0 );
	}

	public function queue_for_contribution( $contribution_id ) {
		$row = $this->contributions->find_by_id( $contribution_id );
		if ( ! $row || ! in_array( $row['status'], array( BadAround_Contribution_Repository::STATUS_PUBLISHED, BadAround_Contribution_Repository::STATUS_RESERVED ), true ) ) {
			return new WP_Error( 'ba_contribution_notification_not_eligible', __( 'Contributo non notificabile.', 'badaround-core' ) );
		}

		$recipient = $this->reporter_email_for_event( $row['event_id'] );
		if ( ! $recipient ) {
			BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_reporter_notification_unavailable', 'contribution' );
			return new WP_Error( 'ba_contribution_reporter_email_unavailable', __( 'Contatto del segnalatore non disponibile.', 'badaround-core' ) );
		}

		$recipient_hash = hash_hmac( 'sha256', strtolower( $recipient ), wp_salt( 'auth' ) );
		$kind = BadAround_Contribution_Repository::STATUS_RESERVED === $row['status'] ? 'reserved' : 'published';
		$access_public_id = null;
		$access_token_hash = null;
		$access_expires_at = null;

		if ( 'reserved' === $kind ) {
			$access_public_id = wp_generate_uuid4();
			$access_expires_at = gmdate( 'Y-m-d H:i:s', time() + self::ACCESS_TTL );
			$token = $this->access_token( $access_public_id, $contribution_id, $recipient_hash, $access_expires_at );
			$access_token_hash = hash( 'sha256', $token );
		}

		$notification = $this->notifications->create_or_get(
			$contribution_id,
			$row['event_id'],
			$kind,
			$recipient_hash,
			$access_public_id,
			$access_token_hash,
			$access_expires_at
		);
		if ( ! $notification ) {
			return new WP_Error( 'ba_contribution_notification_create_failed', __( 'Impossibile accodare la notifica.', 'badaround-core' ) );
		}

		$args = array( absint( $notification['id'] ) );
		if ( ! wp_next_scheduled( 'badaround_contribution_notify_reporter', $args ) ) {
			wp_schedule_single_event( time() + 5, 'badaround_contribution_notify_reporter', $args );
		}
		BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_reporter_notification_queued', 'contribution' );
		return $notification;
	}

	public function send_notification( $notification_id ) {
		$notification = $this->notifications->find_by_id( $notification_id );
		if ( ! $notification || in_array( $notification['status'], array( BadAround_Contribution_Notification_Repository::STATUS_SENT, BadAround_Contribution_Notification_Repository::STATUS_FAILED ), true ) ) {
			return;
		}

		$claimed = BadAround_Contribution_Notification_Repository::STATUS_RETRY === $notification['status']
			? $this->notifications->claim_retry( $notification_id )
			: $this->notifications->claim( $notification_id );
		if ( ! $claimed ) { return; }

		$notification = $this->notifications->find_by_id( $notification_id );
		$row = $this->contributions->find_by_id( $notification['contribution_id'] );
		$recipient = $row ? $this->reporter_email_for_event( $row['event_id'] ) : '';
		if ( ! $row || ! $recipient ) {
			$this->fail_or_retry( $notification, 'recipient_unavailable' );
			return;
		}

		$actual_hash = hash_hmac( 'sha256', strtolower( $recipient ), wp_salt( 'auth' ) );
		if ( ! hash_equals( (string) $notification['recipient_hash'], $actual_hash ) ) {
			$this->notifications->mark_failed( $notification_id, 'recipient_changed' );
			BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reporter_notification_failed', 'contribution', 'recipient_changed' );
			return;
		}

		$recipient_to = $this->staging_recipient( $recipient );
		if ( ! $recipient_to ) {
			$this->notifications->mark_failed( $notification_id, 'recipient_unavailable' );
			return;
		}

		$title = get_the_title( absint( $row['event_id'] ) );
		if ( 'reserved' === $notification['notification_kind'] ) {
			$url = $this->reserved_access_url( $notification, $row );
			$subject = $this->subject_prefix() . 'Informazione riservata sulla tua segnalazione';
			$message = "È arrivata un’informazione riservata relativa a: {$title}.\n\nPer motivi di privacy il contenuto non è incluso in questa email.\nApri il link temporaneo:\n{$url}\n\nIl link scade dopo 48 ore.";
			$html = '<p>È arrivata un’informazione riservata relativa a <strong>' . esc_html( $title ) . '</strong>.</p><p>Per motivi di privacy il contenuto non è incluso in questa email.</p><p><a href="' . esc_url( $url ) . '">Apri l’informazione riservata</a></p><p>Il link scade dopo 48 ore.</p>';
		} else {
			$public_text = $this->public_projection_text( $row );
			$url = get_permalink( absint( $row['event_id'] ) );
			$subject = $this->subject_prefix() . 'Nuovo contributo sulla tua segnalazione';
			$message = "È stato pubblicato un nuovo contributo relativo a: {$title}.\n\n{$public_text}\n\nApri la segnalazione:\n{$url}";
			$html = '<p>È stato pubblicato un nuovo contributo relativo a <strong>' . esc_html( $title ) . '</strong>.</p><p>' . nl2br( esc_html( $public_text ) ) . '</p><p><a href="' . esc_url( $url ) . '">Apri la segnalazione</a></p>';
		}

		$sent = ( new BadAround_Transactional_Mailer() )->send( $recipient_to, $subject, $message, $html );
		if ( is_wp_error( $sent ) ) {
			$this->fail_or_retry( $notification, $sent->get_error_code() );
			return;
		}

		$this->notifications->mark_sent( $notification_id );
		BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reporter_notification_sent', 'contribution' );
	}

	public function maybe_render_reserved_access() {
		if ( empty( $_GET['ba_contribution_reserved'] ) ) { return; }
		$parts = explode( '.', sanitize_text_field( wp_unslash( $_GET['ba_contribution_reserved'] ) ), 2 );
		$status = 'invalid';
		$content = '';

		if ( 2 === count( $parts ) ) {
			$notification = $this->notifications->find_by_access_public_id( $parts[0] );
			if ( $notification && 'reserved' === $notification['notification_kind'] ) {
				$row = $this->contributions->find_by_id( $notification['contribution_id'] );
				$expires = strtotime( $notification['access_expires_at'] . ' UTC' );
				$expected = $row ? $this->access_token( $notification['access_public_id'], $row['id'], $notification['recipient_hash'], $notification['access_expires_at'] ) : '';
				if ( ! $expires || $expires < time() ) {
					$status = 'expired';
				} elseif ( $row && BadAround_Contribution_Repository::STATUS_RESERVED === $row['status'] && $expected && hash_equals( (string) $notification['access_token_hash'], hash( 'sha256', $parts[1] ) ) && hash_equals( $expected, $parts[1] ) ) {
					$status = 'valid';
					$content = (string) $row['recipient_text'];
					BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reserved_access_opened', 'contribution' );
				}
			}
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informazione riservata — BadAround</title></head><body><main style="max-width:720px;margin:48px auto;padding:24px;font-family:system-ui,sans-serif"><h1>Informazione riservata</h1>';
		if ( 'valid' === $status ) {
			echo '<p>' . nl2br( esc_html( $content ) ) . '</p>';
		} elseif ( 'expired' === $status ) {
			echo '<p>Questo collegamento è scaduto.</p>';
		} else {
			echo '<p>Questo collegamento non è valido.</p>';
		}
		echo '<p><a href="' . esc_url( home_url( '/' ) ) . '">Torna a BadAround</a></p></main></body></html>';
		exit;
	}

	private function reporter_email_for_event( $event_id ) {
		global $wpdb;
		$email = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT author_email FROM {$wpdb->prefix}ba_reports WHERE event_id = %d AND deleted_at IS NULL AND author_email IS NOT NULL AND author_email <> '' ORDER BY id ASC LIMIT 1",
				absint( $event_id )
			)
		);
		$email = strtolower( sanitize_email( $email ) );
		return $email && is_email( $email ) ? $email : '';
	}

	private function public_projection_text( array $row ) {
		if ( empty( $row['public_projection_id'] ) ) { return ''; }
		$post = get_post( absint( $row['public_projection_id'] ) );
		return $post ? wp_strip_all_tags( $post->post_content ) : '';
	}

	private function reserved_access_url( array $notification, array $row ) {
		$token = $this->access_token( $notification['access_public_id'], $row['id'], $notification['recipient_hash'], $notification['access_expires_at'] );
		return add_query_arg( 'ba_contribution_reserved', rawurlencode( $notification['access_public_id'] . '.' . $token ), home_url( '/' ) );
	}

	private function access_token( $public_id, $contribution_id, $recipient_hash, $expires_at ) {
		return hash_hmac( 'sha256', 'contribution-reserved|' . $public_id . '|' . absint( $contribution_id ) . '|' . $recipient_hash . '|' . $expires_at, wp_salt( 'auth' ) );
	}

	private function fail_or_retry( array $notification, $error_code ) {
		$attempts = absint( $notification['notification_attempts'] );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->notifications->mark_failed( $notification['id'], $error_code );
			BadAround_Audit_Log::record( 'contribution', $notification['contribution_id'], 'contribution_reporter_notification_failed', 'contribution', $error_code );
			return;
		}
		$delay = 1 === $attempts ? 300 : 1800;
		$next = gmdate( 'Y-m-d H:i:s', time() + $delay );
		$this->notifications->mark_retry( $notification['id'], $error_code, $next );
		wp_schedule_single_event( strtotime( $next . ' UTC' ), 'badaround_contribution_notify_reporter', array( absint( $notification['id'] ) ) );
	}

	private function staging_recipient( $recipient ) {
		if ( ! $this->is_staging() ) { return $recipient; }
		$override = defined( 'BADAROUND_CONTRIBUTION_STAGING_MAIL_TO' ) ? sanitize_email( BADAROUND_CONTRIBUTION_STAGING_MAIL_TO ) : '';
		if ( ! $override && defined( 'BADAROUND_SENTINEL_STAGING_MAIL_TO' ) ) { $override = sanitize_email( BADAROUND_SENTINEL_STAGING_MAIL_TO ); }
		return $override && is_email( $override ) ? $override : '';
	}

	private function subject_prefix() {
		return $this->is_staging() ? '[STAGING BadAround] ' : 'BadAround — ';
	}

	private function is_staging() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return 'production' !== $environment || false !== strpos( $host, 'staging.' );
	}
}
