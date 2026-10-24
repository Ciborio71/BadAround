<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BadAround admin: prefer the classic WordPress editor on every post type.
 * This is UI-only; moderation/publication permissions and hooks stay intact.
 */
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_block_editor_for_post', '__return_false', 100 );

define( 'BADAROUND_CHILD_VERSION', '0.6.0' );


/**
 * MAINTENANCE-01 — public maintenance gate.
 *
 * Anonymous visitors receive a branded HTTP 503 page while authenticated users,
 * wp-admin and WPVibe management access remain available.
 */
function badaround_child_maintenance_enabled() {
	return true;
}

function badaround_child_is_maintenance_exempt() {
	if ( ! badaround_child_maintenance_enabled() ) {
		return true;
	}

	if ( is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return true;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return true;
	}

	return false;
}

function badaround_child_render_maintenance() {
	if ( badaround_child_is_maintenance_exempt() ) {
		return;
	}

	status_header( 503 );
	nocache_headers();
	header( 'Retry-After: 3600' );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );

	$title = 'BadAround — Stiamo lavorando';
	?>
	<!doctype html>
	<html lang="it">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow,noarchive">
		<title><?php echo esc_html( $title ); ?></title>
		<style>
			:root{color-scheme:light;--ba-navy:#071A38;--ba-teal:#0798A8;--ba-teal-dark:#066F79;--muted:#5E7288;--line:#CFE1E7;--surface:#fff;--bg:#EAF7FA}
			*{box-sizing:border-box}
			html,body{margin:0;min-height:100%;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:var(--ba-navy);background:linear-gradient(135deg,#F8FCFD 0%,var(--bg) 58%,#D6F1F5 100%)}
			body{min-height:100vh;display:grid;place-items:center;padding:24px}
			main{width:min(680px,100%);background:var(--surface);border:1px solid var(--line);border-radius:24px;padding:clamp(32px,6vw,64px);text-align:center;box-shadow:0 18px 60px rgba(7,26,56,.12);position:relative;overflow:hidden}
			.brand{font-size:clamp(34px,7vw,54px);line-height:1;font-weight:800;letter-spacing:-.045em;margin:0 0 28px;color:var(--ba-navy)} .brand-accent{color:var(--ba-teal)}
			h1{font-size:clamp(26px,5vw,38px);line-height:1.15;letter-spacing:-.025em;margin:0 0 16px;color:var(--ba-navy)}
			p{font-size:clamp(17px,3vw,20px);line-height:1.6;color:var(--muted);margin:0 auto;max-width:520px}
			.status{display:inline-flex;align-items:center;gap:9px;margin-top:30px;padding:9px 14px;border:1px solid #A8DDE3;border-radius:999px;font-size:14px;font-weight:700;color:var(--ba-navy);background:#EEF9FA}
			.dot{width:9px;height:9px;border-radius:50%;background:var(--ba-teal);box-shadow:0 0 0 4px rgba(7,152,168,.14)}
		</style>
	</head>
	<body>
		<main>
			<div class="brand"><span>Bad</span><span class="brand-accent">Around</span></div>
			<h1>Stiamo lavorando per arrivare presto nel tuo quartiere.</h1>
			<p>BadAround è in fase di preparazione. Stiamo completando gli ultimi lavori prima dell'apertura al pubblico.</p>
			<div class="status"><span class="dot" aria-hidden="true"></span> Sito in manutenzione</div>
		</main>
	</body>
	</html>
	<?php
	exit;
}
add_action( 'template_redirect', 'badaround_child_render_maintenance', -100 );

/**
 * Hide public REST data during maintenance while preserving authenticated access
 * and the WPVibe management namespace used for administration.
 */
function badaround_child_maintenance_rest_gate( $result, $server, $request ) {
	if ( ! badaround_child_maintenance_enabled() || is_user_logged_in() ) {
		return $result;
	}

	$route = $request->get_route();

	$allowed_prefixes = array(
		'/wpvibe/',
		'/badaround/v1/discovery',
		'/badaround/v1/sentinels',
	);

	foreach ( $allowed_prefixes as $prefix ) {
		if ( 0 === strpos( $route, $prefix ) ) {
			return $result;
		}
	}

	return new WP_Error(
		'badaround_maintenance',
		'BadAround è temporaneamente in manutenzione.',
		array( 'status' => 503 )
	);
}
add_filter( 'rest_pre_dispatch', 'badaround_child_maintenance_rest_gate', 5, 3 );

require_once __DIR__ . '/inc/native-report.php';

/**
 * Public media standard.
 * Originals remain governed by the private/moderated media pipeline; these sizes are presentation derivatives only.
 */
function badaround_child_public_image_sizes() {
	add_image_size( 'ba-thumb', 360, 240, true );
	add_image_size( 'ba-map-card', 640, 426, true );
	add_image_size( 'ba-detail', 1440, 1080, false );
}
add_action( 'after_setup_theme', 'badaround_child_public_image_sizes' );

function badaround_child_placeholder_url() {
	return content_url( '/uploads/2026/10/BadAround-—-placeholder-segnalazione.png' );
}

function badaround_child_event_image_url( $post_id, $context = 'thumb' ) {
	$sizes = array( 'thumb' => 'ba-thumb', 'map' => 'ba-map-card', 'detail' => 'ba-detail' );
	$size = $sizes[ $context ] ?? 'ba-thumb';
	if ( has_post_thumbnail( $post_id ) ) {
		$url = get_the_post_thumbnail_url( $post_id, $size );
		if ( $url ) return $url;
	}
	return badaround_child_placeholder_url();
}

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
	$is_reports   = is_page_template( 'page-segnalazioni.php' );
	$is_sentinel  = is_page_template( 'page-sentinelle.php' );
	$is_states    = is_page_template( array( 'page-stati-sistema.php', 'page-segnalazione-inviata.php' ) );
	$is_account   = is_page_template( 'page-area-personale.php' );
	$is_not_found    = is_404();
	$is_how_it_works = is_page( 'come-funziona' );
	$is_legal         = is_page( array( 'termini-e-condizioni', 'privacy-policy', 'cookie-policy', 'regole-di-pubblicazione' ) );
	$is_badaround     = $is_home || $is_event || $is_location || $is_reporting || $is_map || $is_search || $is_reports || $is_sentinel || $is_states || $is_account || $is_not_found || $is_how_it_works || $is_legal;

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
	wp_script_add_data( 'badaround-navigation', 'strategy', 'defer' );

	if ( $is_how_it_works ) {
		wp_enqueue_style(
			'badaround-how-it-works',
			$uri . '/assets/css/how-it-works.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/how-it-works.css' )
		);
	}

	if ( $is_legal ) {
		wp_enqueue_style(
			'badaround-legal',
			$uri . '/assets/css/legal.css',
			array( 'badaround-shell' ),
			(string) filemtime( $dir . '/assets/css/legal.css' )
		);
	}

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

	if ( $is_search || $is_reports ) {
		wp_enqueue_style(
			'badaround-search',
			$uri . '/assets/css/search.css',
			array( 'badaround-components' ),
			(string) filemtime( $dir . '/assets/css/search.css' )
		);
	}

	if ( $is_map || $is_home || $is_event || $is_reporting ) {
		wp_enqueue_style( 'badaround-map-navigation', $uri . '/assets/css/map-navigation.css', array( 'badaround-components' ), (string) filemtime( $dir . '/assets/css/map-navigation.css' ) );
		wp_enqueue_script( 'badaround-map-navigation', $uri . '/assets/js/map-navigation.js', array(), (string) filemtime( $dir . '/assets/js/map-navigation.js' ), true );
		wp_script_add_data( 'badaround-map-navigation', 'strategy', 'defer' );
	}

	if ( $is_map || $is_home || $is_event ) {
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

		wp_enqueue_script(
			'badaround-map',
			$uri . '/assets/js/map.js',
			array( 'badaround-map-navigation' ),
			(string) filemtime( $dir . '/assets/js/map.js' ),
			true
		);
		wp_script_add_data( 'badaround-map', 'strategy', 'defer' );

		$category_by_term = array();
		if ( taxonomy_exists( 'ba_tipo_evento' ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => 'ba_tipo_evento',
					'hide_empty' => false,
				)
			);
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$current = $term;
					while ( $current instanceof WP_Term && $current->parent ) {
						$parent = get_term( $current->parent, 'ba_tipo_evento' );
						if ( ! $parent instanceof WP_Term ) {
							break;
						}
						$current = $parent;
					}
					if ( $current instanceof WP_Term ) {
						$category_by_term[ (string) $term->term_id ] = $current->slug;
					}
				}
			}
		}

		wp_add_inline_script(
			'badaround-map',
			'window.BadAroundMap=' . wp_json_encode(
				array(
					'endpoint'       => esc_url_raw( rest_url( 'badaround/v1/discovery' ) ),
					'limit'          => 100,
					'hasMaps'        => (bool) $google_maps_key,
					'mapsUrl'        => $google_maps_key
						? add_query_arg(
							array(
								'key'     => $google_maps_key,
								'loading' => 'async',
								'v'       => 'weekly',
							),
							'https://maps.googleapis.com/maps/api/js'
						)
						: '',
					'categoryByTerm' => $category_by_term,
					'placeholder'    => badaround_child_placeholder_url(),
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
		wp_add_inline_script(
			'badaround-sentinels',
			'window.BadAroundSentinels=' . wp_json_encode(
				array(
					'endpoint' => esc_url_raw( rest_url( 'badaround/v1/sentinels' ) ),
				)
			) . ';',
			'before'
		);
	}

	if ( $is_states || $is_not_found ) {
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
		wp_enqueue_style( 'badaround-native-report', $uri . '/assets/css/native-report.css', array( 'badaround-report' ), (string) filemtime( $dir . '/assets/css/native-report.css' ) );
		$previous = array( 'badaround-map-navigation' );
		foreach ( array( 'model', 'api', 'errors', 'media', 'media-view', 'location-map', 'smart-address', 'wizard' ) as $module ) {
			$handle = 'badaround-native-' . $module;
			wp_enqueue_script( $handle, $uri . '/assets/js/native-report/' . $module . '.js', $previous, (string) filemtime( $dir . '/assets/js/native-report/' . $module . '.js' ), true );
			wp_script_add_data( $handle, 'strategy', 'defer' );
			$previous = array( $handle );
		}
	}

	if ( $is_home || $is_location ) {
		wp_enqueue_script(
			'badaround-ui',
			$uri . '/assets/js/ui.js',
			array(),
			(string) filemtime( $dir . '/assets/js/ui.js' ),
			true
		);
		wp_script_add_data( 'badaround-ui', 'strategy', 'defer' );
	}
}
add_action( 'wp_enqueue_scripts', 'badaround_child_enqueue_assets', 20 );

/**
 * Warm up only the third-party origins used by the public maps.
 */
function badaround_child_map_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' !== $relation_type || ( ! is_front_page() && ! is_page_template( 'page-mappa.php' ) && ! is_singular( 'ba_evento' ) ) ) {
		return $urls;
	}

	$urls[] = array(
		'href'        => 'https://maps.googleapis.com',
		'crossorigin' => 'anonymous',
	);
	$urls[] = array(
		'href'        => 'https://maps.gstatic.com',
		'crossorigin' => 'anonymous',
	);

	return $urls;
}
add_filter( 'wp_resource_hints', 'badaround_child_map_resource_hints', 10, 2 );

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
