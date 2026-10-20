<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-badaround-public-geo-contract.php';
require_once __DIR__ . '/class-badaround-public-geo-projection.php';

/**
 * Owns the controlled B3 transition from approved moderation to public WordPress content.
 */
class BadAround_Publication_Service {
	const STATUS_PUBLISHED = 'published';
	private $b3_event = 0;
	private static $geo_writer = 0;

	public static function register_geo_guards() {
		foreach ( array( 'add_post_metadata', 'update_post_metadata', 'delete_post_metadata' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'guard_geo_write' ), 999, 5 );
		}
	}

	public static function guard_geo_write( $check, $event_id, $key, $value = null, $extra = null ) {
		if ( ! in_array( $key, array( '_ba_public_geo', '_ba_public_geo_proof', '_ba_public_lat', '_ba_public_lng', '_ba_public_radius_m' ), true ) ) { return $check; }
		if ( (int) $event_id === self::$geo_writer ) { return $check; }
		if ( in_array( $key, array( '_ba_public_geo', '_ba_public_geo_proof' ), true ) || self::uses_versioned_geo( $event_id ) ) { return false; }
		return $check; // Legacy editor behavior is unchanged.
	}

	public static function uses_versioned_geo( $event_id ) {
		global $wpdb;
		if ( '' !== get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true )
			|| '' !== get_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, true ) ) { return true; }
		$has = $wpdb->get_var( $wpdb->prepare( "SELECT CASE WHEN JSON_VALID(content_original) THEN JSON_CONTAINS_PATH(content_original, 'one', '$.canonical.location.public_location_request', '$.canonical.location.public_location_request_version') ELSE 0 END FROM {$wpdb->prefix}ba_reports WHERE event_id = %d AND deleted_at IS NULL ORDER BY id ASC LIMIT 1", $event_id ) );
		return ! empty( $wpdb->last_error ) || (int) $has > 0;
	}

	/**
	 * Builds a conservative public projection from already classified,
	 * structured data. Private free text and full plates are excluded.
	 * Versioned coordinates are created only inside the controlled B3 transaction.
	 */
	public function prepare_public_projection( $event_id ) {
		$event_id = absint( $event_id );
		$post     = get_post( $event_id );

		if ( ! $post || BadAround_Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ba_publication_invalid_event', __( 'Evento non valido.', 'badaround-core' ) );
		}
		$geo_gate = $this->versioned_geo_gate( $event_id );
		if ( is_wp_error( $geo_gate ) ) { return $geo_gate; }

		$territory = $this->deepest_term( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		$subtype   = $this->deepest_term( $event_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
		$precision = sanitize_key( (string) get_post_meta( $event_id, '_ba_public_location_precision', true ) );
		if ( $this->b3_event === $event_id && 'municipality' === ( get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true )['mode'] ?? '' ) ) { $precision = 'f32-c7'; }

		/*
		 * Municipality precision is a semantic boundary, not merely a larger
		 * radius. The public taxonomy and label must stop at the canonical
		 * Comune level even when the event was resolved to a deeper locality.
		 */
		if ( 'f32-c7' === $precision ) {
			$municipality = $this->municipality_term( $territory );
			if ( ! $municipality ) {
				return new WP_Error( 'ba_publication_municipality_missing', __( 'Il Comune canonico non è risolvibile dalla gerarchia territoriale.', 'badaround-core' ) );
			}

			$territory_result = wp_set_object_terms(
				$event_id,
				array( (int) $municipality->term_id ),
				BadAround_Event_Post_Type::TERRITORY_TAX,
				false
			);
			if ( is_wp_error( $territory_result ) ) {
				return $territory_result;
			}

			$territory = $municipality;
			$place_name = sanitize_text_field( $municipality->name );
			update_post_meta( $event_id, '_ba_public_place_name', $place_name );
			delete_post_meta( $event_id, '_ba_public_address' );
		} else {
			$place_name = trim( (string) get_post_meta( $event_id, '_ba_public_place_name', true ) );
			if ( '' === $place_name && $territory ) {
				$place_name = sanitize_text_field( $territory->name );
				update_post_meta( $event_id, '_ba_public_place_name', $place_name );
			}
		}

		$location_result = $this->ensure_public_location( $event_id, $territory );
		if ( is_wp_error( $location_result ) ) {
			return $location_result;
		}

		$title = trim( wp_strip_all_tags( (string) $post->post_title ) );
		if ( '' === $title || preg_match( '/^Segnalazione da moderare\s*#/i', $title ) ) {
			$subject = $subtype ? sanitize_text_field( $subtype->name ) : __( 'Segnalazione', 'badaround-core' );
			$title   = $place_name
				? sprintf( __( '%1$s a %2$s', 'badaround-core' ), $subject, $place_name )
				: $subject;
		}

		$content = trim( wp_strip_all_tags( (string) $post->post_content ) );
		if ( '' === $content ) {
			$parts   = array();
			$subject = $subtype ? sanitize_text_field( $subtype->name ) : __( 'evento segnalato', 'badaround-core' );

			if ( $place_name ) {
				$parts[] = sprintf(
					__( 'Segnalazione relativa a %1$s nella zona di %2$s.', 'badaround-core' ),
					$subject,
					$place_name
				);
			} else {
				$parts[] = sprintf( __( 'Segnalazione relativa a %s.', 'badaround-core' ), $subject );
			}

			$occurred_date = trim( (string) get_post_meta( $event_id, '_ba_occurred_date', true ) );
			$occurred_time = trim( (string) get_post_meta( $event_id, '_ba_occurred_time', true ) );
			if ( $occurred_date && $occurred_time ) {
				$parts[] = sprintf( __( 'Evento indicato per il %1$s alle %2$s.', 'badaround-core' ), $occurred_date, $occurred_time );
			} elseif ( $occurred_date ) {
				$parts[] = sprintf( __( 'Evento indicato per il %s.', 'badaround-core' ), $occurred_date );
			}

			$vehicle_bits = array_filter(
				array(
					trim( (string) get_post_meta( $event_id, '_ba_vehicle_make', true ) ),
					trim( (string) get_post_meta( $event_id, '_ba_vehicle_model', true ) ),
				)
			);
			$vehicle_color = trim( (string) get_post_meta( $event_id, '_ba_vehicle_color', true ) );
			if ( $vehicle_bits || $vehicle_color ) {
				$vehicle = trim( implode( ' ', $vehicle_bits ) );
				if ( $vehicle && $vehicle_color ) {
					$parts[] = sprintf( __( 'Veicolo: %1$s, colore %2$s.', 'badaround-core' ), $vehicle, $vehicle_color );
				} elseif ( $vehicle ) {
					$parts[] = sprintf( __( 'Veicolo: %s.', 'badaround-core' ), $vehicle );
				} else {
					$parts[] = sprintf( __( 'Colore del veicolo: %s.', 'badaround-core' ), $vehicle_color );
				}
			}

			$content = implode( ' ', $parts );
		}

		/*
		 * B2 publish guards must remain in force for all new publications.
		 * Only a previously published event with a matching frozen moderation
		 * state may preserve its current publish status during projection edits.
		 */
		$preserve_published = 'publish' === $post->post_status
			&& self::STATUS_PUBLISHED === sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		$allow_projection_update = static function ( $allowed, $candidate_id ) use ( $event_id ) {
			return absint( $candidate_id ) === $event_id ? true : $allowed;
		};
		if ( $preserve_published ) {
			add_filter( 'badaround_allow_event_publish', $allow_projection_update, 999, 2 );
		}
		try {
			$updated = wp_update_post(
				array(
					'ID'           => $event_id,
					'post_title'   => sanitize_text_field( $title ),
					'post_content' => wp_kses_post( $content ),
				),
				true
			);
		} finally {
			if ( $preserve_published ) {
				remove_filter( 'badaround_allow_event_publish', $allow_projection_update, 999 );
			}
		}
		if ( $preserve_published && ! is_wp_error( $updated ) && 'publish' !== get_post_status( $event_id ) ) {
			return new WP_Error( 'ba_publication_projection_status_changed', __( 'La proiezione pubblica ha modificato lo stato di pubblicazione.', 'badaround-core' ) );
		}

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		BadAround_Audit_Log::record( 'event', $event_id, 'public_projection_prepared', 'publication' );
		return true;
	}

	public function validate_public_projection( $event_id, $allow_published_recovery = false ) {
		global $wpdb;

		$event_id = absint( $event_id );
		$post     = get_post( $event_id );
		if ( ! $post || BadAround_Event_Post_Type::POST_TYPE !== $post->post_type ) {
			return new WP_Error( 'ba_publication_invalid_event', __( 'Evento non valido.', 'badaround-core' ) );
		}
		$geo_gate = $this->versioned_geo_gate( $event_id );
		if ( is_wp_error( $geo_gate ) ) { return $geo_gate; }

		$moderation_status = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		if ( BadAround_Moderation_Service::STATUS_APPROVED !== $moderation_status && ! ( $allow_published_recovery && self::STATUS_PUBLISHED === $moderation_status ) ) {
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
		if ( is_wp_error( $territories ) || ! $territories || ! $this->municipality_term( $this->deepest_term( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX ) ) ) {
			return new WP_Error( 'ba_publication_territory_missing', __( 'È necessario un territorio pubblico canonico valido fino al Comune.', 'badaround-core' ) );
		}

		$occurred_date = trim( (string) get_post_meta( $event_id, '_ba_occurred_date', true ) );
		$occurred_at   = trim( (string) get_post_meta( $event_id, '_ba_occurred_at', true ) );
		$time_precision = sanitize_key( (string) get_post_meta( $event_id, '_ba_time_precision', true ) );
		$canonical_time_precisions = array( 'f16-c4', 'f16-c5', 'f16-c6', 'f16-c7', 'f16-c8' );

		if ( $time_precision && ! in_array( $time_precision, $canonical_time_precisions, true ) ) {
			return new WP_Error( 'ba_publication_time_invalid', __( 'Lo stato temporale pubblico non è riconosciuto.', 'badaround-core' ) );
		}

		/* Preserve frozen Native time semantics: exact requires date; other canonical
		 * modes, including f16-c8 (unknown), never fabricate an occurred timestamp. */
		$valid_without_date = in_array( $time_precision, array( 'f16-c5', 'f16-c6', 'f16-c7', 'f16-c8' ), true );
		if ( '' === $occurred_date && '' === $occurred_at && ! $valid_without_date ) {
			return new WP_Error( 'ba_publication_date_missing', __( 'La data pubblicabile dell’evento è obbligatoria quando il tempo è noto in modo esatto.', 'badaround-core' ) );
		}

		$place_name = trim( (string) get_post_meta( $event_id, '_ba_public_place_name', true ) );
		$public_lat = get_post_meta( $event_id, '_ba_public_lat', true );
		$public_lng = get_post_meta( $event_id, '_ba_public_lng', true );
		$radius     = absint( get_post_meta( $event_id, '_ba_public_radius_m', true ) );
		$has_public_coords = is_numeric( $public_lat ) && is_numeric( $public_lng );
		if ( ( is_numeric( $public_lat ) xor is_numeric( $public_lng ) ) || ( ! $has_public_coords && $radius > 0 ) ) {
			return new WP_Error( 'ba_publication_incomplete_public_coordinates', __( 'Le coordinate pubbliche devono essere complete oppure assenti.', 'badaround-core' ) );
		}
		$precision = sanitize_key( (string) get_post_meta( $event_id, '_ba_public_location_precision', true ) );
		if ( $this->b3_event === $event_id && 'municipality' === ( get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true )['mode'] ?? '' ) ) { $precision = 'f32-c7'; }

		if ( 'f32-c7' === $precision ) {
			$territory = $this->deepest_term( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX );
			$municipality = $this->municipality_term( $territory );
			if ( ! $municipality ) {
				return new WP_Error( 'ba_publication_municipality_missing', __( 'Il Comune canonico non è risolvibile dalla gerarchia territoriale.', 'badaround-core' ) );
			}
			if ( ! $territory || (int) $territory->term_id !== (int) $municipality->term_id ) {
				return new WP_Error( 'ba_publication_municipality_territory_too_precise', __( 'Il territorio pubblico deve fermarsi al Comune canonico.', 'badaround-core' ) );
			}
			if ( sanitize_text_field( $municipality->name ) !== $place_name ) {
				return new WP_Error( 'ba_publication_municipality_label_invalid', __( 'La località pubblica deve coincidere con il Comune canonico.', 'badaround-core' ) );
			}
			if ( '' !== trim( (string) get_post_meta( $event_id, '_ba_public_address', true ) ) ) {
				return new WP_Error( 'ba_publication_municipality_address_too_precise', __( 'Con precisione comunale non può essere esposto un indirizzo più preciso.', 'badaround-core' ) );
			}
			if ( $has_public_coords ) {
				$verified = strtolower( trim( (string) get_term_meta( $municipality->term_id, '_ba_geo_verified', true ) ) );
				$center_lat = get_term_meta( $municipality->term_id, '_ba_center_lat', true );
				$center_lng = get_term_meta( $municipality->term_id, '_ba_center_lng', true );
				$center_matches = is_numeric( $center_lat ) && is_numeric( $center_lng )
					&& abs( (float) $public_lat - (float) $center_lat ) < 0.000001
					&& abs( (float) $public_lng - (float) $center_lng ) < 0.000001;
				if ( ! in_array( $verified, array( '1', 'true', 'yes', 'on' ), true ) || ! $center_matches || $radius < 3000 ) {
					return new WP_Error( 'ba_publication_municipality_coordinates_invalid', __( 'Le coordinate pubbliche comunali devono provenire da un centro territoriale verificato.', 'badaround-core' ) );
				}
			}
		}

		if ( '' === $place_name && ! $has_public_coords ) {
			return new WP_Error( 'ba_publication_location_missing', __( 'È necessaria una posizione pubblica o approssimata.', 'badaround-core' ) );
		}
		$explicit_exact = $this->b3_event === $event_id && 'exact_point' === ( get_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, true )['mode'] ?? '' );
		if ( $has_public_coords && $radius < 100 && ! $explicit_exact ) {
			return new WP_Error( 'ba_publication_location_too_precise', __( 'Le coordinate pubbliche devono avere un raggio di approssimazione adeguato.', 'badaround-core' ) );
		}

		$primary_sql = $this->b3_event === $event_id ? $wpdb->prepare( ' AND id = %d', absint( get_post_meta( $event_id, '_ba_primary_report_id', true ) ) ) : '';
		$report = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT author_name, author_surname, author_email, author_phone, exact_address, exact_lat, exact_lng, full_plate, content_original
				 FROM {$wpdb->prefix}ba_reports
				 WHERE event_id = %d AND deleted_at IS NULL {$primary_sql}
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

		$private_identity = trim( strtolower( trim( (string) $report->author_name . ' ' . (string) $report->author_surname ) ) );
		foreach ( array( $private_identity, $report->author_email, $report->author_phone, $report->exact_address ) as $private_value ) {
			$private_value = trim( strtolower( (string) $private_value ) );
			if ( strlen( $private_value ) >= 5 && false !== strpos( $public_text, $private_value ) ) {
				return new WP_Error( 'ba_publication_private_data_detected', __( 'La proiezione pubblica contiene dati del report riservato.', 'badaround-core' ) );
			}
		}

		if ( $explicit_exact ) {
			$verified = BadAround_Public_Geo_Projection::verified( $event_id );
			if ( is_wp_error( $verified ) ) { return $verified; }
		}
		if ( ! $explicit_exact && $has_public_coords && null !== $report->exact_lat && null !== $report->exact_lng ) {
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

	/** Never route a new request/decision through the legacy public projection. */
	private function versioned_geo_gate( $event_id ) {
		global $wpdb;
		// Read only a presence flag, never load private coordinates/address for
		// this gate (especially on the municipality-only projection path).
		$has_request = $wpdb->get_var( $wpdb->prepare(
			"SELECT CASE WHEN JSON_VALID(content_original) THEN JSON_CONTAINS_PATH(content_original, 'one',
			 '$.canonical.location.public_location_request', '$.canonical.location.public_location_request_version') ELSE 0 END
			 FROM {$wpdb->prefix}ba_reports WHERE event_id = %d AND deleted_at IS NULL ORDER BY id ASC LIMIT 1",
			$event_id
		) );
		if ( ! empty( $wpdb->last_error ) ) {
			return new WP_Error( 'ba_publication_geo_report_unavailable', __( 'Il report geografico riservato non è verificabile.', 'badaround-core' ) );
		}
		if ( (int) $has_request > 0
			|| '' !== get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true )
			|| '' !== get_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, true ) ) {
			if ( $this->b3_event === (int) $event_id ) { return true; }
			return new WP_Error( 'ba_publication_geo_contract_not_active', __( 'La nuova richiesta geografica richiede il percorso di pubblicazione versionato, non ancora attivo.', 'badaround-core' ) );
		}
		return true;
	}

	private function ensure_public_location( $event_id, $territory = null ) {
		global $wpdb;
		if ( $this->b3_event === (int) $event_id ) { return $this->create_versioned_geo( $event_id ); }

		$precision = sanitize_key( (string) get_post_meta( $event_id, '_ba_public_location_precision', true ) );
		$public_lat = get_post_meta( $event_id, '_ba_public_lat', true );
		$public_lng = get_post_meta( $event_id, '_ba_public_lng', true );
		$radius     = absint( get_post_meta( $event_id, '_ba_public_radius_m', true ) );

		/*
		 * Municipality precision must never inherit a more precise public point
		 * and must never derive one from the private report coordinates.
		 * Only an explicitly verified canonical municipality centre is allowed.
		 */
		if ( 'f32-c7' === $precision ) {
			delete_post_meta( $event_id, '_ba_public_lat' );
			delete_post_meta( $event_id, '_ba_public_lng' );
			delete_post_meta( $event_id, '_ba_public_radius_m' );

			$municipality = $this->municipality_term( $territory );
			if ( ! $municipality ) {
				return new WP_Error( 'ba_publication_municipality_missing', __( 'Il Comune canonico non è risolvibile dalla gerarchia territoriale.', 'badaround-core' ) );
			}

			$verified = strtolower( trim( (string) get_term_meta( $municipality->term_id, '_ba_geo_verified', true ) ) );
			$center_lat = get_term_meta( $municipality->term_id, '_ba_center_lat', true );
			$center_lng = get_term_meta( $municipality->term_id, '_ba_center_lng', true );
			if ( in_array( $verified, array( '1', 'true', 'yes', 'on' ), true ) && is_numeric( $center_lat ) && is_numeric( $center_lng ) ) {
				update_post_meta( $event_id, '_ba_public_lat', round( (float) $center_lat, 6 ) );
				update_post_meta( $event_id, '_ba_public_lng', round( (float) $center_lng, 6 ) );
				update_post_meta( $event_id, '_ba_public_radius_m', 3000 );
			}

			return true;
		}

		if ( is_numeric( $public_lat ) && is_numeric( $public_lng ) && $radius >= 100 ) {
			return true;
		}

		/*
		 * F1.14R territory-only is a fallback when no valid private coordinates
		 * exist, not a default for point/street/area. Municipality is handled
		 * above and must never derive from private coordinates.
		 */

		$report = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT exact_lat, exact_lng FROM {$wpdb->prefix}ba_reports
				 WHERE event_id = %d AND deleted_at IS NULL
				 ORDER BY id ASC LIMIT 1",
				absint( $event_id )
			)
		);

		$valid_private_coords = $report
			&& is_numeric( $report->exact_lat ) && is_numeric( $report->exact_lng )
			&& is_finite( (float) $report->exact_lat ) && is_finite( (float) $report->exact_lng )
			&& (float) $report->exact_lat >= -90 && (float) $report->exact_lat <= 90
			&& (float) $report->exact_lng >= -180 && (float) $report->exact_lng <= 180;
		if ( ! $valid_private_coords ) {
			if ( ! $this->municipality_term( $territory ) ) {
				return new WP_Error( 'ba_publication_location_missing', __( 'Non sono disponibili coordinate sufficienti per creare una posizione pubblica approssimata.', 'badaround-core' ) );
			}
			delete_post_meta( $event_id, '_ba_public_lat' );
			delete_post_meta( $event_id, '_ba_public_lng' );
			delete_post_meta( $event_id, '_ba_public_radius_m' );
			return true;
		}

		$precision = sanitize_key( (string) get_post_meta( $event_id, '_ba_public_location_precision', true ) );
		$radii = array(
			'f32-c4' => 150,
			'f32-c5' => 300,
			'f32-c6' => 1000,
			'f32-c7' => 3000,
		);
		$radius = isset( $radii[ $precision ] ) ? $radii[ $precision ] : 750;


		$lat = (float) $report->exact_lat;
		$lng = (float) $report->exact_lng;

		/* Deterministic privacy offset: stable for the event, but impossible to
		 * reverse without the private source coordinates. */
		$seed = hash_hmac( 'sha256', 'ba-public-location|' . absint( $event_id ), wp_salt( 'auth' ) );
		$angle_fraction = hexdec( substr( $seed, 0, 8 ) ) / 4294967295;
		$angle = 2 * M_PI * $angle_fraction;
		$distance = max( 100, (int) round( $radius * 0.65 ) );
		$earth = 6378137;

		$lat_offset = ( $distance * cos( $angle ) / $earth ) * ( 180 / M_PI );
		$cos_lat = cos( deg2rad( $lat ) );
		$lng_offset = abs( $cos_lat ) > 0.000001
			? ( $distance * sin( $angle ) / ( $earth * $cos_lat ) ) * ( 180 / M_PI )
			: 0;

		$public_lat = round( $lat + $lat_offset, 6 );
		$public_lng = round( $lng + $lng_offset, 6 );

		if ( abs( $public_lat - $lat ) < 0.00001 && abs( $public_lng - $lng ) < 0.00001 ) {
			$public_lat = round( $lat + 0.0015, 6 );
		}

		update_post_meta( $event_id, '_ba_public_lat', $public_lat );
		update_post_meta( $event_id, '_ba_public_lng', $public_lng );
		update_post_meta( $event_id, '_ba_public_radius_m', $radius );

		BadAround_Audit_Log::record( 'event', $event_id, 'public_location_generalized', 'publication' );
		return true;
	}

	/**
	 * Resolve the canonical municipality from the frozen territorial hierarchy:
	 * Regione -> Provincia -> Comune -> Localita/Frazione/Quartiere.
	 *
	 * This intentionally uses ancestry only. Names are never interpreted as
	 * administrative levels, so a Provincia named "Roma" cannot be mistaken
	 * for a Comune named "Roma".
	 */
	private function municipality_term( $territory ) {
		if ( ! ( $territory instanceof WP_Term ) || BadAround_Event_Post_Type::TERRITORY_TAX !== $territory->taxonomy ) {
			return null;
		}

		$ancestor_ids = array_reverse(
			array_map(
				'absint',
				get_ancestors( $territory->term_id, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' )
			)
		);
		$chain = array_merge( $ancestor_ids, array( absint( $territory->term_id ) ) );

		/* Root = Regione, index 1 = Provincia, index 2 = Comune. */
		if ( count( $chain ) < 3 || empty( $chain[2] ) ) {
			return null;
		}

		$municipality = get_term( (int) $chain[2], BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( ! $municipality || is_wp_error( $municipality ) ) {
			return null;
		}

		return $municipality;
	}

	private function deepest_term( $event_id, $taxonomy ) {
		$terms = wp_get_post_terms( absint( $event_id ), $taxonomy );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return null;
		}

		usort(
			$terms,
			static function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, $b->taxonomy, 'taxonomy' ) ) <=> count( get_ancestors( $a->term_id, $a->taxonomy, 'taxonomy' ) );
			}
		);

		return reset( $terms ) ?: null;
	}

	public function publish( $event_id ) {
		$event_id = absint( $event_id );

		if ( ! $event_id || ! current_user_can( 'ba_moderate_events' ) || ! current_user_can( 'publish_ba_eventi' ) || ! current_user_can( 'edit_post', $event_id ) ) {
			return new WP_Error( 'ba_publication_forbidden', __( 'Non sei autorizzato a pubblicare questo evento.', 'badaround-core' ) );
		}
		if ( $this->b3_event !== $event_id && self::uses_versioned_geo( $event_id ) ) { return $this->publish_versioned( $event_id ); }

		if (class_exists('BadAround_Native_Media_Fence')) {
			$fence=BadAround_Native_Media_Fence::check($event_id);
			if (is_wp_error($fence)) return $fence;
		}

		$current = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );

		/*
		 * GEO-PRIV-02D.1: one-time recovery for event #290 only.  The event
		 * was previously published via B3 and became pending during projection
		 * regeneration.  No other pending event can use this exceptional path.
		 * The existing nonce-protected admin action and publish capabilities
		 * are checked before reaching this branch.
		 */
		if ( 290 === $event_id && self::STATUS_PUBLISHED === $current && 'pending' === get_post_status( $event_id ) ) {
			global $wpdb;
			$primary_report = absint( get_post_meta( $event_id, '_ba_primary_report_id', true ) );
			if ( 25 !== $primary_report ) {
				return new WP_Error( 'ba_geo_recovery_report_mismatch', __( 'Il report associato non coincide con il recupero autorizzato.', 'badaround-core' ) );
			}
			$has_publication_proof = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}ba_audit_log WHERE object_type = %s AND object_id = %d AND action = %s",
					'event', $event_id, 'event_published'
				)
			);
			if ( ! $has_publication_proof || ! empty( $wpdb->last_error ) ) {
				return new WP_Error( 'ba_geo_recovery_missing_audit', __( 'Non è dimostrabile la precedente pubblicazione.', 'badaround-core' ) );
			}
			$valid = $this->validate_public_projection( $event_id, true );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
			$allow = static function ( $allowed, $candidate_id ) use ( $event_id ) {
				return absint( $candidate_id ) === $event_id ? true : $allowed;
			};
			add_filter( 'badaround_allow_event_publish', $allow, 999, 2 );
			try {
				$updated = wp_update_post( array( 'ID' => $event_id, 'post_status' => 'publish' ), true );
			} finally {
				remove_filter( 'badaround_allow_event_publish', $allow, 999 );
			}
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			if ( 'publish' !== get_post_status( $event_id ) ) {
				return new WP_Error( 'ba_geo_recovery_publish_failed', __( 'Lo stato pubblico non è stato ripristinato.', 'badaround-core' ) );
			}
			BadAround_Audit_Log::record( 'event', $event_id, 'geo_priv_02d_publication_recovered', 'publication' );
			return true;
		}

		if ( self::STATUS_PUBLISHED === $current && 'publish' === get_post_status( $event_id ) ) {
			$prepared = $this->prepare_public_projection( $event_id );
			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}
			$media = new BadAround_Media_Repository();
			$approved = $media->approve_received_images_for_event( $event_id );
			if ( is_wp_error( $approved ) ) {
				return $approved;
			}
			$attachments = $media->materialize_approved_public_media_for_event( $event_id );
			if ( is_wp_error( $attachments ) ) {
				return $attachments;
			}
			if ( ! empty( $attachments ) && ! has_post_thumbnail( $event_id ) ) {
				set_post_thumbnail( $event_id, (int) reset( $attachments ) );
			}
			return true;
		}
		if ( BadAround_Moderation_Service::STATUS_APPROVED !== $current ) {
			return new WP_Error( 'ba_publication_transition_not_allowed', __( 'La pubblicazione è consentita solo dallo stato Approvato.', 'badaround-core' ) );
		}

		$valid = $this->validate_public_projection( $event_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$media_repository = new BadAround_Media_Repository();
		$media_approval = $media_repository->approve_received_images_for_event( $event_id );
		if ( is_wp_error( $media_approval ) ) {
			return $media_approval;
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

		$media_result = $media_repository->materialize_approved_public_media_for_event( $event_id );
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

		$publication_audit = BadAround_Audit_Log::transition(
			'event',
			$event_id,
			'event_published',
			BadAround_Moderation_Service::STATUS_APPROVED,
			self::STATUS_PUBLISHED,
			'publication'
		);
		if ( $this->b3_event === $event_id && ! $publication_audit ) { return $this->geo_error( 'audit_failed' ); }

		/*
		 * D2 starts only after B3 is fully committed. Listeners must remain
		 * asynchronous and must never make publication depend on delivery.
		 */
		if ( $this->b3_event !== $event_id ) { do_action( 'badaround_event_published', $event_id ); }

		return true;
	}

	/** Private source is loaded only inside controlled, capability-checked B3. */
	private function versioned_context( $event_id ) {
		global $wpdb;
		$primary = absint( get_post_meta( $event_id, '_ba_primary_report_id', true ) );
		if ( ! $primary ) { return $this->geo_error( 'report_missing' ); }
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, exact_lat, exact_lng, content_original FROM {$wpdb->prefix}ba_reports WHERE id = %d AND event_id = %d AND deleted_at IS NULL FOR UPDATE", $primary, $event_id ) );
		if ( ! $row || ! empty( $wpdb->last_error ) ) { return $this->geo_error( 'report_missing' ); }
		$stored = json_decode( $row->content_original, true );
		$location = $stored['canonical']['location'] ?? null;
		$decision = get_post_meta( $event_id, BadAround_Public_Geo_Contract::DECISION_META, true );
		$valid = BadAround_Public_Geo_Contract::validate_decision( $location, $decision );
		if ( is_wp_error( $valid ) ) { return $valid; }
		// Never source coordinates from an untrusted alternate JSON representation.
		if ( isset( $location['exact_lat'], $location['exact_lng'] ) &&
			( ! BadAround_Public_Geo_Projection::pair( $row->exact_lat, $row->exact_lng ) ||
			abs( (float) $location['exact_lat'] - (float) $row->exact_lat ) > 0.0000001 ||
			abs( (float) $location['exact_lng'] - (float) $row->exact_lng ) > 0.0000001 ) ) { return $this->geo_error( 'source_mismatch' ); }
		$location['exact_lat'] = $row->exact_lat; $location['exact_lng'] = $row->exact_lng;
		$precision = (string) get_post_meta( $event_id, '_ba_public_location_precision', true );
		$source = hash_hmac( 'sha256', wp_json_encode( array( $primary, $location, $precision ) ), wp_salt( 'auth' ) );
		return array( 'report_id' => $primary, 'location' => $location, 'decision' => $decision, 'precision' => $precision, 'source' => $source );
	}

	private function exact_review( $event_id, $context ) {
		$d = $context['decision'];
		// Explicit GEO-PUBLIC-04 confirmation is the positive server review.
		// The existing hook remains a veto/override review point, never an authority bypass.
		$review = static function ( $allowed ) use ( $context, $d ) {
			return true === $allowed || ( 'exact_point' === ( $context['location']['public_location_request'] ?? null ) && true === ( $d['exact_confirmed'] ?? false ) );
		};
		add_filter( 'badaround_public_geo_exact_allowed', $review, -999, 1 );
		try { return BadAround_Public_Geo_Contract::exact_safety_allowed( $event_id, $context['report_id'], $d ); }
		finally { remove_filter( 'badaround_public_geo_exact_allowed', $review, -999 ); }
	}

	private function create_versioned_geo( $event_id ) {
		if ( $this->b3_event !== (int) $event_id ) { return $this->geo_error( 'b3_required' ); }
		$c = $this->versioned_context( $event_id );
		if ( is_wp_error( $c ) ) { return $c; }
		if ( 'exact_point' === $c['decision']['mode'] && true !== ( $c['decision']['exact_confirmed'] ?? false ) ) { return $this->geo_error( 'exact_confirmation_required' ); }
		$existing = get_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, true );
		if ( '' !== $existing ) {
			$p = BadAround_Public_Geo_Projection::verified( $event_id );
			$proof = get_post_meta( $event_id, BadAround_Public_Geo_Projection::PROOF_META, true );
			if ( is_wp_error( $p ) || ! hash_equals( $c['source'], $proof['source'] ?? '' ) ) { return $this->geo_error( 'reprojection_forbidden' ); }
		} else {
			if ( 'publish' === get_post_status( $event_id ) || 'published' === get_post_meta( $event_id, '_ba_moderation_status', true ) ) { return $this->geo_error( 'reprojection_forbidden' ); }
			$p = BadAround_Public_Geo_Projection::build( $event_id, $c['location'], $c['decision'], $c['precision'] );
			if ( is_wp_error( $p ) ) { return $p; }
		}
		$exact_allowed = 'exact_point' === $c['decision']['mode'] ? $this->exact_review( $event_id, $c ) : false;
		$valid = BadAround_Public_Geo_Contract::validate_projection( $c['location'], $c['decision'], $p, 'b3', $exact_allowed );
		if ( is_wp_error( $valid ) ) { return $valid; }
		if ( '' !== $existing ) { return true; }
		self::$geo_writer = (int) $event_id;
		try {
			foreach ( array( '_ba_public_lat' => 'lat', '_ba_public_lng' => 'lng', '_ba_public_radius_m' => 'radius_meters' ) as $key => $field ) {
				if ( array_key_exists( $field, $p ) ) { update_post_meta( $event_id, $key, $p[ $field ] ); }
				else { delete_post_meta( $event_id, $key ); }
			}
			update_post_meta( $event_id, BadAround_Public_Geo_Contract::PROJECTION_META, $p );
			update_post_meta( $event_id, BadAround_Public_Geo_Projection::PROOF_META, array( 'source' => $c['source'], 'seal' => BadAround_Public_Geo_Projection::signature( $event_id, $p, $c['decision'], $c['source'] ) ) );
		} finally { self::$geo_writer = 0; }
		return is_wp_error( BadAround_Public_Geo_Projection::verified( $event_id ) ) ? $this->geo_error( 'storage_failed' ) : true;
	}

	/** Geometry, publication state and audit commit together; no public coordinates on failure. */
	private function publish_versioned( $event_id ) {
		global $wpdb;
		if ( ! current_user_can( 'ba_view_private_reports' ) ) { return $this->geo_error( 'forbidden' ); }
		$tables = array( $wpdb->posts, $wpdb->postmeta, $wpdb->prefix . 'ba_audit_log', $wpdb->prefix . 'ba_reports', $wpdb->term_relationships );
		foreach ( $tables as $table ) {
			if ( 'InnoDB' !== $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) { return $this->geo_error( 'transaction_unavailable' ); }
		}
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) { return $this->geo_error( 'transaction_unavailable' ); }
		$committed = false; $was_published = false;
		try {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT ID,post_type,post_status FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE", $event_id ) );
			clean_post_cache( $event_id );
			if ( ! $row || 'ba_evento' !== $row->post_type ) { return $this->geo_error( 'invalid_event' ); }
			$current = get_post_meta( $event_id, '_ba_moderation_status', true );
			$was_published = 'publish' === $row->post_status && 'published' === $current;
			if ( ! $was_published && ( ! in_array( $row->post_status, array( 'pending', 'draft' ), true ) || 'approved' !== $current ) ) { return $this->geo_error( 'transition_not_allowed' ); }
			if ( class_exists( 'BadAround_Native_Media_Fence' ) ) {
				$fence = BadAround_Native_Media_Fence::check( $event_id ); if ( is_wp_error( $fence ) ) { return $fence; }
			}
			$this->b3_event = $event_id;
			if ( $was_published ) {
				$result = $this->create_versioned_geo( $event_id );
				if ( ! is_wp_error( $result ) ) { $result = $this->validate_public_projection( $event_id, true ); }
			} else {
				$result = $this->prepare_public_projection( $event_id );
				if ( ! is_wp_error( $result ) ) { $result = $this->publish( $event_id ); }
				if ( ! is_wp_error( $result ) ) {
					$c = $this->versioned_context( $event_id );
					$p = BadAround_Public_Geo_Projection::verified( $event_id );
					if ( is_wp_error( $c ) || is_wp_error( $p ) || ! BadAround_Audit_Log::geographic_publication( $event_id, $c, $p ) ) { return $this->geo_error( 'audit_failed' ); }
				}
			}
			if ( is_wp_error( $result ) ) { return $result; }
			if ( 'publish' !== get_post_status( $event_id ) || 'published' !== get_post_meta( $event_id, '_ba_moderation_status', true ) ) { return $this->geo_error( 'state_failed' ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) { return $this->geo_error( 'storage_failed' ); }
			$committed = true;
		} catch ( Throwable $error ) { return $this->geo_error( 'storage_failed' ); }
		finally {
			$this->b3_event = 0; self::$geo_writer = 0;
			if ( ! $committed ) { $wpdb->query( 'ROLLBACK' ); }
			clean_post_cache( $event_id );
		}
		if ( ! $was_published ) { do_action( 'badaround_event_published', $event_id ); }
		return true;
	}

	private function geo_error( $code ) { return new WP_Error( 'ba_publication_geo_' . $code, __( 'Pubblicazione geografica non autorizzata o non verificabile.', 'badaround-core' ) ); }
}
