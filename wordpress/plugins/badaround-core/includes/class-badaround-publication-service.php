<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the controlled B3 transition from approved moderation to public WordPress content.
 */
class BadAround_Publication_Service {
	const STATUS_PUBLISHED = 'published';

	public function validate_public_projection( $event_id ) {
		global $wpdb;

		$event_id = absint( $event_id );
		$post     = get_post( $event_id );
		if ( ! $post || BadAround_Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ba_publication_invalid_event', __( 'Evento non valido.', 'badaround-core' ) );
		}

		$moderation_status = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		if ( BadAround_Moderation_Service::STATUS_APPROVED !== $moderation_status ) {
			return new WP_Error( 'ba_publication_not_approved', __( 'Solo un evento approvato può essere pubblicato.', 'badaround-core' ) );
		}

		$title   = trim( wp_strip_all_tags( (string) $post->post_title ) );
		$content = trim( wp_strip_all_tags( (string) $post->post_content ) );
		if ( '' === $title || preg_match( '/^Segnalazione da moderare\s*#/i', $title ) ) {
			return new WP_Error( 'ba_publication_title_missing', __( 'Il titolo pubblico deve essere completato prima della pubblicazione.', 'badaround-core' ) );
		}
		if ( '' === $content ) {
			return new WP_Error( 'ba_publication_content_missing', __( 'La descrizione pubblicabile è obbligatoria.', 'badaround-core' ) );
		}

		$types = wp_get_post_terms( $event_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
		if ( is_wp_error( $types ) || ! $types ) {
			return new WP_Error( 'ba_publication_type_missing', __( 'Categoria e sottocategoria sono obbligatorie.', 'badaround-core' ) );
		}
		$has_subcategory = false;
		foreach ( $types as $term ) {
			if ( ! empty( $term->parent ) ) {
				$has_subcategory = true;
				break;
			}
		}
		if ( ! $has_subcategory ) {
			return new WP_Error( 'ba_publication_subcategory_missing', __( 'È necessaria una sottocategoria pubblicabile.', 'badaround-core' ) );
		}

		$territories = wp_get_post_terms( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( is_wp_error( $territories ) || ! $territories ) {
			return new WP_Error( 'ba_publication_territory_missing', __( 'Il territorio pubblico è obbligatorio.', 'badaround-core' ) );
		}

		$occurred_date = trim( (string) get_post_meta( $event_id, '_ba_occurred_date', true ) );
		$occurred_at   = trim( (string) get_post_meta( $event_id, '_ba_occurred_at', true ) );
		if ( '' === $occurred_date && '' === $occurred_at ) {
			return new WP_Error( 'ba_publication_date_missing', __( 'La data pubblicabile dell’evento è obbligatoria.', 'badaround-core' ) );
		}

		$place_name = trim( (string) get_post_meta( $event_id, '_ba_public_place_name', true ) );
		$public_lat = get_post_meta( $event_id, '_ba_public_lat', true );
		$public_lng = get_post_meta( $event_id, '_ba_public_lng', true );
		$radius     = absint( get_post_meta( $event_id, '_ba_public_radius_m', true ) );
		$has_public_coords = is_numeric( $public_lat ) && is_numeric( $public_lng );
		if ( '' === $place_name && ! $has_public_coords ) {
			return new WP_Error( 'ba_publication_location_missing', __( 'È necessaria una posizione pubblica o approssimata.', 'badaround-core' ) );
		}
		if ( $has_public_coords && $radius < 100 ) {
			return new WP_Error( 'ba_publication_location_too_precise', __( 'Le coordinate pubbliche devono avere un raggio di approssimazione adeguato.', 'badaround-core' ) );
		}

		$report = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT author_name, author_surname, author_email, author_phone, exact_address, exact_lat, exact_lng, full_plate, content_original
				 FROM {$wpdb->prefix}ba_reports
				 WHERE event_id = %d AND deleted_at IS NULL
				 ORDER BY id ASC LIMIT 1",
				$event_id
			)
		);
		if ( ! $report ) {
			return new WP_Error( 'ba_publication_private_report_missing', __( 'Il report riservato collegato non è disponibile.', 'badaround-core' ) );
		}

		$public_text = strtolower( implode( "\n", array_filter( array(
			$title,
			$content,
			$place_name,
			(string) get_post_meta( $event_id, '_ba_public_address', true ),
			(string) get_post_meta( $event_id, '_ba_vehicle_plate_masked', true ),
		) ) ) );

		foreach ( array( $report->author_email, $report->author_phone, $report->exact_address ) as $private_value ) {
			$private_value = trim( strtolower( (string) $private_value ) );
			if ( strlen( $private_value ) >= 5 && false !== strpos( $public_text, $private_value ) ) {
				return new WP_Error( 'ba_publication_private_data_detected', __( 'La proiezione pubblica contiene dati del report riservato.', 'badaround-core' ) );
			}
		}

		if ( $has_public_coords && null !== $report->exact_lat && null !== $report->exact_lng ) {
			if ( abs( (float) $public_lat - (float) $report->exact_lat ) < 0.00001 && abs( (float) $public_lng - (float) $report->exact_lng ) < 0.00001 ) {
				return new WP_Error( 'ba_publication_exact_coordinates_detected', __( 'Le coordinate precise non possono essere pubblicate.', 'badaround-core' ) );
			}
		}

		$full_plate = strtoupper( preg_replace( '/[^A-Z0-9]/', '', (string) $report->full_plate ) );
		if ( $full_plate ) {
			$masked_plate = strtoupper( preg_replace( '/[^A-Z0-9*]/', '', (string) get_post_meta( $event_id, '_ba_vehicle_plate_masked', true ) ) );
			if ( '' === $masked_plate || $masked_plate === $full_plate || false !== strpos( strtoupper( $public_text ), $full_plate ) ) {
				return new WP_Error( 'ba_publication_plate_not_masked', __( 'La targa completa non può essere pubblicata.', 'badaround-core' ) );
			}
		}

		$sensitive_meta_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE post_id = %d
				   AND (
					   meta_key LIKE '%%author_email%%'
					   OR meta_key LIKE '%%author_phone%%'
					   OR meta_key LIKE '%%exact_address%%'
					   OR meta_key LIKE '%%exact_lat%%'
					   OR meta_key LIKE '%%exact_lng%%'
					   OR meta_key LIKE '%%full_plate%%'
					   OR meta_key LIKE '%%content_original%%'
				   )",
				$event_id
			)
		);
		if ( $sensitive_meta_count > 0 ) {
			return new WP_Error( 'ba_publication_sensitive_meta_detected', __( 'Sono presenti meta riservati sulla proiezione pubblica.', 'badaround-core' ) );
		}

		$public_media = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, review_status, sensitivity, public_attachment_id
				 FROM {$wpdb->prefix}ba_report_media
				 WHERE event_id = %d AND deleted_at IS NULL
				 ORDER BY id ASC",
				$event_id
			)
		);
		foreach ( $public_media as $media ) {
			if ( 'private' !== $media->sensitivity ) {
				return new WP_Error( 'ba_publication_media_segregation_invalid', __( 'Il media originale deve restare classificato come privato.', 'badaround-core' ) );
			}
			if ( ! empty( $media->public_attachment_id ) && ! get_post( (int) $media->public_attachment_id ) ) {
				return new WP_Error( 'ba_publication_public_media_missing', __( 'La copia media pubblica collegata non è valida.', 'badaround-core' ) );
			}
		}

		return true;
	}

	public function publish( $event_id ) {
		$event_id = absint( $event_id );

		if ( ! $event_id || ! current_user_can( 'ba_moderate_events' ) || ! current_user_can( 'publish_ba_eventi' ) || ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'ba_publication_forbidden', __( 'Non sei autorizzato a pubblicare questo evento.', 'badaround-core' ) );
		}

		$current = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		if ( self::STATUS_PUBLISHED === $current && 'publish' === get_post_status( $event_id ) ) {
			return true;
		}
		if ( BadAround_Moderation_Service::STATUS_APPROVED !== $current ) {
			return new WP_Error( 'ba_publication_transition_not_allowed', __( 'La pubblicazione è consentita solo dallo stato Approvato.', 'badaround-core' ) );
		}

		$valid = $this->validate_public_projection( $event_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$allow = static function ( $allowed, $candidate_id ) use ( $event_id ) {
			return absint( $candidate_id ) === $event_id ? true : $allowed;
		};
		add_filter( 'badaround_allow_event_publish', $allow, 999, 2 );

		$updated = wp_update_post(
			array(
				'ID'            => $event_id,
				'post_status'   => 'publish',
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
			),
			true
		);

		remove_filter( 'badaround_allow_event_publish', $allow, 999 );

		if ( is_wp_error( $updated ) || 'publish' !== get_post_status( $event_id ) ) {
			return is_wp_error( $updated )
				? $updated
				: new WP_Error( 'ba_publication_wp_status_failed', __( 'WordPress non ha confermato la pubblicazione.', 'badaround-core' ) );
		}

		$media_result = ( new BadAround_Media_Repository() )->materialize_approved_public_media_for_event( $event_id );
		if ( is_wp_error( $media_result ) ) {
			wp_update_post( array( 'ID' => $event_id, 'post_status' => 'pending' ) );
			return $media_result;
		}
		if ( ! empty( $media_result ) && ! has_post_thumbnail( $event_id ) ) {
			set_post_thumbnail( $event_id, (int) reset( $media_result ) );
		}

		update_post_meta( $event_id, '_ba_moderation_status', self::STATUS_PUBLISHED );
		if ( ! get_post_meta( $event_id, '_ba_event_status', true ) ) {
			update_post_meta( $event_id, '_ba_event_status', 'open' );
		}

		BadAround_Audit_Log::transition(
			'event',
			$event_id,
			'event_published',
			BadAround_Moderation_Service::STATUS_APPROVED,
			self::STATUS_PUBLISHED,
			'publication'
		);

		return true;
	}
}
