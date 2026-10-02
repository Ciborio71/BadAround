<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolves intake text only against pre-existing canonical ba_territorio terms. */
class BadAround_Territory_Resolver {
	public function resolve( $area_label, $exact_address ) {
		$area_label    = trim( sanitize_text_field( $area_label ) );
		$exact_address = trim( sanitize_text_field( $exact_address ) );
		$candidates    = array();

		if ( $area_label ) {
			$candidates = get_terms(
				array(
					'taxonomy'   => BadAround_Event_Post_Type::TERRITORY_TAX,
					'hide_empty' => false,
					'name'       => $area_label,
				)
			);
			if ( is_wp_error( $candidates ) ) {
				$candidates = array();
			}
		}

		if ( 1 === count( $candidates ) ) {
			return (int) $candidates[0]->term_id;
		}

		if ( $candidates ) {
			$matched = $this->disambiguate_by_ancestors( $candidates, $exact_address );
			if ( $matched ) {
				return $matched;
			}
		}

		/* Fallback: find the deepest canonical term whose name occurs in the address. */
		$terms = get_terms(
			array(
				'taxonomy'   => BadAround_Event_Post_Type::TERRITORY_TAX,
				'hide_empty' => false,
				'number'     => 500,
			)
		);
		if ( is_wp_error( $terms ) || ! $terms ) {
			return 0;
		}

		$best_id    = 0;
		$best_depth = -1;
		foreach ( $terms as $term ) {
			if ( ! $exact_address || false === $this->contains( $exact_address, $term->name ) ) {
				continue;
			}
			$depth = count( get_ancestors( $term->term_id, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' ) );
			if ( $depth > $best_depth ) {
				$best_id    = (int) $term->term_id;
				$best_depth = $depth;
			}
		}
		return $best_id;
	}

	private function disambiguate_by_ancestors( $candidates, $address ) {
		$matches = array();
		foreach ( $candidates as $term ) {
			$score = 0;
			foreach ( get_ancestors( $term->term_id, BadAround_Event_Post_Type::TERRITORY_TAX, 'taxonomy' ) as $ancestor_id ) {
				$ancestor = get_term( $ancestor_id, BadAround_Event_Post_Type::TERRITORY_TAX );
				if ( $ancestor && ! is_wp_error( $ancestor ) && $this->contains( $address, $ancestor->name ) ) {
					++$score;
				}
			}
			$matches[ $term->term_id ] = $score;
		}
		arsort( $matches, SORT_NUMERIC );
		$ids = array_keys( $matches );
		if ( ! $ids || 0 === (int) reset( $matches ) ) {
			return 0;
		}
		if ( isset( $ids[1] ) && $matches[ $ids[0] ] === $matches[ $ids[1] ] ) {
			return 0;
		}
		return (int) $ids[0];
	}

	private function contains( $haystack, $needle ) {
		if ( function_exists( 'mb_stripos' ) ) {
			return mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return stripos( $haystack, $needle );
	}
}
