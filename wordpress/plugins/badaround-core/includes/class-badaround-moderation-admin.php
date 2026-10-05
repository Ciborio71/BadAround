<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Minimal, capability-gated B2 moderation UI for ba_evento. */
class BadAround_Moderation_Admin {
	public function register_hooks() {
		add_filter( 'views_edit-' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'add_moderation_queue_view' ) );
		add_action( 'add_meta_boxes_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'save_public_fields' ), 10, 2 );
		add_action( 'admin_post_ba_moderate_event', array( $this, 'handle_moderation_action' ) );
		add_action( 'admin_post_ba_publish_event', array( $this, 'handle_publish_action' ) );
		add_action( 'admin_post_ba_approve_public_media', array( $this, 'handle_public_media_action' ) );
		add_action( 'admin_post_ba_private_media', array( $this, 'serve_private_media' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_moderation_queue' ) );
		add_filter( 'manage_' . BadAround_Event_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'add_list_columns' ) );
		add_action( 'manage_' . BadAround_Event_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'render_list_column' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'prevent_b2_publish' ), 20, 2 );
		add_action( 'transition_post_status', array( $this, 'enforce_b2_pending_status' ), 20, 3 );
		add_action( 'admin_footer-post.php', array( $this, 'customize_native_publish_controls' ) );
	}

	public function add_moderation_queue_view( $views ) {
		if ( ! current_user_can( 'ba_moderate_events' ) ) {
			return $views;
		}
		$count = $this->moderation_queue_count();
		$url = add_query_arg(
			array(
				'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
				'ba_moderation_queue' => 1,
			),
			admin_url( 'edit.php' )
		);
		$current = ! empty( $_GET['ba_moderation_queue'] ) ? ' class="current" aria-current="page"' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$views['ba_moderation_queue'] = '<a href="' . esc_url( $url ) . '"' . $current . '>' . esc_html__( 'Da moderare', 'badaround-core' ) . ' <span class="count">(' . absint( $count ) . ')</span></a>';
		return $views;
	}

