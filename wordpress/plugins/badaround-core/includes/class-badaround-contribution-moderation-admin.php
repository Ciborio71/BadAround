<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Contribution_Moderation_Admin {
	private $repository;
	private $service;
	private $media_repository;

	public function __construct() {
		$this->repository = new BadAround_Contribution_Repository();
		$this->service = new BadAround_Contribution_Moderation_Service();
		$this->media_repository = new BadAround_Contribution_Media_Repository();
	}

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_ba_moderate_contribution', array( $this, 'handle_action' ) );
		add_action( 'admin_post_ba_contribution_media_review', array( $this, 'handle_media_action' ) );
		add_action( 'admin_post_ba_contribution_private_media', array( $this, 'serve_private_media' ) );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . BadAround_Event_Post_Type::POST_TYPE,
			__( 'Contributi community', 'badaround-core' ),
			__( 'Contributi', 'badaround-core' ),
			'ba_moderate_contributions',
			'ba-contributions',
			array( $this, 'render_page' )
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'ba_moderate_contributions' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ) );
		}

		$selected_id = isset( $_GET['contribution_id'] ) ? absint( $_GET['contribution_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap"><h1>' . esc_html__( 'Contributi della Community', 'badaround-core' ) . '</h1>';

		if ( $selected_id ) {
			$this->render_detail( $selected_id );
		} else {
			$this->render_queue();
		}
		echo '</div>';
	}

	private function render_queue() {
		$rows = $this->repository->list_for_moderation( 100 );
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nessun contributo da mostrare.', 'badaround-core' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Evento</th><th>Tipo</th><th>Visibilità richiesta</th><th>Stato</th><th>Ricevuto</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$url = add_query_arg(
				array(
					'post_type' => BadAround_Event_Post_Type::POST_TYPE,
					'page' => 'ba-contributions',
					'contribution_id' => absint( $row['id'] ),
				),
				admin_url( 'edit.php' )
			);
			echo '<tr>';
			echo '<td>#' . absint( $row['id'] ) . '</td>';
			echo '<td><a href="' . esc_url( get_edit_post_link( absint( $row['event_id'] ) ) ) . '">' . esc_html( get_the_title( absint( $row['event_id'] ) ) ) . '</a></td>';
			echo '<td>' . esc_html( $row['contribution_type'] ) . '</td>';
			echo '<td>' . esc_html( $row['visibility_requested'] ) . '</td>';
			echo '<td><strong>' . esc_html( $row['status'] ) . '</strong></td>';
			echo '<td>' . esc_html( $row['created_at'] ) . '</td>';
			echo '<td><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Apri', 'badaround-core' ) . '</a></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	private function render_detail( $id ) {
		$row = $this->repository->find_by_id( $id );
		if ( ! $row ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Contributo non trovato.', 'badaround-core' ) . '</p></div>';
			return;
		}

		$back = add_query_arg(
			array( 'post_type' => BadAround_Event_Post_Type::POST_TYPE, 'page' => 'ba-contributions' ),
			admin_url( 'edit.php' )
		);
		echo '<p><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Torna alla coda', 'badaround-core' ) . '</a></p>';
		echo '<h2>#' . absint( $row['id'] ) . ' — ' . esc_html( get_the_title( absint( $row['event_id'] ) ) ) . '</h2>';
		echo '<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;align-items:start">';

		echo '<div>';
		echo '<h3>' . esc_html__( 'Contenuto originale — RISERVATO', 'badaround-core' ) . '</h3>';
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Non copiare automaticamente dati riservati nella proiezione pubblica.', 'badaround-core' ) . '</strong></p></div>';
		echo '<table class="widefat striped"><tbody>';
		$private = array(
			__( 'Tipo', 'badaround-core' ) => $row['contribution_type'],
			__( 'Testo originale', 'badaround-core' ) => $row['content_original'],
			__( 'Email', 'badaround-core' ) => current_user_can( 'ba_view_private_contributions' ) ? $row['email'] : '—',
			__( 'Quando osservato', 'badaround-core' ) => $row['observed_at'] ?: '—',
			__( 'Posizione precisa/testuale', 'badaround-core' ) => current_user_can( 'ba_view_private_contributions' ) ? ( $row['exact_location_text'] ?: '—' ) : '—',
			__( 'Coordinate precise', 'badaround-core' ) => current_user_can( 'ba_view_private_contributions' ) && null !== $row['exact_lat'] && null !== $row['exact_lng'] ? $row['exact_lat'] . ', ' . $row['exact_lng'] : '—',
			__( 'Direzione', 'badaround-core' ) => $row['direction'] ?: '—',
			__( 'Identità pubblica', 'badaround-core' ) => $row['public_identity_mode'],
			__( 'Alias richiesto', 'badaround-core' ) => $row['public_display_name'] ?: '—',
			__( 'Visibilità richiesta', 'badaround-core' ) => $row['visibility_requested'],
		);
		foreach ( $private as $label => $value ) {
			echo '<tr><th style="width:210px">' . esc_html( $label ) . '</th><td>' . nl2br( esc_html( (string) $value ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->render_media( $row );
		echo '</div>';

		echo '<aside>';
		echo '<h3>' . esc_html__( 'Moderazione', 'badaround-core' ) . '</h3>';
		echo '<p><strong>' . esc_html__( 'Stato:', 'badaround-core' ) . '</strong> ' . esc_html( $row['status'] ) . '</p>';

		if ( BadAround_Contribution_Repository::STATUS_TO_REVIEW === $row['status'] ) {
			$this->render_action_form( $row, BadAround_Contribution_Repository::STATUS_IN_REVIEW, __( 'Prendi in carico', 'badaround-core' ), false );
		} elseif ( BadAround_Contribution_Repository::STATUS_IN_REVIEW === $row['status'] ) {
			if ( 'reserved' !== $row['visibility_requested'] ) {
				$this->render_publish_form( $row );
			}
			$this->render_action_form( $row, BadAround_Contribution_Repository::STATUS_RESERVED, __( 'Classifica come riservato', 'badaround-core' ), false );
			$this->render_action_form( $row, BadAround_Contribution_Repository::STATUS_REJECTED, __( 'Rifiuta', 'badaround-core' ), true );
		} else {
			echo '<p>' . esc_html__( 'Moderazione conclusa.', 'badaround-core' ) . '</p>';
			if ( ! empty( $row['public_projection_id'] ) ) {
				echo '<p>' . esc_html__( 'Proiezione pubblica:', 'badaround-core' ) . ' #' . absint( $row['public_projection_id'] ) . '</p>';
			}
		}
		echo '</aside></div>';
	}

	private function render_media( array $row ) {
		if ( ! current_user_can( 'ba_view_private_contributions' ) ) {
			return;
		}
		$media = $this->media_repository->list_for_contribution( $row['id'] );
		echo '<h3 style="margin-top:24px">' . esc_html__( 'Media del contributo — ORIGINALI PRIVATI', 'badaround-core' ) . '</h3>';
		if ( ! $media ) {
			echo '<p>' . esc_html__( 'Nessun media allegato.', 'badaround-core' ) . '</p>';
			return;
		}
		foreach ( $media as $item ) {
			$view_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=ba_contribution_private_media&media_id=' . absint( $item['id'] ) ),
				'ba_contribution_private_media_' . absint( $item['id'] )
			);
			echo '<div style="padding:12px;margin:10px 0;border:1px solid #ccd0d4;background:#fff">';
			echo '<p><strong>' . esc_html( $item['original_filename'] ) . '</strong><br>';
			echo esc_html( $item['mime_type'] . ' · ' . size_format( (int) $item['file_size'] ) . ' · ' . $item['review_status'] ) . '</p>';
			echo '<p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $view_url ) . '">' . esc_html__( 'Visualizza originale privato', 'badaround-core' ) . '</a></p>';

			if ( BadAround_Contribution_Repository::STATUS_IN_REVIEW === $row['status'] && 'received' === $item['review_status'] ) {
				foreach ( array( 'approve' => __( 'Approva derivato pubblico', 'badaround-core' ), 'reject' => __( 'Rifiuta media', 'badaround-core' ) ) as $decision => $label ) {
					$url = wp_nonce_url(
						admin_url(
							'admin-post.php?action=ba_contribution_media_review&contribution_id=' . absint( $row['id'] ) .
							'&media_id=' . absint( $item['id'] ) . '&decision=' . rawurlencode( $decision )
						),
						'ba_contribution_media_review_' . absint( $item['id'] )
					);
					echo '<a class="button button-secondary" style="margin-right:6px" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
				}
			}
			if ( ! empty( $item['public_attachment_id'] ) ) {
				echo '<p><small>' . esc_html__( 'Derivato pubblico:', 'badaround-core' ) . ' #' . absint( $item['public_attachment_id'] ) . '</small></p>';
			}
			echo '</div>';
		}
	}

	private function render_publish_form( array $row ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0;padding:12px;border:1px solid #ccd0d4;background:#fff">';
		wp_nonce_field( 'ba_moderate_contribution_' . absint( $row['id'] ) );
		echo '<input type="hidden" name="action" value="ba_moderate_contribution">';
		echo '<input type="hidden" name="contribution_id" value="' . absint( $row['id'] ) . '">';
		echo '<input type="hidden" name="target_status" value="' . esc_attr( BadAround_Contribution_Repository::STATUS_PUBLISHED ) . '">';
		echo '<p><label><strong>' . esc_html__( 'Testo pubblico moderato', 'badaround-core' ) . '</strong><br>';
		echo '<textarea class="widefat" rows="6" name="public_text" required></textarea></label></p>';
		echo '<p class="description">' . esc_html__( 'Scrivi esplicitamente la versione pubblicabile. Il contenuto originale non viene copiato automaticamente.', 'badaround-core' ) . '</p>';
		submit_button( __( 'Pubblica contributo', 'badaround-core' ), 'primary', 'submit', false );
		echo '</form>';
	}

	private function render_action_form( array $row, $target, $label, $reason_required ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
		wp_nonce_field( 'ba_moderate_contribution_' . absint( $row['id'] ) );
		echo '<input type="hidden" name="action" value="ba_moderate_contribution">';
		echo '<input type="hidden" name="contribution_id" value="' . absint( $row['id'] ) . '">';
		echo '<input type="hidden" name="target_status" value="' . esc_attr( $target ) . '">';
		if ( $reason_required ) {
			echo '<p><textarea class="widefat" name="reason" rows="3" required placeholder="' . esc_attr__( 'Motivazione interna obbligatoria', 'badaround-core' ) . '"></textarea></p>';
		}
		submit_button( $label, 'secondary', 'submit', false );
		echo '</form>';
	}

	public function handle_media_action() {
		if ( ! current_user_can( 'ba_moderate_contributions' ) || ! current_user_can( 'ba_view_private_contributions' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ), '', array( 'response' => 403 ) );
		}
		$contribution_id = isset( $_GET['contribution_id'] ) ? absint( $_GET['contribution_id'] ) : 0;
		$media_id = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
		$decision = isset( $_GET['decision'] ) ? sanitize_key( wp_unslash( $_GET['decision'] ) ) : '';
		check_admin_referer( 'ba_contribution_media_review_' . $media_id );

		$row = $this->repository->find_by_id( $contribution_id );
		if ( ! $row || BadAround_Contribution_Repository::STATUS_IN_REVIEW !== $row['status'] ) {
			wp_die( esc_html__( 'Il contributo non è in revisione.', 'badaround-core' ), '', array( 'response' => 409 ) );
		}

		if ( 'approve' === $decision ) {
			$result = $this->media_repository->approve( $media_id, $contribution_id );
		} elseif ( 'reject' === $decision ) {
			$result = $this->media_repository->reject( $media_id, $contribution_id );
		} else {
			$result = new WP_Error( 'ba_contribution_media_invalid_decision', __( 'Decisione non valida.', 'badaround-core' ) );
		}

		$url = add_query_arg(
			array(
				'post_type' => BadAround_Event_Post_Type::POST_TYPE,
				'page' => 'ba-contributions',
				'contribution_id' => $contribution_id,
				'ba_media_result' => is_wp_error( $result ) ? $result->get_error_code() : 'ok',
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function serve_private_media() {
		$media_id = isset( $_GET['media_id'] ) ? absint( $_GET['media_id'] ) : 0;
		check_admin_referer( 'ba_contribution_private_media_' . $media_id );
		if ( ! current_user_can( 'ba_view_private_contributions' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ), '', array( 'response' => 403 ) );
		}
		$file = $this->media_repository->private_file( $media_id );
		if ( is_wp_error( $file ) ) {
			wp_die( esc_html( $file->get_error_message() ), '', array( 'response' => 404 ) );
		}
		$row = $file['row'];
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: default-src \'none\'; img-src \'self\' data:' );
		header( 'Content-Type: ' . sanitize_mime_type( $row['mime_type'] ) );
		header( 'Content-Length: ' . (string) filesize( $file['path'] ) );
		header( 'Content-Disposition: inline; filename="' . rawurlencode( $row['original_filename'] ) . '"' );
		readfile( $file['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public function handle_action() {
		if ( ! current_user_can( 'ba_moderate_contributions' ) ) {
			wp_die( esc_html__( 'Accesso non autorizzato.', 'badaround-core' ) );
		}
		$id = isset( $_POST['contribution_id'] ) ? absint( $_POST['contribution_id'] ) : 0;
		check_admin_referer( 'ba_moderate_contribution_' . $id );

		$result = $this->service->transition(
			$id,
			isset( $_POST['target_status'] ) ? sanitize_key( wp_unslash( $_POST['target_status'] ) ) : '',
			array(
				'public_text' => isset( $_POST['public_text'] ) ? wp_unslash( $_POST['public_text'] ) : '',
				'reason' => isset( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : '',
			)
		);

		$url = add_query_arg(
			array(
				'post_type' => BadAround_Event_Post_Type::POST_TYPE,
				'page' => 'ba-contributions',
				'contribution_id' => $id,
				'ba_result' => is_wp_error( $result ) ? $result->get_error_code() : 'ok',
			),
			admin_url( 'edit.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}
}
