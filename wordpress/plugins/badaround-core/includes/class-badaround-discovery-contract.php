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

		$territory_id = absint( $territory_id );
		$category_id  = absint( $category_id );
		$event_type_id = absint( $event_type_id );

		$territory = get_term( $territory_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( ! $territory instanceof WP_Term ) {
			return false;
		}

		$event_territories = get_the_terms( $event_id, BadAround_Event_Post_Type::TERRITORY_TAX );
		if ( is_wp_error( $event_territories ) || ! $event_territories ) {
			return false;
		}

		$territory_match = false;
		foreach ( $event_territories as $event_territory ) {
			if (
				(int) $event_territory->term_id === $territory_id ||
				term_is_ancestor_of( $territory_id, $event_territory->term_id, BadAround_Event_Post_Type::TERRITORY_TAX )
			) {
				$territory_match = true;
				break;
			}
		}
		if ( ! $territory_match ) {
			return false;
		}

		$event_types = get_the_terms( $event_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
		if ( is_wp_error( $event_types ) || ! $event_types ) {
			return false;
		}

		if ( $category_id ) {
			$category = get_term( $category_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $category instanceof WP_Term || 0 !== (int) $category->parent ) {
				return false;
			}

			$category_match = false;
			foreach ( $event_types as $assigned_type ) {
				if (
					(int) $assigned_type->term_id === $category_id ||
					term_is_ancestor_of( $category_id, $assigned_type->term_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX )
				) {
					$category_match = true;
					break;
				}
			}
			if ( ! $category_match ) {
				return false;
			}
		}

		if ( $event_type_id ) {
			$event_type = get_term( $event_type_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX );
			if ( ! $event_type instanceof WP_Term || 0 === (int) $event_type->parent ) {
				return false;
			}
			if ( $category_id && ! term_is_ancestor_of( $category_id, $event_type_id, BadAround_Event_Post_Type::EVENT_TYPE_TAX ) ) {
				return false;
			}

			$event_type_match = false;
			foreach ( $event_types as $assigned_type ) {
				if ( (int) $assigned_type->term_id === $event_type_id ) {
					$event_type_match = true;
					break;
				}
			}
			if ( ! $event_type_match ) {
				return false;
			}
		}

		return true;
	}
}
