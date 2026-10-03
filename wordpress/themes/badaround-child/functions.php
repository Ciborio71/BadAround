<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BADAROUND_CHILD_VERSION', '0.5.0' );

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
	$is_search    = is_page_template( 'page-cerca.php' );
	$is_sentinel  = is_page_template( 'page-sentinelle.php' );
	$is_states    = is_page_template( array( 'page-stati-sistema.php', 'page-segnalazione-inviata.php' ) );
	$is_account   = is_page_template( 'page-area-personale.php' );
	$is_badaround = $is_home || $is_event || $is_location || $is_reporting || $is_map || $is_search || $is_sentinel || $is_states || $is_account;

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

	if ( $is_search ) {
		wp_enqueue_style(
			'badaround-search',
			$uri . '/assets/css/search.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/search.css' )
		);
	}

	if ( $is_map ) {
		wp_enqueue_style(
			'badaround-map',
			$uri . '/assets/css/map.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/map.css' )
		);

		$wpforms_settings = get_option( 'wpforms_settings', array() );
		$google_maps_key  = is_array( $wpforms_settings ) && ! empty( $wpforms_settings['geolocation-google-places-api-key'] )
			? trim( (string) $wpforms_settings['geolocation-google-places-api-key'] )
			: '';

		$map_dependencies = array();
		if ( $google_maps_key ) {
			wp_enqueue_script(
				'badaround-google-maps',
				'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $google_maps_key ),
				array(),
				null,
				true
			);
			wp_script_add_data( 'badaround-google-maps', 'strategy', 'defer' );
			$map_dependencies[] = 'badaround-google-maps';
		}

		wp_enqueue_script(
			'badaround-map',
			$uri . '/assets/js/map.js',
			$map_dependencies,
			(string) filemtime( $dir . '/assets/js/map.js' ),
			true
		);
		wp_script_add_data( 'badaround-map', 'strategy', 'defer' );

		wp_add_inline_script(
			'badaround-map',
			'window.BadAroundMap=' . wp_json_encode(
				array(
					'endpoint' => esc_url_raw( rest_url( 'badaround/v1/discovery' ) ),
					'limit'    => 100,
					'hasMaps'  => (bool) $google_maps_key,
				)
			) . ';',
			'before'
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


/**
 * B4 — minimal SEO for the canonical public event detail.
 * Only published ba_evento items receive public-event metadata.
 */
function badaround_event_document_title( $parts ) {
	if ( is_singular( 'ba_evento' ) && 'publish' === get_post_status( get_queried_object_id() ) ) {
		$parts['site'] = 'BadAround';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'badaround_event_document_title', 20 );

function badaround_event_robots( $robots ) {
	if ( is_singular( 'ba_evento' ) && 'publish' !== get_post_status( get_queried_object_id() ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'badaround_event_robots', 20 );

function badaround_event_meta_description() {
	if ( ! is_singular( 'ba_evento' ) || 'publish' !== get_post_status( get_queried_object_id() ) ) {
		return;
	}
	if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	$post_id     = get_queried_object_id();
	$description = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
	$description = trim( preg_replace( '/\s+/', ' ', $description ) );
	if ( ! $description ) {
		return;
	}
	$description = wp_html_excerpt( $description, 155, '…' );
	echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
}
add_action( 'wp_head', 'badaround_event_meta_description', 2 );


/**
 * B5 — minimal SEO for canonical territory archives.
 */
function badaround_territory_document_title( $parts ) {
	if ( is_tax( 'ba_territorio' ) ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->name ) ) {
			$parts['title'] = $term->name . ' — Eventi';
			$parts['site']  = 'BadAround';
		}
	}
	return $parts;
}
add_filter( 'document_title_parts', 'badaround_territory_document_title', 20 );

function badaround_territory_meta_description() {
	if ( ! is_tax( 'ba_territorio' ) || defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) {
		return;
	}
	$term = get_queried_object();
	if ( ! $term || empty( $term->name ) ) {
		return;
	}
	$description = ! empty( $term->description )
		? wp_strip_all_tags( $term->description )
		: sprintf( 'Eventi pubblicati e moderati relativi a %s su BadAround.', $term->name );
	echo '<meta name="description" content="' . esc_attr( wp_html_excerpt( trim( $description ), 155, '…' ) ) . '">' . "\n";
}
add_action( 'wp_head', 'badaround_territory_meta_description', 2 );

function badaround_territory_robots( $robots ) {
	if ( is_tax( 'ba_territorio' ) ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->term_id ) ) {
			$published = new WP_Query(
				array(
					'post_type'              => 'ba_evento',
					'post_status'            => 'publish',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'meta_query'             => array(
						array(
							'key'     => '_ba_moderation_status',
							'value'   => 'published',
							'compare' => '=',
						),
					),
					'tax_query'              => array(
						array(
							'taxonomy'         => 'ba_territorio',
							'field'            => 'term_id',
							'terms'            => array( (int) $term->term_id ),
							'include_children' => true,
						),
					),
				)
			);

			if ( ! $published->have_posts() ) {
				$robots['noindex'] = true;
			}
		}
	}
	return $robots;
}
add_filter( 'wp_robots', 'badaround_territory_robots', 20 );


/**
 * B5 — canonical URL for territory archives.
 */
function badaround_territory_canonical() {
	if ( ! is_tax( 'ba_territorio' ) ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term || empty( $term->term_id ) ) {
		return;
	}

	$url = get_term_link( $term );
	if ( is_wp_error( $url ) ) {
		return;
	}

	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged > 1 ) {
		$url = trailingslashit( $url ) . 'page/' . $paged . '/';
	}

	echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
}
add_action( 'wp_head', 'badaround_territory_canonical', 3 );


/**
 * C2 — search result pages are utility pages, not SEO landing pages.
 */
function badaround_search_robots( $robots ) {
	if ( is_page_template( 'page-cerca.php' ) ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	}
	return $robots;
}
add_filter( 'wp_robots', 'badaround_search_robots', 20 );
