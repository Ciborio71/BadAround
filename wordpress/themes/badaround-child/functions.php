<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BADAROUND_CHILD_VERSION', '0.2.0' );

/**
 * Load only the assets required by the current screen.
 */
function badaround_child_enqueue_assets() {
	$uri = get_stylesheet_directory_uri();
	$dir = get_stylesheet_directory();

	$is_home      = is_front_page();
	$is_event     = is_singular( array( 'ba_evento', 'badaround_event' ) );
	$is_location  = is_tax( array( 'ba_territorio', 'badaround_location' ) );
	$is_reporting = is_page_template( 'page-segnala-evento.php' );
	$is_map       = is_page_template( 'page-mappa.php' );
	$is_sentinel  = is_page_template( 'page-sentinelle.php' );
	$is_states    = is_page_template( array( 'page-stati-sistema.php', 'page-segnalazione-inviata.php' ) );
	$is_account   = is_page_template( 'page-area-personale.php' );
	$is_badaround = $is_home || $is_event || $is_location || $is_reporting || $is_map || $is_sentinel || $is_states || $is_account;

	if ( ! $is_badaround ) {
		return;
	}

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

	wp_enqueue_style(
		'badaround-shell',
		$uri . '/assets/css/shell.css',
		array( 'badaround-components' ),
		(string) filemtime( $dir . '/assets/css/shell.css' )
	);

	wp_enqueue_script(
		'badaround-navigation',
		$uri . '/assets/js/navigation.js',
		array(),
		(string) filemtime( $dir . '/assets/js/navigation.js' ),
		true
	);

	if ( $is_home ) {
		wp_enqueue_style(
			'badaround-home',
			$uri . '/assets/css/home.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/home.css' )
		);
	}

	if ( $is_event || $is_location ) {
		wp_enqueue_style(
			'badaround-pages',
			$uri . '/assets/css/pages.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/pages.css' )
		);
	}

	if ( $is_map ) {
		wp_enqueue_style(
			'badaround-map',
			$uri . '/assets/css/map.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/map.css' )
		);
		wp_enqueue_script(
			'badaround-map',
			$uri . '/assets/js/map.js',
			array(),
			(string) filemtime( $dir . '/assets/js/map.js' ),
			true
		);
	}

	if ( $is_sentinel ) {
		wp_enqueue_style(
			'badaround-sentinels',
			$uri . '/assets/css/sentinels.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/sentinels.css' )
		);
		wp_enqueue_script(
			'badaround-sentinels',
			$uri . '/assets/js/sentinels.js',
			array(),
			(string) filemtime( $dir . '/assets/js/sentinels.js' ),
			true
		);
	}

	if ( $is_states ) {
		wp_enqueue_style(
			'badaround-states',
			$uri . '/assets/css/states.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/states.css' )
		);
	}

	if ( $is_account ) {
		wp_enqueue_style(
			'badaround-account',
			$uri . '/assets/css/account.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/account.css' )
		);
		wp_enqueue_script(
			'badaround-account',
			$uri . '/assets/js/account.js',
			array(),
			(string) filemtime( $dir . '/assets/js/account.js' ),
			true
		);
	}

	if ( $is_reporting ) {
		wp_enqueue_style(
			'badaround-report',
			$uri . '/assets/css/report.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/report.css' )
		);
		wp_enqueue_style(
			'badaround-wpforms',
			$uri . '/assets/css/wpforms.css',
			array( 'badaround-report' ),
			(string) filemtime( $dir . '/assets/css/wpforms.css' )
		);
		wp_enqueue_script(
			'badaround-report-ui',
			$uri . '/assets/js/report.js',
			array(),
			(string) filemtime( $dir . '/assets/js/report.js' ),
			true
		);
	}

	if ( $is_home || $is_location ) {
		wp_enqueue_script(
			'badaround-ui',
			$uri . '/assets/js/ui.js',
			array(),
			(string) filemtime( $dir . '/assets/js/ui.js' ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'badaround_child_enqueue_assets', 20 );

/**
 * WPForms id can be provided without editing the page template.
 */
function badaround_report_form_id() {
	return (int) apply_filters( 'badaround_report_form_id', 6 );
}


/**
 * BadAround reporting map defaults.
 * Keep the first geolocation view in Italy and use a close street-level zoom.
 */
function badaround_wpforms_geolocation_default_location( $location ) {
	if ( ! is_page_template( 'page-segnala-evento.php' ) ) {
		return $location;
	}

	return array(
		'lat' => 41.9028,
		'lng' => 12.4964,
	);
}
add_filter( 'wpforms_geolocation_map_default_location', 'badaround_wpforms_geolocation_default_location', 20 );

function badaround_wpforms_geolocation_map_zoom( $zoom, $context ) {
	if ( ! is_page_template( 'page-segnala-evento.php' ) ) {
		return $zoom;
	}

	if ( 'field' === $context ) {
		return 18;
	}

	return $zoom;
}
add_filter( 'wpforms_geolocation_map_zoom', 'badaround_wpforms_geolocation_map_zoom', 20, 2 );
