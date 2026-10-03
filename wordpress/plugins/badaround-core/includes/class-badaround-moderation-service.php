<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Applies the approved B2 moderation state machine without publishing events. */
class BadAround_Moderation_Service {
	const STATUS_NEW               = 'new';
	const STATUS_IN_REVIEW         = 'in_review';
	const STATUS_NEEDS_INFORMATION = 'needs_information';
	const STATUS_APPROVED          = 'approved';
	const STATUS_REJECTED          = 'rejected';
	const STATUS_ESCALATED         = 'escalated';

	public function transition( $event_id, $target_status, $reason = '' ) {
		$event_id      = absint( $event_id );
		$target_status = sanitize_key( $target_status );
		$reason        = sanitize_textarea_field( $reason );

		if ( ! $event_id || ! current_user_can( 'ba_moderate_events' ) || ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'ba_moderation_forbidden', __( 'Non sei autorizzato a moderare questo evento.', 'badaround-core' ) );
		}

		$post = get_post( $event_id );
		if ( ! $post || BadAround_Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ba_moderation_invalid_event', __( 'Evento non valido.', 'badaround-core' ) );
		}

		$current = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		if ( ! $current ) {
			$current = self::STATUS_NEW;
		}

		$allowed = array(
			self::STATUS_NEW               => array( self::STATUS_IN_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED ),
			self::STATUS_IN_REVIEW         => array( self::STATUS_APPROVED, self::STATUS_REJECTED ),
			self::STATUS_NEEDS_INFORMATION => array( self::STATUS_IN_REVIEW, self::STATUS_APPROVED, self::STATUS_REJECTED ),
		);

		if ( $current === $target_status ) {
			return true;
		}

		if ( empty( $allowed[ $current ] ) || ! in_array( $target_status, $allowed[ $current ], true ) ) {
			return new WP_Error( 'ba_moderation_transition_not_allowed', __( 'Transizione di moderazione non consentita.', 'badaround-core' ) );
		}

		if ( self::STATUS_REJECTED === $target_status && '' === $reason ) {
			return new WP_Error( 'ba_moderation_reason_required', __( 'Per rifiutare un evento è necessaria una motivazione.', 'badaround-core' ) );
		}

		if ( false === update_post_meta( $event_id, '_ba_moderation_status', $target_status ) ) {
			$stored = (string) get_post_meta( $event_id, '_ba_moderation_status', true );
			if ( $stored !== $target_status ) {
				return new WP_Error( 'ba_moderation_update_failed', __( 'Impossibile aggiornare lo stato di moderazione.', 'badaround-core' ) );
			}
		}

		/*
		 * B2 must never publish. Approved and rejected events stay non-public;
		 * B3 owns the explicit transition to WordPress publish.
		 */
		if ( 'pending' !== get_post_status( $event_id ) ) {
			wp_update_post(
				array(
					'ID'          => $event_id,
					'post_status' => 'pending',
				)
			);
		}

		if ( self::STATUS_APPROVED === $target_status ) {
			$this->ensure_masked_plate( $event_id );
		}

		$action = array(
			self::STATUS_IN_REVIEW => 'moderation_started',
			self::STATUS_APPROVED  => 'moderation_approved',
			self::STATUS_REJECTED  => 'moderation_rejected',
		);

		BadAround_Audit_Log::transition(
			'event',
			$event_id,
			isset( $action[ $target_status ] ) ? $action[ $target_status ] : 'moderation_transition',
			$current,
			$target_status,
			'moderation',
			$reason
		);

		return true;
	}

	public static function label( $status ) {
		$labels = array(
			self::STATUS_NEW               => __( 'Da moderare', 'badaround-core' ),
			self::STATUS_IN_REVIEW         => __( 'In verifica', 'badaround-core' ),
			self::STATUS_NEEDS_INFORMATION => __( 'Integrazione richiesta', 'badaround-core' ),
			self::STATUS_APPROVED          => __( 'Approvato', 'badaround-core' ),
			self::STATUS_REJECTED          => __( 'Rifiutato', 'badaround-core' ),
			self::STATUS_ESCALATED         => __( 'Escalation', 'badaround-core' ),
			BadAround_Publication_Service::STATUS_PUBLISHED => __( 'Pubblicato', 'badaround-core' ),
		);
		$status = sanitize_key( $status );
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	private function ensure_masked_plate( $event_id ) {
		global $wpdb;

		if ( get_post_meta( $event_id, '_ba_vehicle_plate_masked', true ) ) {
			return;
		}

		$table = $wpdb->prefix . 'ba_reports';
		$plate = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT full_plate FROM {$table} WHERE event_id = %d AND deleted_at IS NULL ORDER BY id ASC LIMIT 1",
				absint( $event_id )
			)
		);

		$plate = strtoupper( preg_replace( '/[^A-Z0-9]/', '', $plate ) );
		if ( strlen( $plate ) < 5 ) {
			return;
		}

		$masked = substr( $plate, 0, 2 ) . '***' . substr( $plate, -2 );
		update_post_meta( $event_id, '_ba_vehicle_plate_masked', $masked );
	}
}
