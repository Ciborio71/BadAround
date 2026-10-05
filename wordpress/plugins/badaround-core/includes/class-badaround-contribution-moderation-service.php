<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Moderation_Service {
	private $repository;

	public function __construct() {
		$this->repository = new BadAround_Contribution_Repository();
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
			$visibility_decided = 'public';
		}

		if ( BadAround_Contribution_Repository::STATUS_RESERVED === $target_status ) {
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
}
