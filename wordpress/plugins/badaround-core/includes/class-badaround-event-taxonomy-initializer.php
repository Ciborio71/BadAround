<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Explicit one-time initializer for the canonical ba_tipo_evento taxonomy.
 *
 * Not hooked into report intake or normal runtime. A complete conflict preflight
 * is performed before any mutation is applied.
 */
class BadAround_Event_Taxonomy_Initializer {
	private $taxonomy;

	public function __construct( $taxonomy = null ) {
		$this->taxonomy = $taxonomy ?: BadAround_Event_Post_Type::EVENT_TYPE_TAX;
	}

	public function run() {
		$plan = $this->build_plan();
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$report = array(
			'category_count' => count( BadAround_Report_Schema::category_subtypes() ),
			'subtype_count'  => $this->subtype_count(),
			'reused'         => 0,
			'adopted'        => 0,
			'meta_added'     => 0,
			'created'        => 0,
			'mutation_count' => 0,
			'conflicts'      => array(),
		);

		foreach ( $plan as $item ) {
			$result = $this->apply_item( $item );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$report[ $result ]++;
			if ( 'adopted' === $result ) {
				$report['meta_added']++;
				$report['mutation_count']++;
			} elseif ( 'created' === $result ) {
				$report['mutation_count']++;
			}
		}

		return $report;
	}

	public function build_plan() {
		$source_check = $this->validate_sources();
		if ( is_wp_error( $source_check ) ) {
			return $source_check;
		}

		$mapping = BadAround_Event_Taxonomy_Map::mapping();
		$schema  = BadAround_Report_Schema::category_subtypes();
		$plan    = array();
		$parents = array();

		foreach ( $schema as $category => $subtypes ) {
			$item = $this->inspect_term( $category, $mapping['categories'][ $category ], 0, 'category' );
			if ( is_wp_error( $item ) ) {
				return $item;
			}
			$plan[] = $item;
			$parents[ $category ] = isset( $item['term_id'] ) ? (int) $item['term_id'] : null;
		}

		foreach ( $schema as $category => $subtypes ) {
			foreach ( $subtypes as $subtype ) {
				$item = $this->inspect_term(
					$subtype,
					$mapping['subtypes'][ $subtype ],
					$parents[ $category ],
					'subtype',
					$category
				);
				if ( is_wp_error( $item ) ) {
					return $item;
				}
				$plan[] = $item;
			}
		}

		return $plan;
	}

	private function validate_sources() {
		$mapping = BadAround_Event_Taxonomy_Map::mapping();
		$schema  = BadAround_Report_Schema::category_subtypes();

		if ( array_keys( $schema ) !== array_keys( $mapping['categories'] ) ) {
			return $this->conflict( 'source_mismatch', '', '', null, array(), 'Category mapping does not exactly match canonical schema.' );
		}

		$expected_subtypes = array();
		foreach ( $schema as $subtypes ) {
			$expected_subtypes = array_merge( $expected_subtypes, $subtypes );
		}
		if ( $expected_subtypes !== array_keys( $mapping['subtypes'] ) ) {
			return $this->conflict( 'source_mismatch', '', '', null, array(), 'Subtype mapping does not exactly match canonical schema.' );
		}

		return true;
	}

	private function inspect_term( $key, $slug, $expected_parent, $kind, $parent_key = '' ) {
		$by_key = $this->terms_by_meta( $key );
		if ( is_wp_error( $by_key ) ) {
			return $by_key;
		}
		if ( count( $by_key ) > 1 ) {
			return $this->conflict( 'duplicate_canonical_key', $key, $slug, $expected_parent, $by_key, 'Canonical key resolves to more than one term.' );
		}

		if ( 1 === count( $by_key ) ) {
			$term = $by_key[0];
			if ( $term->slug !== $slug ) {
				return $this->conflict( 'canonical_slug_mismatch', $key, $slug, $expected_parent, $by_key, 'Canonical key exists on a term with an incompatible slug.' );
			}
			if ( null === $expected_parent ) {
				return $this->conflict( 'unresolved_expected_parent', $key, $slug, null, $by_key, 'Subtype exists while its canonical parent category is not materialized.' );
			}
			if ( (int) $term->parent !== (int) $expected_parent ) {
				return $this->conflict( 'wrong_parent', $key, $slug, $expected_parent, $by_key, 'Canonical term exists under the wrong parent.' );
			}
			return $this->plan_item( 'reuse', $key, $slug, $kind, $parent_key, (int) $term->term_id );
		}

		$by_slug = $this->terms_by_slug( $slug );
		if ( is_wp_error( $by_slug ) ) {
			return $by_slug;
		}
		if ( count( $by_slug ) > 1 ) {
			return $this->conflict( 'slug_collision', $key, $slug, $expected_parent, $by_slug, 'Canonical slug resolves to more than one candidate.' );
		}

		if ( 1 === count( $by_slug ) ) {
			$term = $by_slug[0];
			if ( null === $expected_parent || (int) $term->parent !== (int) $expected_parent ) {
				return $this->conflict( 'unsafe_adoption_parent', $key, $slug, $expected_parent, $by_slug, 'Slug candidate does not have the uniquely expected parent.' );
			}
			$existing_key = get_term_meta( (int) $term->term_id, BadAround_Event_Taxonomy_Map::CANONICAL_META, true );
			if ( '' !== (string) $existing_key && $key !== (string) $existing_key ) {
				return $this->conflict( 'incompatible_canonical_meta', $key, $slug, $expected_parent, $by_slug, 'Slug candidate already carries a different canonical key.' );
			}
			return $this->plan_item( 'adopt', $key, $slug, $kind, $parent_key, (int) $term->term_id );
		}

		return $this->plan_item( 'create', $key, $slug, $kind, $parent_key, null );
	}

