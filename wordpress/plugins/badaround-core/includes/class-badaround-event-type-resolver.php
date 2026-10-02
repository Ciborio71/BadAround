<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Maps WPForms field/choice identity to stable ba_tipo_evento terms. */
class BadAround_Event_Type_Resolver {
	const STABLE_META = '_ba_wpforms_choice_key';

	public function resolve_and_assign( $post_id, $normalized, $form_data ) {
		$type = isset( $normalized['event_type'] ) ? $normalized['event_type'] : array();
		$category = $this->ensure_term(
			isset( $type['category_field_id'] ) ? $type['category_field_id'] : 0,
			isset( $type['category_choice_id'] ) ? $type['category_choice_id'] : 0,
			0,
			$form_data
		);
		if ( ! $category ) {
			return false;
		}

		$term_ids = array( $category );
		$subtype = $this->ensure_term(
			isset( $type['subtype_field_id'] ) ? $type['subtype_field_id'] : 0,
			isset( $type['subtype_choice_id'] ) ? $type['subtype_choice_id'] : 0,
			$category,
			$form_data
		);
		if ( $subtype ) {
			$term_ids[] = $subtype;
		}

		$result = wp_set_object_terms( $post_id, array_map( 'absint', $term_ids ), BadAround_Event_Post_Type::EVENT_TYPE_TAX, false );
		return ! is_wp_error( $result );
	}

	private function ensure_term( $field_id, $choice_id, $parent_id, $form_data ) {
		$field_id  = absint( $field_id );
		$choice_id = absint( $choice_id );
		if ( ! $field_id || ! $choice_id ) {
			return 0;
		}

		$stable_key = sprintf( 'wpforms6:f%d:c%d', $field_id, $choice_id );
		$existing = get_terms(
			array(
				'taxonomy'   => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => self::STABLE_META,
				'meta_value' => $stable_key,
			)
		);
		if ( ! is_wp_error( $existing ) && $existing ) {
			return (int) $existing[0]->term_id;
		}

		$label = $this->choice_label( $field_id, $choice_id, $form_data );
		if ( ! $label ) {
			return 0;
		}

		$created = wp_insert_term(
			$label,
			BadAround_Event_Post_Type::EVENT_TYPE_TAX,
			array(
				'parent' => absint( $parent_id ),
				'slug'   => sanitize_title( $label ),
			)
		);
		if ( is_wp_error( $created ) ) {
			if ( 'term_exists' === $created->get_error_code() ) {
				$data = $created->get_error_data();
				return is_array( $data ) && isset( $data['term_id'] ) ? (int) $data['term_id'] : absint( $data );
			}
			return 0;
		}

		$term_id = (int) $created['term_id'];
		update_term_meta( $term_id, self::STABLE_META, $stable_key );
		update_term_meta( $term_id, '_ba_source_field_id', $field_id );
		update_term_meta( $term_id, '_ba_source_choice_id', $choice_id );
		return $term_id;
	}

	private function choice_label( $field_id, $choice_id, $form_data ) {
		if ( ! isset( $form_data['fields'][ $field_id ]['choices'][ $choice_id ]['label'] ) ) {
			return '';
		}
		return sanitize_text_field( $form_data['fields'][ $field_id ]['choices'][ $choice_id ]['label'] );
	}
}
