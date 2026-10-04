<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers the public Event entity and its controlled classifications. */
class BadAround_Event_Post_Type {
	const POST_TYPE      = 'ba_evento';
	const EVENT_TYPE_TAX = 'ba_tipo_evento';
	const TERRITORY_TAX  = 'ba_territorio';

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_content_model' ) );
		add_filter( 'display_post_states', array( $this, 'label_pending_events' ), 10, 2 );
		add_action( 'template_redirect', array( $this, 'redirect_public_archive' ) );
	}

	public function register_content_model() {
		$this->register_taxonomies();
		$this->register_post_type();
		$this->register_meta();
		$this->register_territory_meta();
	}

	private function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels' => array(
					'name'               => __( 'Eventi', 'badaround-core' ),
					'singular_name'      => __( 'Evento', 'badaround-core' ),
					'menu_name'          => __( 'Eventi', 'badaround-core' ),
					'add_new'            => __( 'Aggiungi evento', 'badaround-core' ),
					'add_new_item'       => __( 'Aggiungi evento', 'badaround-core' ),
					'edit_item'          => __( 'Modifica evento', 'badaround-core' ),
					'new_item'           => __( 'Nuovo evento', 'badaround-core' ),
					'view_item'          => __( 'Visualizza evento', 'badaround-core' ),
					'search_items'       => __( 'Cerca eventi', 'badaround-core' ),
					'not_found'          => __( 'Nessun evento trovato.', 'badaround-core' ),
					'not_found_in_trash' => __( 'Nessun evento nel cestino.', 'badaround-core' ),
					'all_items'          => __( 'Tutti gli eventi', 'badaround-core' ),
				),
				'public'           => true,
				'show_in_rest'     => true,
				'has_archive'      => true,
				'rewrite'          => array( 'slug' => 'eventi' ),
				'menu_icon'        => 'dashicons-location-alt',
				'supports'         => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
				'taxonomies'       => array( self::EVENT_TYPE_TAX, self::TERRITORY_TAX ),
				'capability_type'   => array( 'ba_evento', 'ba_eventi' ),
				'map_meta_cap'     => true,
				'delete_with_user' => false,
			)
		);
	}

	private function register_taxonomies() {
		register_taxonomy(
			self::EVENT_TYPE_TAX,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Tipologie evento', 'badaround-core' ),
					'singular_name' => __( 'Tipologia evento', 'badaround-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'tipologia-evento' ),
			)
		);

		register_taxonomy(
			self::TERRITORY_TAX,
			array( self::POST_TYPE ),
			array(
				'labels' => array(
					'name'          => __( 'Territori', 'badaround-core' ),
					'singular_name' => __( 'Territorio', 'badaround-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => false,
				'show_admin_column' => true,
				'meta_box_cb'       => false,
				'rewrite'           => array( 'slug' => 'territorio' ),
			)
		);
	}

	private function register_meta() {
		$definitions = array(
			'_ba_primary_report_id'         => 'integer',
			'_ba_source_type'               => 'string',
			'_ba_source_form_id'            => 'integer',
			'_ba_source_entry_id'           => 'integer',
			'_ba_imported_at'               => 'string',
			'_ba_moderation_status'         => 'string',
			'_ba_event_status'              => 'string',
			'_ba_occurred_at'               => 'string',
			'_ba_occurred_date'             => 'string',
			'_ba_occurred_time'             => 'string',
			'_ba_time_precision'            => 'string',
			'_ba_public_place_name'         => 'string',
			'_ba_public_address'            => 'string',
			'_ba_public_lat'                => 'number',
			'_ba_public_lng'                => 'number',
			'_ba_public_location_precision' => 'string',
			'_ba_public_radius_m'           => 'integer',
			'_ba_vehicle_involved'          => 'boolean',
			'_ba_vehicle_type'              => 'string',
			'_ba_vehicle_make'              => 'string',
			'_ba_vehicle_model'             => 'string',
			'_ba_vehicle_color'             => 'string',
			'_ba_vehicle_plate_masked'      => 'string',
			'_ba_reward_available'          => 'boolean',
			'_ba_reward_amount'             => 'number',
			'_ba_reward_currency'           => 'string',
			'_ba_expires_at'                => 'string',
			'_ba_closed_at'                 => 'string',
			'_ba_closure_reason'            => 'string',
		);

		foreach ( $definitions as $key => $type ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => array( $this, 'sanitize_meta_value' ),
					'auth_callback'     => array( $this, 'can_edit_event_meta' ),
				)
			);
		}
	}

	private function register_territory_meta() {
		$definitions = array(
			'_ba_geo_level'         => 'string',
			'_ba_istat_code'        => 'string',
			'_ba_cadastral_code'    => 'string',
			'_ba_province_code'     => 'string',
			'_ba_center_lat'        => 'number',
			'_ba_center_lng'        => 'number',
			'_ba_geo_source'        => 'string',
			'_ba_external_place_id' => 'string',
			'_ba_geo_verified'      => 'boolean',
		);

		foreach ( $definitions as $key => $type ) {
			register_term_meta(
				self::TERRITORY_TAX,
				$key,
				array(
					'type'         => $type,
					'single'       => true,
					'show_in_rest' => false,
				)
			);
		}
	}

	public function sanitize_meta_value( $value, $meta_key ) {
		if ( in_array( $meta_key, array( '_ba_primary_report_id', '_ba_source_form_id', '_ba_source_entry_id', '_ba_public_radius_m' ), true ) ) {
			return absint( $value );
		}

		if ( in_array( $meta_key, array( '_ba_public_lat', '_ba_public_lng', '_ba_reward_amount' ), true ) ) {
			return is_numeric( $value ) ? (float) $value : null;
		}

		if ( in_array( $meta_key, array( '_ba_vehicle_involved', '_ba_reward_available' ), true ) ) {
			return rest_sanitize_boolean( $value );
		}

		return sanitize_text_field( $value );
	}

	public function can_edit_event_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	public function redirect_public_archive() {
		if ( ! is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}

		wp_safe_redirect( home_url( '/segnalazioni/' ), 301, 'BadAround' );
		exit;
	}

	public function label_pending_events( $post_states, $post ) {
		if ( self::POST_TYPE === $post->post_type && 'pending' === $post->post_status ) {
			$post_states['ba_pending'] = __( 'Da moderare', 'badaround-core' );
		}

		return $post_states;
	}
}
