<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lightweight asynchronous selector; never renders the full territory tree. */
class BadAround_Territory_Admin {
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ba_get_territory_children', array( $this, 'get_children' ) );
		add_action( 'save_post_' . BadAround_Event_Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
	}

	public function add_meta_box() {
		add_meta_box(
			'ba-event-territory',
			__( 'Territorio', 'badaround-core' ),
			array( $this, 'render' ),
			BadAround_Event_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	public function render( $post ) {
		$current = wp_get_object_terms(
			$post->ID,
			BadAround_Event_Post_Type::TERRITORY_TAX,
			array( 'fields' => 'ids' )
		);
		$selected = ! is_wp_error( $current ) && $current ? (int) end( $current ) : 0;
		$path     = $selected ? array_merge( array_reverse( get_ancestors( $selected, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' ) ), array( $selected ) ) : array();

		wp_nonce_field( 'ba_save_event_territory', 'ba_event_territory_nonce' );
		?>
		<div id="ba-territory-selector" data-path="<?php echo esc_attr( wp_json_encode( array_map( 'absint', $path ) ) ); ?>">
			<p class="description"><?php esc_html_e( 'Caricamento progressivo: Regione, Provincia, Comune, Località.', 'badaround-core' ); ?></p>
			<label class="screen-reader-text" for="ba-territory-region"><?php esc_html_e( 'Regione', 'badaround-core' ); ?></label>
			<select id="ba-territory-region" data-level="region"><option value=""><?php esc_html_e( 'Regione', 'badaround-core' ); ?></option></select>
			<label class="screen-reader-text" for="ba-territory-province"><?php esc_html_e( 'Provincia', 'badaround-core' ); ?></label>
			<select id="ba-territory-province" data-level="province" disabled><option value=""><?php esc_html_e( 'Provincia', 'badaround-core' ); ?></option></select>
			<label class="screen-reader-text" for="ba-territory-municipality"><?php esc_html_e( 'Comune', 'badaround-core' ); ?></label>
			<select id="ba-territory-municipality" data-level="municipality" disabled><option value=""><?php esc_html_e( 'Comune', 'badaround-core' ); ?></option></select>
			<label class="screen-reader-text" for="ba-territory-locality"><?php esc_html_e( 'Località', 'badaround-core' ); ?></label>
			<select id="ba-territory-locality" data-level="locality" disabled><option value=""><?php esc_html_e( 'Località', 'badaround-core' ); ?></option></select>
			<input type="hidden" id="ba-territory-term-id" name="ba_territory_term_id" value="<?php echo esc_attr( $selected ); ?>">
		</div>
		<?php
	}

	public function enqueue_assets( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! $screen || BadAround_Event_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_script(
			'badaround-territory-admin',
			plugins_url( 'assets/js/territory-admin.js', BADAROUND_CORE_FILE ),
			array(),
			BADAROUND_CORE_VERSION,
			true
		);
		wp_localize_script(
			'badaround-territory-admin',
			'BadAroundTerritory',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ba_territory_lookup' ),
			)
		);
	}

	public function get_children() {
		check_ajax_referer( 'ba_territory_lookup', 'nonce' );
		if ( ! current_user_can( 'edit_ba_eventi' ) ) {
			wp_send_json_error( array( 'message' => __( 'Operazione non autorizzata.', 'badaround-core' ) ), 403 );
		}

		$parent = isset( $_GET['parent'] ) ? absint( wp_unslash( $_GET['parent'] ) ) : 0;
		$level  = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : 'region';
		$terms  = get_terms(
			array(
				'taxonomy'   => BadAround_Event_Post_Type::TERRITORY_TAX,
				'hide_empty' => false,
				'parent'     => $parent,
				'number'     => 200,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'meta_key'   => '_ba_geo_level',
				'meta_value' => $level,
			)
		);

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( array( 'message' => $terms->get_error_message() ), 500 );
		}

		wp_send_json_success(
			array_map(
				function ( $term ) {
					return array( 'id' => (int) $term->term_id, 'name' => $term->name );
				},
				$terms
			)
		);
	}

	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['ba_event_territory_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ba_event_territory_nonce'] ) ), 'ba_save_event_territory' ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$term_id = isset( $_POST['ba_territory_term_id'] ) ? absint( wp_unslash( $_POST['ba_territory_term_id'] ) ) : 0;
		wp_set_object_terms( $post_id, $term_id ? array( $term_id ) : array(), BadAround_Event_Post_Type::TERRITORY_TAX, false );
	}
}
