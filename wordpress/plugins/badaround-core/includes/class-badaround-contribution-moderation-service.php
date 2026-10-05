<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Moderation_Service {
	private $repository;

	public function __construct() {
		$this->repository = new BadAround_Contribution_Repository();
		add_action( 'badaround_contribution_notify_reporter', array( $this, 'send_reporter_notification' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'maybe_render_reserved_access' ), 0 );
	}

	public function transition( $contribution_id, $target_status, array $args = array() ) {
		$contribution_id = absint( $contribution_id );
		$target_status = sanitize_key( $target_status );

		if ( ! $contribution_id || ! current_user_can( 'ba_moderate_contributions' ) ) {
			return new WP_Error( 'ba_contribution_moderation_forbidden', __( 'Non sei autorizzato a moderare i contributi.', 'badaround-core' ) );
		}

		$row = $this->repository->find_by_id( $contribution_id );
		if ( ! $row ) {
			return new WP_Error( 'ba_contribution_not_found', __( 'Contributo non trovato.', 'badaround-core' ) );
		}

		$current = sanitize_key( $row['status'] );
		$allowed = array(
			BadAround_Contribution_Repository::STATUS_TO_REVIEW => array( BadAround_Contribution_Repository::STATUS_IN_REVIEW ),
			BadAround_Contribution_Repository::STATUS_IN_REVIEW => array(
				BadAround_Contribution_Repository::STATUS_PUBLISHED,
				BadAround_Contribution_Repository::STATUS_RESERVED,
				BadAround_Contribution_Repository::STATUS_REJECTED,
			),
		);

		if ( $current === $target_status ) {
			return $row;
		}
		if ( empty( $allowed[ $current ] ) || ! in_array( $target_status, $allowed[ $current ], true ) ) {
			return new WP_Error( 'ba_contribution_transition_not_allowed', __( 'Transizione di moderazione non consentita.', 'badaround-core' ) );
		}

		$reason = isset( $args['reason'] ) ? sanitize_textarea_field( $args['reason'] ) : '';
		if ( BadAround_Contribution_Repository::STATUS_REJECTED === $target_status && '' === $reason ) {
			return new WP_Error( 'ba_contribution_reason_required', __( 'Per rifiutare un contributo è necessaria una motivazione.', 'badaround-core' ) );
		}

		$projection_id = null;
		$visibility_decided = null;

		if ( BadAround_Contribution_Repository::STATUS_PUBLISHED === $target_status ) {
			if ( 'reserved' === sanitize_key( $row['visibility_requested'] ) ) {
				return new WP_Error( 'ba_contribution_reserved_cannot_publish', __( 'Un contributo richiesto come riservato non può essere pubblicato.', 'badaround-core' ) );
			}
			$media_repository = new BadAround_Contribution_Media_Repository();
			if ( $media_repository->has_pending_review( $contribution_id ) ) {
				return new WP_Error( 'ba_contribution_media_review_required', __( 'Prima di pubblicare devi approvare o rifiutare tutti i media del contributo.', 'badaround-core' ) );
			}

			$public_text = isset( $args['public_text'] ) ? trim( sanitize_textarea_field( $args['public_text'] ) ) : '';
			if ( mb_strlen( $public_text ) < 10 ) {
				return new WP_Error( 'ba_contribution_public_text_required', __( 'Inserisci un testo pubblico moderato.', 'badaround-core' ) );
			}
			$privacy = $this->validate_public_text( $row, $public_text );
			if ( is_wp_error( $privacy ) ) {
				return $privacy;
			}
			$projection_id = $this->create_public_projection( $row, $public_text );
			if ( is_wp_error( $projection_id ) ) {
				return $projection_id;
			}

			$media_result = $media_repository->materialize_approved( $contribution_id, $projection_id );
			if ( is_wp_error( $media_result ) ) {
				wp_delete_post( $projection_id, true );
				return $media_result;
			}
			if ( ! empty( $media_result ) ) {
				update_post_meta( $projection_id, '_ba_public_media_ids', array_map( 'absint', $media_result ) );
			}
			$visibility_decided = 'public';
		}

		$recipient_text = '';
		if ( BadAround_Contribution_Repository::STATUS_RESERVED === $target_status ) {
			$recipient_text = isset( $args['recipient_text'] ) ? trim( sanitize_textarea_field( $args['recipient_text'] ) ) : '';
			if ( mb_strlen( $recipient_text ) < 10 ) {
				return new WP_Error( 'ba_contribution_recipient_text_required', __( 'Inserisci il testo riservato destinato al segnalatore.', 'badaround-core' ) );
			}
			if ( preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $recipient_text ) ) {
				return new WP_Error( 'ba_contribution_recipient_email_detected', __( 'Il testo riservato non deve esporre indirizzi email del contributore.', 'badaround-core' ) );
			}
			$visibility_decided = 'reserved';
		}
		if ( BadAround_Contribution_Repository::STATUS_REJECTED === $target_status ) {
			$visibility_decided = 'rejected';
		}

		$updated = $this->repository->moderate(
			$contribution_id,
			$current,
			$target_status,
			array(
				'visibility_decided' => $visibility_decided,
				'public_projection_id' => $projection_id,
				'moderated_by' => get_current_user_id() ?: null,
				'reason' => $reason,
				'recipient_text' => $recipient_text,
			)
		);
		if ( is_wp_error( $updated ) ) {
			if ( $projection_id ) {
				wp_delete_post( $projection_id, true );
			}
			return $updated;
		}

		$actions = array(
			BadAround_Contribution_Repository::STATUS_IN_REVIEW => 'contribution_review_started',
			BadAround_Contribution_Repository::STATUS_PUBLISHED => 'contribution_published',
			BadAround_Contribution_Repository::STATUS_RESERVED => 'contribution_reserved',
			BadAround_Contribution_Repository::STATUS_REJECTED => 'contribution_rejected',
		);
		BadAround_Audit_Log::transition(
			'contribution',
			$contribution_id,
			isset( $actions[ $target_status ] ) ? $actions[ $target_status ] : 'contribution_moderation_transition',
			$current,
			$target_status,
			'contribution',
			$reason
		);

		if ( $projection_id ) {
			BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_public_projection_created', 'contribution' );
		}
		if ( in_array( $target_status, array( BadAround_Contribution_Repository::STATUS_PUBLISHED, BadAround_Contribution_Repository::STATUS_RESERVED ), true ) ) {
			$queued = $this->queue_reporter_notification( $contribution_id );
			if ( is_wp_error( $queued ) ) {
				BadAround_Audit_Log::record( 'contribution', $contribution_id, 'contribution_reporter_notification_queue_failed', 'contribution', $queued->get_error_code() );
			}
		}
		return $updated;
	}

	private function validate_public_text( array $row, $public_text ) {
		$haystack = strtolower( $public_text );
		$private_values = array(
			$row['email'],
			$row['exact_location_text'],
		);
		foreach ( $private_values as $private_value ) {
			$private_value = trim( strtolower( (string) $private_value ) );
			if ( strlen( $private_value ) >= 5 && false !== strpos( $haystack, $private_value ) ) {
				return new WP_Error( 'ba_contribution_private_data_detected', __( 'Il testo pubblico contiene un dato riservato del contributo.', 'badaround-core' ) );
			}
		}
		if ( is_email( trim( $public_text ) ) || preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $public_text ) ) {
			return new WP_Error( 'ba_contribution_email_detected', __( 'Il testo pubblico non può contenere indirizzi email.', 'badaround-core' ) );
		}
		return true;
	}

	private function create_public_projection( array $row, $public_text ) {
		$event_id = absint( $row['event_id'] );
		if ( 'publish' !== get_post_status( $event_id ) || BadAround_Publication_Service::STATUS_PUBLISHED !== sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) ) ) {
			return new WP_Error( 'ba_contribution_parent_not_public', __( 'L’evento collegato non è più pubblicato.', 'badaround-core' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_type' => BadAround_Contribution_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_title' => sprintf( __( 'Contributo community — evento #%d', 'badaround-core' ), $event_id ),
				'post_content' => $public_text,
				'post_parent' => $event_id,
				'post_author' => get_current_user_id() ?: 0,
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_ba_event_id', $event_id );
		update_post_meta( $post_id, '_ba_contribution_id', absint( $row['id'] ) );
		update_post_meta( $post_id, '_ba_contribution_type', sanitize_key( $row['contribution_type'] ) );
		update_post_meta( $post_id, '_ba_public_identity_mode', sanitize_key( $row['public_identity_mode'] ) );

		$display_name = 'Contributo anonimo';
		if ( 'public_alias' === sanitize_key( $row['public_identity_mode'] ) && ! empty( $row['public_display_name'] ) ) {
			$display_name = sanitize_text_field( $row['public_display_name'] );
		}
		update_post_meta( $post_id, '_ba_public_display_name', $display_name );

		return absint( $post_id );
	}

	public function queue_reporter_notification( $contribution_id ) {
		global $wpdb;
		$row = $this->repository->find_by_id( $contribution_id );
		if ( ! $row || ! in_array( $row['status'], array( BadAround_Contribution_Repository::STATUS_PUBLISHED, BadAround_Contribution_Repository::STATUS_RESERVED ), true ) ) {
			return new WP_Error( 'ba_contribution_notification_not_eligible', __( 'Contributo non notificabile.', 'badaround-core' ) );
		}

		$recipient = $this->reporter_email_for_event( $row['event_id'] );
		if ( ! $recipient ) {
			return new WP_Error( 'ba_contribution_reporter_email_unavailable', __( 'Contatto del segnalatore non disponibile.', 'badaround-core' ) );
		}

		$recipient_hash = hash_hmac( 'sha256', strtolower( $recipient ), wp_salt( 'auth' ) );
		$kind = BadAround_Contribution_Repository::STATUS_RESERVED === $row['status'] ? 'reserved' : 'published';
		$access_public_id = null;
		$access_token_hash = null;
		$access_expires_at = null;

		if ( 'reserved' === $kind ) {
			$access_public_id = wp_generate_uuid4();
			$access_expires_at = gmdate( 'Y-m-d H:i:s', time() + ( 2 * DAY_IN_SECONDS ) );
			$token = $this->reserved_access_token( $access_public_id, $row['id'], $recipient_hash, $access_expires_at );
			$access_token_hash = hash( 'sha256', $token );
		}

		$now = current_time( 'mysql', true );
		$inserted = $wpdb->insert(
			$wpdb->prefix . 'ba_contribution_notifications',
			array(
				'contribution_id' => absint( $row['id'] ),
				'event_id' => absint( $row['event_id'] ),
				'notification_kind' => $kind,
				'recipient_hash' => $recipient_hash,
				'status' => 'queued',
				'access_public_id' => $access_public_id,
				'access_token_hash' => $access_token_hash,
				'access_expires_at' => $access_expires_at,
				'created_at' => $now,
				'updated_at' => $now,
			)
		);

		$notification = $inserted
			? $this->reporter_notification_for_contribution( $row['id'] )
			: $this->reporter_notification_for_contribution( $row['id'] );

		if ( ! $notification ) {
			return new WP_Error( 'ba_contribution_notification_create_failed', __( 'Impossibile accodare la notifica.', 'badaround-core' ) );
		}

		if ( ! in_array( $notification['status'], array( 'sent', 'failed' ), true ) ) {
			$args = array( absint( $notification['id'] ) );
			if ( ! wp_next_scheduled( 'badaround_contribution_notify_reporter', $args ) ) {
				wp_schedule_single_event( time() + 5, 'badaround_contribution_notify_reporter', $args );
			}
		}
		BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reporter_notification_queued', 'contribution' );
		return $notification;
	}

	public function send_reporter_notification( $notification_id ) {
		global $wpdb;
		$notification = $this->reporter_notification_by_id( $notification_id );
		if ( ! $notification || in_array( $notification['status'], array( 'sent', 'failed' ), true ) ) {
			return;
		}

		$attempts = absint( $notification['notification_attempts'] );
		$allowed_status = 'retry' === $notification['status'] ? 'retry' : 'queued';
		if ( 'retry' === $allowed_status && ! empty( $notification['next_retry_at'] ) && strtotime( $notification['next_retry_at'] . ' UTC' ) > time() ) {
			return;
		}

		$claimed = $wpdb->update(
			$wpdb->prefix . 'ba_contribution_notifications',
			array(
				'status' => 'sending',
				'notification_attempts' => $attempts + 1,
				'last_attempt_at' => current_time( 'mysql', true ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'id' => absint( $notification_id ),
				'status' => $allowed_status,
			)
		);
		if ( 1 !== (int) $claimed ) {
			return;
		}

		$notification = $this->reporter_notification_by_id( $notification_id );
		$row = $this->repository->find_by_id( $notification['contribution_id'] );
		$recipient = $row ? $this->reporter_email_for_event( $row['event_id'] ) : '';
		if ( ! $row || ! $recipient ) {
			$this->reporter_notification_retry_or_fail( $notification, 'recipient_unavailable' );
			return;
		}

		$recipient_hash = hash_hmac( 'sha256', strtolower( $recipient ), wp_salt( 'auth' ) );
		if ( ! hash_equals( (string) $notification['recipient_hash'], $recipient_hash ) ) {
			$this->reporter_notification_mark_failed( $notification, 'recipient_changed' );
			return;
		}

		$destination = $this->reporter_notification_destination( $recipient );
		if ( ! $destination ) {
			$this->reporter_notification_mark_failed( $notification, 'destination_unavailable' );
			return;
		}

		$title = get_the_title( absint( $row['event_id'] ) );
		if ( 'reserved' === $notification['notification_kind'] ) {
			$url = $this->reserved_access_url( $notification, $row );
			$subject = $this->reporter_subject_prefix() . 'Informazione riservata sulla tua segnalazione';
			$message = "È arrivata un’informazione riservata relativa a: {$title}.\n\nPer motivi di privacy il contenuto non è incluso in questa email.\nApri il link temporaneo:\n{$url}\n\nIl link scade dopo 48 ore.";
			$html = '<p>È arrivata un’informazione riservata relativa a <strong>' . esc_html( $title ) . '</strong>.</p><p>Per motivi di privacy il contenuto non è incluso in questa email.</p><p><a href="' . esc_url( $url ) . '">Apri l’informazione riservata</a></p><p>Il link scade dopo 48 ore.</p>';
		} else {
			$public_text = $this->public_projection_text( $row );
			$url = get_permalink( absint( $row['event_id'] ) );
			$subject = $this->reporter_subject_prefix() . 'Nuovo contributo sulla tua segnalazione';
			$message = "È stato pubblicato un nuovo contributo relativo a: {$title}.\n\n{$public_text}\n\nApri la segnalazione:\n{$url}";
			$html = '<p>È stato pubblicato un nuovo contributo relativo a <strong>' . esc_html( $title ) . '</strong>.</p><p>' . nl2br( esc_html( $public_text ) ) . '</p><p><a href="' . esc_url( $url ) . '">Apri la segnalazione</a></p>';
		}

		$sent = ( new BadAround_Transactional_Mailer() )->send( $destination, $subject, $message, $html );
		if ( is_wp_error( $sent ) ) {
			$this->reporter_notification_retry_or_fail( $notification, $sent->get_error_code() );
			return;
		}

		$wpdb->update(
			$wpdb->prefix . 'ba_contribution_notifications',
			array(
				'status' => 'sent',
				'sent_at' => current_time( 'mysql', true ),
				'next_retry_at' => null,
				'last_error_code' => null,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $notification_id ) )
		);
		BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reporter_notification_sent', 'contribution' );
	}

	public function maybe_render_reserved_access() {
		if ( empty( $_GET['ba_contribution_reserved'] ) ) {
			return;
		}

		$parts = explode( '.', sanitize_text_field( wp_unslash( $_GET['ba_contribution_reserved'] ) ), 2 );
		$status = 'invalid';
		$content = '';

		if ( 2 === count( $parts ) ) {
			$notification = $this->reporter_notification_by_access_id( $parts[0] );
			if ( $notification && 'reserved' === $notification['notification_kind'] ) {
				$row = $this->repository->find_by_id( $notification['contribution_id'] );
				$expires = ! empty( $notification['access_expires_at'] ) ? strtotime( $notification['access_expires_at'] . ' UTC' ) : 0;
				$expected = $row ? $this->reserved_access_token( $notification['access_public_id'], $row['id'], $notification['recipient_hash'], $notification['access_expires_at'] ) : '';
				if ( $expires && $expires < time() ) {
					$status = 'expired';
				} elseif (
					$row &&
					BadAround_Contribution_Repository::STATUS_RESERVED === $row['status'] &&
					$expected &&
					hash_equals( (string) $notification['access_token_hash'], hash( 'sha256', $parts[1] ) ) &&
					hash_equals( $expected, $parts[1] )
				) {
					$status = 'valid';
					$content = (string) $row['recipient_text'];
					BadAround_Audit_Log::record( 'contribution', $row['id'], 'contribution_reserved_access_opened', 'contribution' );
				}
			}
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Informazione riservata — BadAround</title></head><body>';
		echo '<main style="max-width:720px;margin:48px auto;padding:24px;font-family:system-ui,sans-serif"><h1>Informazione riservata</h1>';
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

	private function reporter_notification_for_contribution( $contribution_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ba_contribution_notifications WHERE contribution_id = %d LIMIT 1",
				absint( $contribution_id )
			),
			ARRAY_A
		);
	}

	private function reporter_notification_by_id( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ba_contribution_notifications WHERE id = %d LIMIT 1",
				absint( $id )
			),
			ARRAY_A
		);
	}

	private function reporter_notification_by_access_id( $public_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ba_contribution_notifications WHERE access_public_id = %s LIMIT 1",
				sanitize_text_field( $public_id )
			),
			ARRAY_A
		);
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
		if ( empty( $row['public_projection_id'] ) ) {
			return '';
		}
		$post = get_post( absint( $row['public_projection_id'] ) );
		return $post ? wp_strip_all_tags( $post->post_content ) : '';
	}

	private function reserved_access_url( array $notification, array $row ) {
		$token = $this->reserved_access_token( $notification['access_public_id'], $row['id'], $notification['recipient_hash'], $notification['access_expires_at'] );
		return add_query_arg( 'ba_contribution_reserved', rawurlencode( $notification['access_public_id'] . '.' . $token ), home_url( '/' ) );
	}

	private function reserved_access_token( $public_id, $contribution_id, $recipient_hash, $expires_at ) {
		return hash_hmac(
			'sha256',
			'contribution-reserved|' . $public_id . '|' . absint( $contribution_id ) . '|' . $recipient_hash . '|' . $expires_at,
			wp_salt( 'auth' )
		);
	}

	private function reporter_notification_retry_or_fail( array $notification, $error_code ) {
		global $wpdb;
		$attempts = absint( $notification['notification_attempts'] );
		if ( $attempts >= 3 ) {
			$this->reporter_notification_mark_failed( $notification, $error_code );
			return;
		}

		$delay = 1 === $attempts ? 300 : 1800;
		$next = gmdate( 'Y-m-d H:i:s', time() + $delay );
		$wpdb->update(
			$wpdb->prefix . 'ba_contribution_notifications',
			array(
				'status' => 'retry',
				'next_retry_at' => $next,
				'last_error_code' => sanitize_key( $error_code ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $notification['id'] ) )
		);
		wp_schedule_single_event( strtotime( $next . ' UTC' ), 'badaround_contribution_notify_reporter', array( absint( $notification['id'] ) ) );
	}

	private function reporter_notification_mark_failed( array $notification, $error_code ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ba_contribution_notifications',
			array(
				'status' => 'failed',
				'next_retry_at' => null,
				'last_error_code' => sanitize_key( $error_code ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $notification['id'] ) )
		);
		BadAround_Audit_Log::record( 'contribution', $notification['contribution_id'], 'contribution_reporter_notification_failed', 'contribution', sanitize_key( $error_code ) );
	}

	private function reporter_notification_destination( $recipient ) {
		if ( ! $this->reporter_notification_is_staging() ) {
			return $recipient;
		}
		$override = defined( 'BADAROUND_CONTRIBUTION_STAGING_MAIL_TO' ) ? sanitize_email( BADAROUND_CONTRIBUTION_STAGING_MAIL_TO ) : '';
		if ( ! $override && defined( 'BADAROUND_SENTINEL_STAGING_MAIL_TO' ) ) {
			$override = sanitize_email( BADAROUND_SENTINEL_STAGING_MAIL_TO );
		}
		return $override && is_email( $override ) ? $override : '';
	}

	private function reporter_subject_prefix() {
		return $this->reporter_notification_is_staging() ? '[STAGING BadAround] ' : 'BadAround — ';
	}

	private function reporter_notification_is_staging() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		return 'production' !== $environment || false !== strpos( $host, 'staging.' );
	}

}