	private function apply_item( $item ) {
		if ( 'reuse' === $item['action'] ) {
			return 'reused';
		}

		if ( 'adopt' === $item['action'] ) {
			$added = add_term_meta( $item['term_id'], BadAround_Event_Taxonomy_Map::CANONICAL_META, $item['key'], true );
			if ( false === $added ) {
				return new WP_Error( 'ba_taxonomy_initializer_write_failed', 'Unable to add canonical term identity.', $item );
			}
			return 'adopted';
		}

		$parent_id = 0;
		if ( 'subtype' === $item['kind'] ) {
			$parent = $this->single_term_by_meta( $item['parent_key'] );
			if ( ! $parent ) {
				return new WP_Error( 'ba_taxonomy_initializer_parent_missing', 'Canonical parent is not materialized during apply.', $item );
			}
			$parent_id = (int) $parent->term_id;
		}

		$created = wp_insert_term(
			$this->label_from_slug( $item['slug'] ),
			$this->taxonomy,
			array(
				'slug'   => $item['slug'],
				'parent' => $parent_id,
			)
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		$term_id = isset( $created['term_id'] ) ? (int) $created['term_id'] : 0;
		if ( ! $term_id || false === add_term_meta( $term_id, BadAround_Event_Taxonomy_Map::CANONICAL_META, $item['key'], true ) ) {
			return new WP_Error( 'ba_taxonomy_initializer_write_failed', 'Unable to persist canonical identity on created term.', $item );
		}
		return 'created';
	}

	private function terms_by_meta( $key ) {
		return get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
				'meta_key'   => BadAround_Event_Taxonomy_Map::CANONICAL_META,
				'meta_value' => $key,
			)
		);
	}

	private function terms_by_slug( $slug ) {
		return get_terms(
			array(
				'taxonomy'   => $this->taxonomy,
				'hide_empty' => false,
				'slug'       => $slug,
			)
		);
	}

	private function single_term_by_meta( $key ) {
		$terms = $this->terms_by_meta( $key );
		return ! is_wp_error( $terms ) && 1 === count( $terms ) ? $terms[0] : null;
	}

	private function subtype_count() {
		$count = 0;
		foreach ( BadAround_Report_Schema::category_subtypes() as $subtypes ) {
			$count += count( $subtypes );
		}
		return $count;
	}

	private function plan_item( $action, $key, $slug, $kind, $parent_key, $term_id ) {
		return array(
			'action'     => $action,
			'key'        => $key,
			'slug'       => $slug,
			'kind'       => $kind,
			'parent_key' => $parent_key,
			'term_id'    => $term_id,
		);
	}

	private function conflict( $type, $key, $slug, $expected_parent, $terms, $message ) {
		$found = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$found[] = array(
				'term_id'       => isset( $term->term_id ) ? (int) $term->term_id : 0,
				'slug'          => isset( $term->slug ) ? $term->slug : '',
				'parent'        => isset( $term->parent ) ? (int) $term->parent : 0,
				'canonical_key' => isset( $term->term_id ) ? (string) get_term_meta( (int) $term->term_id, BadAround_Event_Taxonomy_Map::CANONICAL_META, true ) : '',
			);
		}

		return new WP_Error(
			'ba_canonical_taxonomy_conflict',
			$message,
			array(
				'conflict_type'   => $type,
				'canonical_key'   => $key,
				'expected_slug'   => $slug,
				'expected_parent' => $expected_parent,
				'found'           => $found,
			)
		);
	}

	private function label_from_slug( $slug ) {
		return ucwords( str_replace( '-', ' ', $slug ) );
	}
}
