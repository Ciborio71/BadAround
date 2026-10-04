<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical public discovery query for maps, search, filters and future sentinels.
 */
class BadAround_Discovery_Query {
	const REST_NAMESPACE   = 'badaround/v1';
	const REST_ROUTE       = '/discovery';
	const DEFAULT_LIMIT    = 100;
	const MAX_LIMIT        = 100;
	const SEARCH_ID_LIMIT  = 500;

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	public function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_discover' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'territory' => array(
						'sanitize_callback' => 'sanitize_title',
					),
					'category' => array(
						'sanitize_callback' => 'sanitize_title',
					),
					'event_type' => array(
						'sanitize_callback' => 'sanitize_title',
					),
					'period' => array(
						'sanitize_callback' => 'absint',
					),
					'search' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page' => array(
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'default'           => self::DEFAULT_LIMIT,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function rest_discover( WP_REST_Request $request ) {
		return rest_ensure_response(
			$this->discover(
				array(
					'territory'  => $request->get_param( 'territory' ),
					'category'   => $request->get_param( 'category' ),
					'event_type' => $request->get_param( 'event_type' ),
					'period'     => $request->get_param( 'period' ),
					'search'     => $request->get_param( 'search' ),
					'page'       => $request->get_param( 'page' ),
					'per_page'   => $request->get_param( 'per_page' ),
				)
			)
		);
	}

	public function discover( $filters = array() ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'territory'  => '',
				'category'   => '',
				'event_type' => '',
				'period'     => 0,
				'search'     => '',
				'page'       => 1,
				'per_page'   => self::DEFAULT_LIMIT,
			)
		);

		$page     = max( 1, absint( $filters['page'] ) );
		$per_page = min( self::MAX_LIMIT, max( 1, absint( $filters['per_page'] ) ) );
		$search   = trim( sanitize_text_field( (string) $filters['search'] ) );
		$period   = in_array( absint( $filters['period'] ), array( 7, 30, 90 ), true ) ? absint( $filters['period'] ) : 0;
		$territory_matches = array();

		$args = array(
			'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'meta_query'          => BadAround_Discovery_Contract::public_gate_meta_query(),
		);

		if ( '' !== $search ) {
			$resolved = $this->resolve_search( $search );
			$territory_matches = $resolved['territory_matches'];

			if ( empty( $resolved['post_ids'] ) ) {
				return $this->empty_result( $filters, $page, $territory_matches );
			}

			$args['post__in'] = $resolved['post_ids'];
		}

		if ( $period ) {
			$period_ids = $this->resolve_period_post_ids( $period );
			if ( empty( $period_ids ) ) {
				return $this->empty_result( $filters, $page, $territory_matches );
			}

			$args['post__in'] = isset( $args['post__in'] )
				? array_values( array_intersect( $args['post__in'], $period_ids ) )
				: $period_ids;

			if ( empty( $args['post__in'] ) ) {
				return $this->empty_result( $filters, $page, $territory_matches );
			}
		}

		$tax_query = BadAround_Discovery_Contract::tax_query_from_slugs(
			(string) $filters['territory'],
			(string) $filters['category'],
			(string) $filters['event_type']
		);
		if ( $tax_query ) {
			$args['tax_query'] = $tax_query;
		}

		$query = new WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $post ) {
			$projection = $this->public_projection( $post );
			if ( $projection ) {
				$items[] = $projection;
			}
		}

		return array(
			'items'               => $items,
			'total'               => (int) $query->found_posts,
			'page'                => $page,
			'pages'               => (int) $query->max_num_pages,
			'filters'             => array(
				'territory'  => sanitize_title( (string) $filters['territory'] ),
				'category'   => sanitize_title( (string) $filters['category'] ),
				'event_type' => sanitize_title( (string) $filters['event_type'] ),
				'period'     => $period,
				'search'     => $search,
			),
			'territory_matches'   => $territory_matches,
		);
	}

	private function resolve_period_post_ids( $days ) {
		$days = absint( $days );
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'posts_per_page'      => self::SEARCH_ID_LIMIT,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				'meta_query'          => BadAround_Discovery_Contract::public_gate_meta_query(),
			)
		);

		$today     = new DateTimeImmutable( 'today', wp_timezone() );
		$threshold = $today->sub( new DateInterval( 'P' . max( 0, $days - 1 ) . 'D' ) );
		$ids       = array();

		foreach ( $query->posts as $post_id ) {
			$value = trim( (string) get_post_meta( $post_id, '_ba_occurred_date', true ) );
			if ( '' === $value ) {
				continue;
			}

			$date = DateTimeImmutable::createFromFormat( '!d/m/Y', $value, wp_timezone() );
			if ( ! $date ) {
				$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
			}

			if ( $date && $date >= $threshold && $date <= $today ) {
				$ids[] = absint( $post_id );
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private function resolve_search( $search ) {
		$post_ids = array();

		$text_query = new WP_Query(
			array(
				'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
				'post_status'         => 'publish',
				'fields'              => 'ids',
				'posts_per_page'      => self::SEARCH_ID_LIMIT,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
				's'                   => $search,
				'meta_query'          => BadAround_Discovery_Contract::public_gate_meta_query(),
			)
		);

		$post_ids = array_map( 'absint', $text_query->posts );

		$territories = $this->find_matching_territories( $search );
		$territory_matches = array();

		if ( $territories ) {
			$term_ids = array();

			foreach ( $territories as $term ) {
				$term_ids[] = (int) $term->term_id;
				$territory_matches[] = $this->territory_projection( $term );
			}

			$territory_query = new WP_Query(
				array(
					'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
					'post_status'         => 'publish',
					'fields'              => 'ids',
					'posts_per_page'      => self::SEARCH_ID_LIMIT,
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
					'meta_query'          => BadAround_Discovery_Contract::public_gate_meta_query(),
					'tax_query'           => array(
						array(
							'taxonomy'         => BadAround_Event_Post_Type::TERRITORY_TAX,
							'field'            => 'term_id',
							'terms'            => $term_ids,
							'include_children' => true,
						),
					),
				)
			);

			$post_ids = array_merge( $post_ids, array_map( 'absint', $territory_query->posts ) );
		}

		return array(
			'post_ids'             => array_values( array_unique( array_filter( $post_ids ) ) ),
			'territory_matches'    => $territory_matches,
		);
	}

	private function find_matching_territories( $search ) {
		$taxonomy = BadAround_Event_Post_Type::TERRITORY_TAX;
		$needle   = $this->normalize_label( $search );
		$matches  = array();

		$slug_match = get_term_by( 'slug', sanitize_title( $search ), $taxonomy );
		if ( $slug_match instanceof WP_Term ) {
			$matches[ $slug_match->term_id ] = $slug_match;
		}

		$candidates = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'name__like' => $search,
				'number'     => 20,
			)
		);

		if ( ! is_wp_error( $candidates ) ) {
			foreach ( $candidates as $term ) {
				if ( $needle === $this->normalize_label( $term->name ) ) {
					$matches[ $term->term_id ] = $term;
				}
			}
		}

		return array_values( $matches );
	}

	private function territory_projection( WP_Term $term ) {
		$taxonomy = BadAround_Event_Post_Type::TERRITORY_TAX;
		$ancestor_ids = array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
		$path = array();

		foreach ( $ancestor_ids as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor instanceof WP_Term ) {
				$path[] = $ancestor->name;
			}
		}
		$path[] = $term->name;

		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			$url = '';
		}

		return array(
			'id'   => (int) $term->term_id,
			'name' => $term->name,
			'slug' => $term->slug,
			'path' => $path,
			'url'  => esc_url_raw( $url ),
		);
	}

	private function normalize_label( $value ) {
		$value = remove_accents( wp_strip_all_tags( (string) $value ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( $value ), 'UTF-8' ) : strtolower( trim( $value ) );
	}

	private function empty_result( $filters, $page, $territory_matches = array() ) {
		return array(
			'items'             => array(),
			'total'             => 0,
			'page'              => $page,
			'pages'             => 0,
			'filters'           => array(
				'territory'  => sanitize_title( (string) $filters['territory'] ),
				'category'   => sanitize_title( (string) $filters['category'] ),
				'event_type' => sanitize_title( (string) $filters['event_type'] ),
				'period'     => in_array( absint( $filters['period'] ), array( 7, 30, 90 ), true ) ? absint( $filters['period'] ) : 0,
				'search'     => trim( sanitize_text_field( (string) $filters['search'] ) ),
			),
			'territory_matches' => $territory_matches,
		);
	}

	private function public_projection( WP_Post $post ) {
		$event_id = (int) $post->ID;

		if ( ! BadAround_Discovery_Contract::is_public_event( $event_id ) ) {
			return null;
		}

		$geo = $this->public_geo( $event_id );
		$event_type = $this->deepest_term( get_the_terms( $event_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX ), BadAround_Event_Post_Type::EVENT_TYPE_TAX );
		$territory  = $this->deepest_term( get_the_terms( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX ), BadAround_Event_Post_Type::TERRITORY_TAX );

		$excerpt = trim( wp_strip_all_tags( get_the_excerpt( $event_id ) ) );
		if ( ! $excerpt ) {
			$excerpt = trim( wp_strip_all_tags( $post->post_content ) );
		}

		$image = get_the_post_thumbnail_url( $event_id, 'medium' );

		return array(
			'id'                => $event_id,
			'title'             => get_the_title( $event_id ),
			'permalink'         => get_permalink( $event_id ),
			'excerpt'           => wp_trim_words( $excerpt, 18, '…' ),
			'thumbnail'         => $image ? esc_url_raw( $image ) : '',
			'event_type'        => $event_type ? array(
				'id'   => (int) $event_type->term_id,
				'name' => $event_type->name,
				'slug' => $event_type->slug,
			) : null,
			'territory'         => $territory ? array(
				'id'   => (int) $territory->term_id,
				'name' => $territory->name,
				'slug' => $territory->slug,
			) : null,
			'occurred_date'     => sanitize_text_field( (string) get_post_meta( $event_id, '_ba_occurred_date', true ) ),
			'occurred_time'     => sanitize_text_field( (string) get_post_meta( $event_id, '_ba_occurred_time', true ) ),
			'event_status'      => sanitize_key( (string) get_post_meta( $event_id, '_ba_event_status', true ) ),
			'public_place_name' => sanitize_text_field( (string) get_post_meta( $event_id, '_ba_public_place_name', true ) ),
			'public_geo'        => $geo,
		);
	}

	private function public_geo( $event_id ) {
		$lat    = get_post_meta( $event_id, '_ba_public_lat', true );
		$lng    = get_post_meta( $event_id, '_ba_public_lng', true );
		$radius = absint( get_post_meta( $event_id, '_ba_public_radius_m', true ) );

		if ( is_numeric( $lat ) && is_numeric( $lng ) && $radius >= 100 ) {
			return array(
				'lat'       => (float) $lat,
				'lng'       => (float) $lng,
				'radius_m'  => $radius,
				'source'    => 'event_public',
				'precision' => sanitize_key( (string) get_post_meta( $event_id, '_ba_public_location_precision', true ) ),
			);
		}

		$territories = get_the_terms( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( is_wp_error( $territories ) || ! $territories ) {
			return null;
		}

		usort(
			$territories,
			static function ( $a, $b ) {
				return count( get_ancestors( $b->term_id, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' ) )
					<=> count( get_ancestors( $a->term_id, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' ) );
			}
		);

		foreach ( $territories as $term ) {
			$center_lat = get_term_meta( $term->term_id, '_ba_center_lat', true );
			$center_lng = get_term_meta( $term->term_id, '_ba_center_lng', true );
			if ( is_numeric( $center_lat ) && is_numeric( $center_lng ) ) {
				return array(
					'lat'       => (float) $center_lat,
					'lng'       => (float) $center_lng,
					'radius_m'  => $radius >= 100 ? $radius : 1000,
					'source'    => 'territory_center',
					'precision' => sanitize_key( (string) get_term_meta( $term->term_id, '_ba_geo_level', true ) ),
					'territory' => array(
						'id'   => (int) $term->term_id,
						'name' => $term->name,
						'slug' => $term->slug,
					),
				);
			}
		}

		return null;
	}

	private function deepest_term( $terms, $taxonomy ) {
		if ( is_wp_error( $terms ) || ! $terms ) {
			return null;
		}

		usort(
			$terms,
			static function ( $a, $b ) use ( $taxonomy ) {
				return count( get_ancestors( $b->term_id, $taxonomy, 'taxonomy' ) )
					<=> count( get_ancestors( $a->term_id, $taxonomy, 'taxonomy' ) );
			}
		);

		return $terms[0];
	}
}