	public function filter_moderation_queue( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || BadAround_Event_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( empty( $_GET['ba_moderation_queue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$query->set( 'post_status', 'pending' );
		$query->set(
			'meta_query',
			array(
				array(
					'key'     => '_ba_moderation_status',
					'value'   => array(
						BadAround_Moderation_Service::STATUS_NEW,
						BadAround_Moderation_Service::STATUS_IN_REVIEW,
						BadAround_Moderation_Service::STATUS_NEEDS_INFORMATION,
					),
					'compare' => 'IN',
				),
			)
		);
	}

	public function add_list_columns( $columns ) {
		$columns['ba_moderation'] = __( 'Moderazione', 'badaround-core' );
		$columns['ba_report']     = __( 'Report', 'badaround-core' );
		return $columns;
	}

	public function render_list_column( $column, $post_id ) {
		if ( 'ba_moderation' === $column ) {
			$status = get_post_meta( $post_id, '_ba_moderation_status', true );
			echo esc_html( BadAround_Moderation_Service::label( $status ?: BadAround_Moderation_Service::STATUS_NEW ) );
		}
		if ( 'ba_report' === $column ) {
			echo esc_html( (string) absint( get_post_meta( $post_id, '_ba_primary_report_id', true ) ) );
		}
	}

	public function register_meta_boxes( $post ) {
		if ( ! current_user_can( 'ba_moderate_events' ) ) {
			return;
		}

		add_meta_box( 'ba_public_data', __( 'PUBBLICABILE — Dati canonici', 'badaround-core' ), array( $this, 'render_public_box' ), BadAround_Event_Post_Type::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'ba_private_report', __( 'RISERVATO / NON PUBBLICABILE', 'badaround-core' ), array( $this, 'render_private_box' ), BadAround_Event_Post_Type::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'ba_private_media', __( 'Media originali privati', 'badaround-core' ), array( $this, 'render_media_box' ), BadAround_Event_Post_Type::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'ba_audit', __( 'Audit essenziale', 'badaround-core' ), array( $this, 'render_audit_box' ), BadAround_Event_Post_Type::POST_TYPE, 'normal', 'default' );
		add_meta_box( 'ba_moderation_actions', __( 'Moderazione', 'badaround-core' ), array( $this, 'render_actions_box' ), BadAround_Event_Post_Type::POST_TYPE, 'side', 'high' );
	}

	public function render_public_box( $post ) {
		wp_nonce_field( 'ba_save_public_fields_' . $post->ID, 'ba_public_fields_nonce' );
		$terms = wp_get_post_terms( $post->ID, BadAround_Event_Post_Type::TERRITORY_TAX );
		$territory = ! is_wp_error( $terms ) && $terms ? $terms[0] : null;
		$fields = array(
			'_ba_occurred_date'     => __( 'Data evento', 'badaround-core' ),
			'_ba_occurred_time'     => __( 'Ora evento', 'badaround-core' ),
			'_ba_public_place_name' => __( 'Luogo pubblico', 'badaround-core' ),
			'_ba_public_address'    => __( 'Indirizzo pubblicabile', 'badaround-core' ),
			'_ba_public_lat'        => __( 'Latitudine pubblica/generalizzata', 'badaround-core' ),
			'_ba_public_lng'        => __( 'Longitudine pubblica/generalizzata', 'badaround-core' ),
			'_ba_public_radius_m'   => __( 'Raggio pubblico (m)', 'badaround-core' ),
			'_ba_vehicle_make'      => __( 'Marca veicolo', 'badaround-core' ),
			'_ba_vehicle_model'     => __( 'Modello veicolo', 'badaround-core' ),
			'_ba_vehicle_color'     => __( 'Colore veicolo', 'badaround-core' ),
			'_ba_vehicle_plate_masked' => __( 'Targa mascherata', 'badaround-core' ),
		);
		echo '<p><strong>' . esc_html__( 'Titolo pubblico', 'badaround-core' ) . '</strong><br><input class="widefat" name="ba_public_title" value="' . esc_attr( $post->post_title ) . '"></p>';
		echo '<p><strong>' . esc_html__( 'Descrizione pubblicabile', 'badaround-core' ) . '</strong><br><textarea class="widefat" rows="5" name="ba_public_content">' . esc_textarea( $post->post_content ) . '</textarea></p>';
		echo '<p><strong>' . esc_html__( 'Categoria / sottocategoria', 'badaround-core' ) . '</strong><br>' . wp_kses_post( $this->term_path( $post->ID, BadAround_Event_Post_Type::EVENT_TYPE_TAX ) ) . '</p>';
		echo '<p><label><strong>' . esc_html__( 'Territorio', 'badaround-core' ) . '</strong><br><select class="widefat" name="ba_territory_id">';
		echo '<option value="">' . esc_html__( '— Seleziona —', 'badaround-core' ) . '</option>';
		foreach ( $this->territory_options() as $term_id => $label ) {
			echo '<option value="' . absint( $term_id ) . '" ' . selected( $territory ? $territory->term_id : 0, $term_id, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label></p>';
		foreach ( $fields as $key => $label ) {
			echo '<p><label><strong>' . esc_html( $label ) . '</strong><br><input class="widefat" name="ba_public_meta[' . esc_attr( $key ) . ']" value="' . esc_attr( get_post_meta( $post->ID, $key, true ) ) . '"></label></p>';
		}
		echo '<p class="description">' . esc_html__( 'Solo questi campi canonici/pubblicabili vengono salvati qui. PII, indirizzo esatto, coordinate precise e targa completa restano nel report riservato.', 'badaround-core' ) . '</p>';
	}

	public function render_private_box( $post ) {
		if ( ! current_user_can( 'ba_view_private_reports' ) ) {
			echo '<p>' . esc_html__( 'Accesso non autorizzato.', 'badaround-core' ) . '</p>';
			return;
		}
		$report = $this->report_for_event( $post->ID );
		if ( ! $report ) {
			echo '<p>' . esc_html__( 'Nessun report riservato collegato.', 'badaround-core' ) . '</p>';
			return;
		}
		$payload = json_decode( (string) $report->content_original, true );
		$original_text = '';
		if ( is_array( $payload ) && ! empty( $payload['private_fields'][55] ) ) {
			$original_text = is_array( $payload['private_fields'][55] ) ? implode( "\n", array_map( 'strval', $payload['private_fields'][55] ) ) : (string) $payload['private_fields'][55];
		}
		$rows = array(
			__( 'Report ID', 'badaround-core' )       => $report->id,
			__( 'Segnalante', 'badaround-core' )      => trim( $report->author_name . ' ' . $report->author_surname ),
			__( 'Email', 'badaround-core' )           => $report->author_email,
			__( 'Telefono', 'badaround-core' )        => $report->author_phone,
			__( 'Indirizzo esatto', 'badaround-core' )=> $report->exact_address,
			__( 'Coordinate precise', 'badaround-core' ) => ( null !== $report->exact_lat && null !== $report->exact_lng ) ? $report->exact_lat . ', ' . $report->exact_lng : '—',
			__( 'Targa completa', 'badaround-core' )  => $report->full_plate ?: '—',
			__( 'Testo originale', 'badaround-core' ) => $original_text ?: '—',
		);
		echo '<div style="border-left:4px solid #d63638;padding-left:12px"><p><strong>' . esc_html__( 'RISERVATO — non copiare nei dati pubblici', 'badaround-core' ) . '</strong></p><table class="widefat striped"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th style="width:180px">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public function render_media_box( $post ) {
		if ( ! current_user_can( 'ba_view_private_media' ) ) {
			echo '<p>' . esc_html__( 'Accesso non autorizzato.', 'badaround-core' ) . '</p>';
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, original_filename, mime_type, file_size, review_status, sensitivity, public_attachment_id FROM {$wpdb->prefix}ba_report_media WHERE event_id = %d AND deleted_at IS NULL ORDER BY id ASC",
				$post->ID
			)
		);
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nessun media collegato.', 'badaround-core' ) . '</p>';
			return;
		}
		$moderation_status = sanitize_key( (string) get_post_meta( $post->ID, '_ba_moderation_status', true ) );
		foreach ( $rows as $row ) {
			$url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ba_private_media&media_id=' . absint( $row->id ) ),
				'ba_private_media_' . absint( $row->id )
			);
			echo '<p><strong>' . esc_html( $row->original_filename ) . '</strong><br>';
			echo esc_html( $row->mime_type . ' · ' . size_format( (int) $row->file_size ) . ' · ' . $row->sensitivity . ' · ' . $row->review_status ) . '<br>';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html__( 'Visualizza originale privato', 'badaround-core' ) . '</a>';
			if ( BadAround_Moderation_Service::STATUS_APPROVED === $moderation_status && 'received' === $row->review_status && current_user_can( 'ba_moderate_events' ) ) {
				$approve_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=ba_approve_public_media&event_id=' . absint( $post->ID ) . '&media_id=' . absint( $row->id ) ),
					'ba_approve_public_media_' . absint( $row->id )
				);
				echo ' <a class="button button-secondary" href="' . esc_url( $approve_url ) . '">' . esc_html__( 'Approva copia per pubblicazione', 'badaround-core' ) . '</a>';
			}
			if ( ! empty( $row->public_attachment_id ) ) {
				echo '<br><small>' . esc_html__( 'Copia pubblica collegata:', 'badaround-core' ) . ' #' . absint( $row->public_attachment_id ) . '</small>';
			}
			echo '</p>';
		}
	}

	public function render_audit_box( $post ) {
		if ( ! current_user_can( 'ba_view_audit_log' ) ) {
			echo '<p>' . esc_html__( 'Accesso non autorizzato.', 'badaround-core' ) . '</p>';
			return;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT actor_user_id, action, old_value, new_value, reason, context, created_at FROM {$wpdb->prefix}ba_audit_log WHERE object_type = 'event' AND object_id = %d ORDER BY id DESC LIMIT 12",
				$post->ID
			)
		);
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nessun record audit.', 'badaround-core' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Quando', 'badaround-core' ) . '</th><th>' . esc_html__( 'Azione', 'badaround-core' ) . '</th><th>' . esc_html__( 'Operatore', 'badaround-core' ) . '</th><th>' . esc_html__( 'Motivo', 'badaround-core' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr><td>' . esc_html( $row->created_at ) . '</td><td>' . esc_html( $row->action ) . '</td><td>' . esc_html( $row->actor_user_id ?: 'system' ) . '</td><td>' . esc_html( $row->reason ?: '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function render_actions_box( $post ) {
		$status = get_post_meta( $post->ID, '_ba_moderation_status', true );
		$status = $status ?: BadAround_Moderation_Service::STATUS_NEW;
		echo '<p><strong>' . esc_html__( 'Stato:', 'badaround-core' ) . '</strong> ' . esc_html( BadAround_Moderation_Service::label( $status ) ) . '</p>';
		echo '<p class="description">' . esc_html__( 'La pubblicazione è consentita solo tramite la transizione controllata B3.', 'badaround-core' ) . '</p>';
		if ( ! current_user_can( 'ba_moderate_events' ) ) {
			return;
		}
		if ( BadAround_Publication_Service::STATUS_PUBLISHED === $status ) {
			$permalink = get_permalink( $post->ID );
			$repair_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ba_publish_event&event_id=' . absint( $post->ID ) ),
				'ba_publish_event_' . absint( $post->ID )
			);
			echo '<p><strong>' . esc_html__( 'Evento pubblicato.', 'badaround-core' ) . '</strong></p>';
			if ( $permalink ) {
				echo '<p><a class="button button-secondary" target="_blank" rel="noopener" href="' . esc_url( $permalink ) . '">' . esc_html__( 'Apri evento pubblico', 'badaround-core' ) . '</a></p>';
			}
			echo '<p><a class="button" style="width:100%;text-align:center" href="' . esc_url( $repair_url ) . '">' . esc_html__( 'Aggiorna proiezione pubblica', 'badaround-core' ) . '</a></p>';
			return;
		}
		if ( BadAround_Moderation_Service::STATUS_APPROVED === $status ) {
			$publish_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ba_publish_event&event_id=' . absint( $post->ID ) ),
				'ba_publish_event_' . absint( $post->ID )
			);
			echo '<p><em>' . esc_html__( 'Moderazione approvata. La proiezione pubblica viene completata automaticamente prima della pubblicazione.', 'badaround-core' ) . '</em></p>';
			echo '<p><a class="button button-primary" style="width:100%;text-align:center" href="' . esc_url( $publish_url ) . '">' . esc_html__( 'Completa e pubblica', 'badaround-core' ) . '</a></p>';
			return;
		}
		if ( BadAround_Moderation_Service::STATUS_REJECTED === $status ) {
			echo '<p><em>' . esc_html__( 'Evento rifiutato: non pubblicabile.', 'badaround-core' ) . '</em></p>';
			return;
		}
		$base = admin_url( 'admin-post.php?action=ba_moderate_event&event_id=' . absint( $post->ID ) );
		foreach ( array(
			BadAround_Moderation_Service::STATUS_IN_REVIEW => __( 'Mantieni da moderare', 'badaround-core' ),
			BadAround_Moderation_Service::STATUS_APPROVED  => __( 'Approva e pubblica', 'badaround-core' ),
		) as $target => $label ) {
			$url = wp_nonce_url( $base . '&target=' . $target, 'ba_moderate_event_' . $post->ID );
			echo '<p><a class="button button-secondary" style="width:100%;text-align:center" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></p>';
		}
		echo '<hr><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ba_moderate_event"><input type="hidden" name="event_id" value="' . absint( $post->ID ) . '"><input type="hidden" name="target" value="' . esc_attr( BadAround_Moderation_Service::STATUS_REJECTED ) . '">';
		wp_nonce_field( 'ba_moderate_event_' . $post->ID );
		echo '<p><label><strong>' . esc_html__( 'Motivo del rifiuto', 'badaround-core' ) . '</strong><textarea class="widefat" rows="3" name="reason" required></textarea></label></p>';
		echo '<p><button class="button" type="submit">' . esc_html__( 'Rifiuta / scarta', 'badaround-core' ) . '</button></p></form>';
	}

	public function save_public_fields( $post_id, $post ) {
		if ( ! isset( $_POST['ba_public_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ba_public_fields_nonce'] ) ), 'ba_save_public_fields_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'ba_moderate_events' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$before_hash = hash( 'sha256', wp_json_encode( array( $post->post_title, $post->post_content, get_post_meta( $post_id ), wp_get_post_terms( $post_id, BadAround_Event_Post_Type::TERRITORY_TAX, array( 'fields' => 'ids' ) ) ) ) );

		remove_action( 'save_post_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'save_public_fields' ), 10 );
		$title   = isset( $_POST['ba_public_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ba_public_title'] ) ) : $post->post_title;
		$content = isset( $_POST['ba_public_content'] ) ? wp_kses_post( wp_unslash( $_POST['ba_public_content'] ) ) : $post->post_content;
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $title, 'post_content' => $content, 'post_status' => 'pending' ) );
		add_action( 'save_post_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'save_public_fields' ), 10, 2 );

		$allowed = array(
			'_ba_occurred_date',
			'_ba_occurred_time',
			'_ba_public_place_name',
			'_ba_public_address',
			'_ba_public_lat',
			'_ba_public_lng',
			'_ba_public_radius_m',
			'_ba_vehicle_make',
			'_ba_vehicle_model',
			'_ba_vehicle_color',
			'_ba_vehicle_plate_masked',
		);
		$input = isset( $_POST['ba_public_meta'] ) && is_array( $_POST['ba_public_meta'] ) ? wp_unslash( $_POST['ba_public_meta'] ) : array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( $input[ $key ] ) );
			}
		}

		if ( isset( $_POST['ba_territory_id'] ) ) {
			$territory_id = absint( $_POST['ba_territory_id'] );
			if ( $territory_id && term_exists( $territory_id, BadAround_Event_Post_Type::TERRITORY_TAX ) ) {
				wp_set_object_terms( $post_id, array( $territory_id ), BadAround_Event_Post_Type::TERRITORY_TAX, false );
			}
		}

		$after = get_post( $post_id );
		$after_hash = hash( 'sha256', wp_json_encode( array( $after ? $after->post_title : '', $after ? $after->post_content : '', get_post_meta( $post_id ), wp_get_post_terms( $post_id, BadAround_Event_Post_Type::TERRITORY_TAX, array( 'fields' => 'ids' ) ) ) ) );
		if ( $before_hash !== $after_hash ) {
			BadAround_Audit_Log::record( 'event', $post_id, 'moderation_public_data_updated', 'moderation' );
		}
	}

	public function handle_moderation_action() {
		$event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 0;
		check_admin_referer( 'ba_moderate_event_' . $event_id );
		if ( ! current_user_can( 'ba_moderate_events' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ), '', array( 'response' => 403 ) );
		}
		$target = isset( $_REQUEST['target'] ) ? sanitize_key( wp_unslash( $_REQUEST['target'] ) ) : '';
		$reason = isset( $_REQUEST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['reason'] ) ) : '';
		$result = ( new BadAround_Moderation_Service() )->transition( $event_id, $target, $reason );

		if ( ! is_wp_error( $result ) && BadAround_Moderation_Service::STATUS_APPROVED === $target ) {
			$publication = new BadAround_Publication_Service();
			$prepared    = $publication->prepare_public_projection( $event_id );
			$result      = is_wp_error( $prepared ) ? $prepared : $publication->publish( $event_id );

			$args = is_wp_error( $result )
				? array( 'ba_publication_error' => $result->get_error_code() )
				: array( 'ba_publication_updated' => 1 );
		} else {
			$args = is_wp_error( $result )
				? array( 'ba_moderation_error' => $result->get_error_code() )
				: array( 'ba_moderation_updated' => 1 );
		}

		wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $event_id, 'url' ) ) );
		exit;
	}

	public function handle_publish_action() {
		$event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 0;
		check_admin_referer( 'ba_publish_event_' . $event_id );
		$publication = new BadAround_Publication_Service();
		$prepared    = $publication->prepare_public_projection( $event_id );
		$result      = is_wp_error( $prepared ) ? $prepared : $publication->publish( $event_id );
		$args = is_wp_error( $result )
			? array( 'ba_publication_error' => $result->get_error_code() )
			: array( 'ba_publication_updated' => 1 );
		wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $event_id, 'url' ) ) );
		exit;
	}

	public function handle_public_media_action() {
		$event_id = isset( $_REQUEST['event_id'] ) ? absint( $_REQUEST['event_id'] ) : 0;
		$media_id = isset( $_REQUEST['media_id'] ) ? absint( $_REQUEST['media_id'] ) : 0;
		check_admin_referer( 'ba_approve_public_media_' . $media_id );
		if ( ! current_user_can( 'ba_moderate_events' ) || ! current_user_can( 'ba_view_private_media' ) || ! current_user_can( 'edit_post', $event_id ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ), '', array( 'response' => 403 ) );
		}
		$status = sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
		if ( BadAround_Moderation_Service::STATUS_APPROVED !== $status ) {
			$result = new WP_Error( 'ba_public_media_event_not_approved', __( 'Il media può essere approvato per il pubblico solo dopo l’approvazione dell’evento.', 'badaround-core' ) );
		} else {
			$result = ( new BadAround_Media_Repository() )->approve_for_publication( $media_id, $event_id );
		}
		$args = is_wp_error( $result )
			? array( 'ba_public_media_error' => $result->get_error_code() )
			: array( 'ba_public_media_updated' => 1 );
		wp_safe_redirect( add_query_arg( $args, get_edit_post_link( $event_id, 'url' ) ) );
		exit;
	}

	public function serve_private_media() {
		$media_id = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
		check_admin_referer( 'ba_private_media_' . $media_id );
		if ( ! current_user_can( 'ba_view_private_media' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ), '', array( 'response' => 403 ) );
		}
		$file = ( new BadAround_Media_Repository() )->private_file_for_media_id( $media_id );
		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 404 ) );
		}
		$row = $file['row'];
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . $row->mime_type );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $row->original_filename ) . '"' );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public function prevent_b2_publish( $data, $postarr ) {
		$post_id   = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
		$post_type = ! empty( $data['post_type'] ) ? $data['post_type'] : ( $post_id ? get_post_type( $post_id ) : '' );

		if ( BadAround_Event_Post_Type::POST_TYPE !== $post_type || empty( $data['post_status'] ) || 'publish' !== $data['post_status'] ) {
			return $data;
		}
		if ( apply_filters( 'badaround_allow_event_publish', false, $post_id ) ) {
			return $data;
		}
		$data['post_status'] = 'pending';
		return $data;
	}

	public function enforce_b2_pending_status( $new_status, $old_status, $post ) {
		if ( ! $post || BadAround_Event_Post_Type::POST_TYPE !== $post->post_type || 'publish' !== $new_status ) {
			return;
		}
		if ( apply_filters( 'badaround_allow_event_publish', false, $post->ID ) ) {
			return;
		}

		remove_action( 'transition_post_status', array( $this, 'enforce_b2_pending_status' ), 20 );
		wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'pending' ) );
		add_action( 'transition_post_status', array( $this, 'enforce_b2_pending_status' ), 20, 3 );

		BadAround_Audit_Log::record( 'event', $post->ID, 'b2_publish_blocked', 'moderation', 'automatic publication blocked before B3' );
	}

	public function customize_native_publish_controls() {
		$screen = get_current_screen();
		if ( ! $screen || BadAround_Event_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		echo '<script>(function(){var b=document.getElementById("publish");if(b){b.value="' . esc_js( __( 'Salva modifiche', 'badaround-core' ) ) . '";}var s=document.getElementById("post-status-select");if(s){s.style.display="none";}}());</script>';
	}

	public function render_admin_notices() {
		if ( ! current_user_can( 'ba_moderate_events' ) ) {
			return;
		}

		if ( ! empty( $_GET['ba_publication_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Evento pubblicato.', 'badaround-core' ) . '</strong> ' . esc_html__( 'La segnalazione è ora disponibile nel frontend e nelle viste territoriali pertinenti.', 'badaround-core' ) . '</p></div>';
			return;
		}

		if ( ! empty( $_GET['ba_publication_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$code = sanitize_key( wp_unslash( $_GET['ba_publication_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Pubblicazione non completata.', 'badaround-core' ) . '</strong> ' . esc_html( $this->publication_error_message( $code ) ) . '</p></div>';
		}
	}

	private function publication_error_message( $code ) {
		$messages = array(
			'ba_publication_invalid_event'              => __( 'L’evento non è valido.', 'badaround-core' ),
			'ba_publication_forbidden'                  => __( 'L’utente corrente non dispone dei permessi necessari per pubblicare.', 'badaround-core' ),
			'ba_publication_transition_not_allowed'     => __( 'La pubblicazione è consentita solo dopo l’approvazione.', 'badaround-core' ),
			'ba_publication_not_approved'               => __( 'L’evento non risulta ancora approvato.', 'badaround-core' ),
			'ba_publication_title_missing'              => __( 'Non è stato possibile costruire un titolo pubblico.', 'badaround-core' ),
			'ba_publication_content_missing'            => __( 'Non è stato possibile costruire una descrizione pubblica.', 'badaround-core' ),
			'ba_publication_type_missing'               => __( 'Manca la categoria dell’evento.', 'badaround-core' ),
			'ba_publication_subcategory_missing'        => __( 'Manca la sottocategoria dell’evento.', 'badaround-core' ),
			'ba_publication_territory_missing'          => __( 'Manca il territorio canonico associato alla segnalazione.', 'badaround-core' ),
			'ba_publication_date_missing'               => __( 'Manca la data pubblicabile dell’evento.', 'badaround-core' ),
			'ba_publication_location_missing'           => __( 'Manca una localizzazione pubblicabile.', 'badaround-core' ),
			'ba_publication_location_too_precise'       => __( 'La posizione pubblica è troppo precisa e deve essere approssimata.', 'badaround-core' ),
			'ba_publication_private_report_missing'     => __( 'Non è disponibile il report riservato collegato.', 'badaround-core' ),
			'ba_publication_private_data_detected'      => __( 'La proiezione pubblica contiene dati riservati e deve essere corretta.', 'badaround-core' ),
			'ba_publication_exact_coordinates_detected' => __( 'Le coordinate pubbliche coincidono con quelle esatte.', 'badaround-core' ),
			'ba_publication_plate_not_masked'           => __( 'La targa non risulta correttamente mascherata.', 'badaround-core' ),
			'ba_publication_sensitive_meta_detected'    => __( 'Sono presenti dati riservati nella proiezione pubblica.', 'badaround-core' ),
			'ba_publication_media_segregation_invalid'  => __( 'Un allegato originale non risulta correttamente segregato.', 'badaround-core' ),
			'ba_publication_public_media_missing'       => __( 'Una copia media pubblica collegata non è disponibile.', 'badaround-core' ),
			'ba_publication_wp_status_failed'           => __( 'WordPress non ha confermato lo stato pubblicato.', 'badaround-core' ),
		);

		return isset( $messages[ $code ] )
			? $messages[ $code ]
			: sprintf( __( 'Errore di pubblicazione: %s', 'badaround-core' ), $code );
	}

	private function moderation_queue_count() {
		$query = new WP_Query(
			array(
				'post_type'      => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'    => 'pending',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'no_found_rows'  => false,
				'meta_query'     => array(
					array(
						'key'     => '_ba_moderation_status',
						'value'   => array(
							BadAround_Moderation_Service::STATUS_NEW,
							BadAround_Moderation_Service::STATUS_IN_REVIEW,
							BadAround_Moderation_Service::STATUS_NEEDS_INFORMATION,
						),
						'compare' => 'IN',
					),
				),
			)
		);
		return (int) $query->found_posts;
	}

	private function report_for_event( $event_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, author_name, author_surname, author_email, author_phone, exact_address, exact_lat, exact_lng, full_plate, content_original FROM {$wpdb->prefix}ba_reports WHERE event_id = %d AND deleted_at IS NULL ORDER BY id ASC LIMIT 1",
				absint( $event_id )
			)
		);
	}

	private function territory_options() {
		$terms = get_terms( array( 'taxonomy' => BadAround_Event_Post_Type::TERRITORY_TAX, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$by_id = array();
		foreach ( $terms as $term ) {
			$by_id[ $term->term_id ] = $term;
		}
		$options = array();
		foreach ( $terms as $term ) {
			$parts = array( $term->name );
			$parent = (int) $term->parent;
			$guard = 0;
			while ( $parent && isset( $by_id[ $parent ] ) && $guard < 8 ) {
				array_unshift( $parts, $by_id[ $parent ]->name );
				$parent = (int) $by_id[ $parent ]->parent;
				$guard++;
			}
			$options[ $term->term_id ] = implode( ' → ', $parts );
		}
		natcasesort( $options );
		return $options;
	}

	private function term_path( $post_id, $taxonomy ) {
		$terms = wp_get_post_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || ! $terms ) {
			return '—';
		}
		usort( $terms, static function( $a, $b ) { return $a->parent <=> $b->parent; } );
		return implode( ' → ', array_map( static function( $term ) { return esc_html( $term->name ); }, $terms ) );
	}
}
