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
	$is_badaround = $is_home || $is_event || $is_location || $is_reporting || $is_map;

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
