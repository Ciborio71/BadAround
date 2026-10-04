<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class BadAround_Discovery_Contract {
	public static function public_gate_meta_query() {
		return array(
			array(
				'key'     => '_ba_moderation_status',
				'value'   => BadAround_Publication_Service::STATUS_PUBLISHED,
				'compare' => '=',
			),
		);
	}

	public static function is_public_event( $event_id ) {
		$event_id = absint( $event_id );
		return $event_id
			&& BadAround_Event_Post_Type::POST_TYPE === get_post_type( $event_id )
			&& 'publish' === get_post_status( $event_id )
			&& BadAround_Publication_Service::STATUS_PUBLISHED === sanitize_key( (string) get_post_meta( $event_id, '_ba_moderation_status', true ) );
	}

	public static function tax_query_from_slugs( $territory = '', $category = '', $event_type = '' ) {
		$tax_query = array();

		if ( '' !== $territory ) {
			$tax_query[] = array(
				'taxonomy'         => BadAround_Event_Post_Type::TERRITORY_TAX,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( $territory ) ),
				'include_children' => true,
			);
		}
		if ( '' !== $category ) {
			$tax_query[] = array(
				'taxonomy'         => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( $category ) ),
				'include_children' => true,
			);
		}
		if ( '' !== $event_type ) {
			$tax_query[] = array(
				'taxonomy'         => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( $event_type ) ),
				'include_children' => false,
			);
		}
		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}
		return $tax_query;
	}

	public static function event_matches_ids( $event_id, $territory_id, $category_id = 0, $event_type_id = 0 ) {
		if ( ! self::is_public_event( $event_id ) ) {
			return false;
		}

		$territory = get_term( absint( $territory_id ), BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( ! $territory instanceof WP_Term ) {
			return false;
		}

		$filters = array( 'territory' => $territory->slug, 'category' => '', 'event_type' => '' );

		if ( $category_id ) {
			$category = get_term( absint( $category_id ), BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $category instanceof WP_Term || 0 !== (int) $category->parent ) {
				return false;
			}
			$filters['category'] = $category->slug;
		}

		if ( $event_type_id ) {
			$event_type = get_term( absint( $event_type_id ), BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $event_type instanceof WP_Term || 0 === (int) $event_type->parent ) {
				return false;
			}
			if ( $category_id && ! term_is_ancestor_of( absint( $category_id ), $event_type->term_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX ) ) {
				return false;
			}
			$filters['event_type'] = $event_type->slug;
		}

		$args = array(
			'post_type'      => BadAround_Event_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'p'              => absint( $event_id ),
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'no_found_rows'   => true,
			'meta_query'     => self::public_gate_meta_query(),
		);
		$tax_query = self::tax_query_from_slugs( $filters['territory'], $filters['category'], $filters['event_type'] );
		if ( $tax_query ) { $args['tax_query'] = $tax_query; }

		$query = new WP_Query( $args );
		return ! empty( $query->posts );
	}
}
