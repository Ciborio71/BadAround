<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical public discovery query for maps, search, filters and future sentinels.
 */
class BadAround_Discovery_Query {
	const REST_NAMESPACE = 'badaround/v1';
	const REST_ROUTE     = '/discovery';
	const DEFAULT_LIMIT  = 100;
	const MAX_LIMIT      = 100;

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
					'event_type' => array(
						'sanitize_callback' => 'sanitize_title',
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
		$result = $this->discover(
			array(
				'territory'  => $request->get_param( 'territory' ),
				'event_type' => $request->get_param( 'event_type' ),
				'page'       => $request->get_param( 'page' ),
				'per_page'   => $request->get_param( 'per_page' ),
			)
		);

		return rest_ensure_response( $result );
	}

	public function discover( $filters = array() ) {
		$filters = wp_parse_args(
			$filters,
			array(
				'territory'  => '',
				'event_type' => '',
				'page'       => 1,
				'per_page'   => self::DEFAULT_LIMIT,
			)
		);

		$page     = max( 1, absint( $filters['page'] ) );
		$per_page = min( self::MAX_LIMIT, max( 1, absint( $filters['per_page'] ) ) );

		$args = array(
			'post_type'           => BadAround_Event_Post_Type::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'meta_query'          => array(
				array(
					'key'     => '_ba_moderation_status',
					'value'   => BadAround_Publication_Service::STATUS_PUBLISHED,
					'compare' => '=',
				),
			),
		);

		$tax_query = array();

		if ( ! empty( $filters['territory'] ) ) {
			$tax_query[] = array(
				'taxonomy'         => BadAround_Event_Post_Type::TERRITORY_TAX,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( $filters['territory'] ) ),
				'include_children' => true,
			);
		}

		if ( ! empty( $filters['event_type'] ) ) {
			$tax_query[] = array(
				'taxonomy'         => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( $filters['event_type'] ) ),
				'include_children' => true,
			);
		}

		if ( $tax_query ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
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
			'items'   => $items,
			'total'   => (int) $query->found_posts,
			'page'    => $page,
			'pages'   => (int) $query->max_num_pages,
			'filters' => array(
				'territory'  => sanitize_title( (string) $filters['territory'] ),
				'event_type' => sanitize_title( (string) $filters['event_type'] ),
			),
		);
	}

	private function public_projection( WP_Post $post ) {
		$event_id = (int) $post->ID;

		if (
			'publish' !== $post->post_status ||
			BadAround_Publication_Service::STATUS_PUBLISHED !== sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) )
		) {
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
