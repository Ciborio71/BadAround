<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BADAROUND_CHILD_VERSION', '0.3.0' );

/**
 * Load only the assets required by the current screen.
 */
function badaround_child_enqueue_assets() {
	$uri = get_stylesheet_directory_uri();
	$dir = get_stylesheet_directory();

	wp_enqueue_style(
		'badaround-base',
		$uri . '/assets/css/base.css',
		array( 'astra-theme-css' ),
		(string) filemtime( $dir . '/assets/css/base.css' )
	);

	wp_enqueue_style(
		'badaround-components',
		$uri . '/assets/css/components.css',
		array( 'badaround-base' ),
		(string) filemtime( $dir . '/assets/css/components.css' )
	);

	if ( is_front_page() || is_singular( 'ba_evento' ) || is_post_type_archive( 'ba_evento' ) || is_tax( array( 'ba_territorio', 'ba_tipo_evento' ) ) || is_page_template( 'page-segnala-evento.php' ) ) {
		wp_enqueue_style(
			'badaround-pages',
			$uri . '/assets/css/pages.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/pages.css' )
		);
	}

	if ( is_page_template( 'page-segnala-evento.php' ) ) {
		wp_enqueue_style(
			'badaround-wpforms',
			$uri . '/assets/css/wpforms.css',
			array( 'badaround-pages' ),
			(string) filemtime( $dir . '/assets/css/wpforms.css' )
		);
	}

	wp_enqueue_script(
		'badaround-ui',
		$uri . '/assets/js/ui.js',
		array(),
		(string) filemtime( $dir . '/assets/js/ui.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'badaround_child_enqueue_assets', 20 );

/**
 * WPForms id can be provided without editing the page template.
 */
function badaround_report_form_id() {
	return (int) apply_filters( 'badaround_report_form_id', 0 );
}

/**
 * Return only fields explicitly approved for public event rendering.
 * Private report data must never be queried by the child theme.
 */
function badaround_get_public_event_data( $post_id = 0 ) {
	$post_id = $post_id ? absint( $post_id ) : get_the_ID();
	if ( ! $post_id || 'ba_evento' !== get_post_type( $post_id ) ) {
		return array();
	}

	$territories = get_the_terms( $post_id, 'ba_territorio' );
	$types       = get_the_terms( $post_id, 'ba_tipo_evento' );

	return array(
		'event_status' => sanitize_key( get_post_meta( $post_id, '_ba_event_status', true ) ),
		'occurred_at'  => sanitize_text_field( get_post_meta( $post_id, '_ba_occurred_at', true ) ),
		'occurred_date' => sanitize_text_field( get_post_meta( $post_id, '_ba_occurred_date', true ) ),
		'place_name'    => sanitize_text_field( get_post_meta( $post_id, '_ba_public_place_name', true ) ),
		'address'       => sanitize_text_field( get_post_meta( $post_id, '_ba_public_address', true ) ),
		'plate_masked'  => sanitize_text_field( get_post_meta( $post_id, '_ba_vehicle_plate_masked', true ) ),
		'reward_amount' => get_post_meta( $post_id, '_ba_reward_amount', true ),
		'territories'   => is_wp_error( $territories ) ? array() : $territories,
		'types'         => is_wp_error( $types ) ? array() : $types,
	);
}

function badaround_public_event_status_label( $status ) {
	$labels = array(
		'open'     => __( 'Evento attivo', 'badaround-child' ),
		'updated'  => __( 'Aggiornato', 'badaround-child' ),
		'resolved' => __( 'Risolto', 'badaround-child' ),
		'closed'   => __( 'Chiuso', 'badaround-child' ),
		'expired'  => __( 'Scaduto', 'badaround-child' ),
		'archived' => __( 'Archiviato', 'badaround-child' ),
	);

	return $labels[ $status ] ?? __( 'Segnalazione', 'badaround-child' );
}
