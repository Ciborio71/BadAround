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

	/**
	 * F1.15A.3: native-only canonical hierarchy identity; no address heuristics.
	 * Canonical v1 transports names, not term IDs. A duplicate at any level
	 * is rejected rather than selecting an arbitrary WordPress term.
	 *
	 * @return int|WP_Error Deepest unambiguous canonical term.
	 */
	public function resolve_path( $location ) {
		$taxonomy = BadAround_Event_Post_Type::TERRITORY_TAX;
		if ( ! is_array( $location ) ) {
			return new WP_Error( 'ba_report_invalid_location', 'Percorso territoriale non valido.', array( 'field' => 'location.region' ) );
		}
		$parent = 0;
		$terminal = 0;
		foreach ( array( 'region', 'province', 'municipality', 'locality' ) as $level ) {
			$value = isset( $location[ $level ] ) && is_string( $location[ $level ] ) ? trim( $location[ $level ] ) : '';
			if ( '' === $value ) {
				if ( 'locality' === $level ) {
					return $terminal;
				}
				return new WP_Error( 'ba_report_invalid_location', 'Livello territoriale obbligatorio mancante.', array( 'field' => 'location.' . $level ) );
			}
			$terms = get_terms( array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'name' => $value,
				'parent' => $parent,
			) );
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				return new WP_Error( 'ba_report_invalid_location', 'Territorio non verificabile.', array( 'field' => 'location.' . $level ) );
			}
			$matches = array();
			foreach ( $terms as $term ) {
				if ( isset( $term->term_id, $term->parent, $term->name, $term->taxonomy )
					&& $taxonomy === $term->taxonomy
					&& (int) $term->parent === $parent
					&& $term->name === $value
					&& (int) $term->term_id > 0 ) {
					$matches[] = (int) $term->term_id;
				}
			}
			if ( 1 !== count( $matches ) ) {
				return new WP_Error( 'ba_report_invalid_location', 'Territorio inesistente o ambiguo.', array( 'field' => 'location.' . $level ) );
			}
			$terminal = $matches[0];
			$parent = $terminal;
		}
		return $terminal;
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
