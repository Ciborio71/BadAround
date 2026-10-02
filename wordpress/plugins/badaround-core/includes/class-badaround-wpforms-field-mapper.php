<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes WPForms #6 into classified BadAround intake data.
 *
 * The mapper intentionally keeps high-risk/free-text data in the private
 * report envelope. Only low-risk structured candidates are projected to the
 * pending ba_evento record before human moderation.
 */
class BadAround_WPForms_Field_Mapper {
	const FORM_SCHEMA_VERSION = 'wpforms-6-v3.0-2026-10-02';

	/** Fields documented as publishable only after moderation. */
	private const PUBLIC_CANDIDATE_FIELDS = array(
		2, 3, 4, 5, 6, 7, 8, 9, 10,
		16, 17, 18, 19, 20, 22, 23, 24, 25,
		29, 32, 33, 34,
		38, 39, 40, 41, 44,
		45, 46, 47, 48,
		49, 51, 88,
		56, 58, 59,
		65, 66, 68, 69, 70,
		76, 77,
	);

	/** Fields that must remain in the private report datastore. */
	private const PRIVATE_FIELDS = array(
		11, 31, 37, 42, 43, 50,
		55, 57, 60, 61, 62, 64,
		73, 74, 75, 78,
		81, 82, 83, 84, 85,
		89, 90, 91,
	);

	/** Free-text values never copied to ba_evento before moderation. */
	private const FREE_TEXT_FIELDS = array( 10, 29, 39, 40, 44, 46, 47, 48, 49, 77, 88 );

	/** Branch field for the selected event subtype, keyed by top category choice ID. */
	private const SUBTYPE_FIELD_BY_CATEGORY_CHOICE = array(
		4  => 3,
		5  => 4,
		6  => 5,
		7  => 6,
		8  => 7,
		9  => 8,
		10 => 9,
	);

	public function normalize( $fields, $entry, $form_data ) {
		$public_candidates = array();
		$private_fields    = array();
		$choice_ids        = array();

		foreach ( self::PUBLIC_CANDIDATE_FIELDS as $field_id ) {
			if ( ! $this->has_value( $fields, $field_id ) ) {
				continue;
			}
			$public_candidates[ $field_id ] = $this->field_value( $fields, $field_id );
			$resolved = $this->choice_ids( $field_id, $fields, $form_data );
			if ( $resolved ) {
				$choice_ids[ $field_id ] = $resolved;
			}
		}

		foreach ( self::PRIVATE_FIELDS as $field_id ) {
			if ( ! $this->has_value( $fields, $field_id ) ) {
				continue;
			}
			$private_fields[ $field_id ] = $this->field_value( $fields, $field_id );
			$resolved = $this->choice_ids( $field_id, $fields, $form_data );
			if ( $resolved ) {
				$choice_ids[ $field_id ] = $resolved;
			}
		}

		$category_choice = $this->first_choice_id( 2, $choice_ids );
		$subtype_field   = isset( self::SUBTYPE_FIELD_BY_CATEGORY_CHOICE[ $category_choice ] ) ? self::SUBTYPE_FIELD_BY_CATEGORY_CHOICE[ $category_choice ] : 0;
		$subtype_choice  = $subtype_field ? $this->first_choice_id( $subtype_field, $choice_ids ) : 0;
		$geo             = $this->extract_geolocation( $fields, $entry );
		$name            = $this->extract_name( isset( $fields[73] ) ? $fields[73] : array() );
		$plate           = $this->has_value( $fields, 43 ) ? strtoupper( preg_replace( '/[^A-Z0-9?]/i', '', $this->field_value( $fields, 43 ) ) ) : '';

		return array(
			'schema_version'    => self::FORM_SCHEMA_VERSION,
			'public_candidates' => $public_candidates,
			'private_fields'    => $private_fields,
			'choice_ids'        => $choice_ids,
			'classification'    => array(
				'public_candidate_ids' => self::PUBLIC_CANDIDATE_FIELDS,
				'private_ids'          => self::PRIVATE_FIELDS,
			),
			'event_type'        => array(
				'category_field_id' => 2,
				'category_choice_id' => $category_choice,
				'subtype_field_id'   => $subtype_field,
				'subtype_choice_id'  => $subtype_choice,
			),
			'location'          => array(
				'exact_address'            => $this->field_value( $fields, 31 ),
				'area_label'               => $this->field_value( $fields, 29 ),
				'public_precision_choice'  => $this->first_choice_id( 32, $choice_ids ),
				'place_type_choice'        => $this->first_choice_id( 33, $choice_ids ),
				'exact_lat'                => $geo['lat'],
				'exact_lng'                => $geo['lng'],
				'external_place_id'        => $geo['place_id'],
			),
			'reporter'          => array(
				'first_name'         => $name['first'],
				'last_name'          => $name['last'],
				'email'              => sanitize_email( $this->field_value( $fields, 74 ) ),
				'phone'              => sanitize_text_field( $this->field_value( $fields, 75 ) ),
				'public_mode_choice' => $this->first_choice_id( 76, $choice_ids ),
				'pseudonym'          => sanitize_text_field( $this->field_value( $fields, 77 ) ),
				'contact_choice'     => $this->first_choice_id( 78, $choice_ids ),
			),
			'vehicle'           => array(
				'description_choice' => $this->first_choice_id( 37, $choice_ids ),
				'type_choice'        ==> $this->first_choice_id( 38, $choice_ids ),
				'make'               => sanitize_text_field( $this->field_value( $fields, 39 ) ),
				'model'              => sanitize_text_field( $this->field_value( $fields, 40 ) ),
			'color'              => sanitize_text_field( $this->field_value( $fields, 41 ) ),
				'plate_knowledge'    => $this->first_choice_id( 42, $choice_ids ),
				'plate_raw'          => $plate,
			),
			'consents'          => array(
				'truthfulness' => $this->has_value( $fields, 81 ),
				'media_rights' => $this->has_value( $fields, 82 ),
				'publishing'   => $this->has_value( $fields, 83 ),
				'terms'        => $this->has_value( $fields, 84 ),
				'privacy'      => $this->has_value( $fields, 85 ),
				'version'      => self::FORM_SCHEMA_VERSION,
			),
			'technical'         => array(
				'user_agent_hash' => $this->request_header_hash( 'HTTP_USER_AGENT' ),
			),
	);
	}

	public function event_meta( $normalized ) {
		$public = isset( $normalized['public_candidates'] ) ? $normalized['public_candidates'] : array();
		$choice = isset( $normalized['choice_ids'] ) ? $normalized['choice_ids'] : array();
		$vehicle = isset( $normalized['vehicle'] ) ? $normalized['vehicle'] : array();

		$meta = array(
			'_ba_moderation_status'         => 'new',
			'_ba_event_status'              => 'open',
			'_ba_time_precision'            => $this->stable_choice_code( 16, $this->first_choice_id( 16, $choice ) ),
			'_ba_public_location_precision' => $this->stable_choice_code( 32, isset( $normalized['location']['public_precision_choice'] ) ? $normalized['location']['public_precision_choice'] : 0 ),
			'_ba_public_place_name'         => isset( $normalized['location']['area_label'] ) ? sanitize_text_field( $normalized['location']['area_label'] ) : '',
			'_ba_vehicle_involved'          => 4 === $this->first_choice_id( 2, $choice ),
			'_ba_vehicle_type'              => $this->stable_choice_code( 38, isset( $vehicle['type_choice'] ) ? $vehicle['type_choice'] : 0 ),
			'_ba_vehicle_make'              => isset( $vehicle['make'] ) ? $vehicle['make'] : '',
			'_ba_vehicle_model'             => isset( $vehicle['model'] ) ? $vehicle['model'] : '',
			'_ba_vehicle_color'             => isset( $vehicle['color'] ) ? $vehicle['color'] : '',
			'_ba_reward_available'          => in_array( $this->first_choice_id( 65, $choice ), array( 19, 20 ), true ),
			'_ba_reward_amount'             => isset( $public[66] ) && is_numeric( $public[66] ) ? (float) $public[66] : null,
			'_ba_expires_at'                => isset( $public[69] ) ? sanitize_text_field( $public[69] ) : '',
		);

		/* User-entered free text and exact coordinates never enter ba_evento pre-moderation. */
		foreach ( self::FREE_TEXT_FIELDS as $field_id ) {
			unset( $public[ $field_id ] );
		}

		if ( isset( $public[17] ) ) {
			$meta['_ba_occurred_date'] = sanitize_text_field( $public[17] );
		}
		if ( isset( $public[19] ) ) {
			$meta['_ba_occurred_time'] = sanitize_text_field( $public[19] );
		}

		return array_filter(
			$meta,
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);
	}

	public function stable_choice_code( $field_id, $choice_id ) {
		$field_id  = absint( $field_id );
		$choice_id = absint( $choice_id );
		return $field_id && $choice_id ? sprintf( 'f%d-c%d', $field_id, $choice_id ) : '';
	}

	private function field_value( $fields, $field_id ) {
		if ( ! isset( $fields[ $field_id ] ) || ! is_array( $fields[ $field_id ] ) ) {
			return '';
		}

		$field = $fields[ $field_id ];
		$value = array_key_exists( 'value_raw', $field ) && '' !== $field['value_raw'] ? $field['value_raw'] : ( isset( $field['value'] ) ? $field['value'] : '' );

		if ( is_array( $value ) ) {
			$value = array_map( array( $this, 'sanitize_scalar' ), $value );
			return array_values( array_filter( $value, static function ( $item ) { return '' !== $item; } ) );
		}

		return $this->sanitize_scalar( $value );
	}

	private function sanitize_scalar( $value ) {
		return sanitize_textarea_field( is_scalar( $value ) ? (string) $value : '' );
	}

	private function has_value( $fields, $field_id ) {
		$value = $this->field_value( $fields, $field_id );
		return is_array( $value ) ? ! empty( $value ) : '' !== trim( (string) $value );
	}

	private function choice_ids( $field_id, $fields, $form_data ) {
		if ( ! isset( $fields[ $field_id ], $form_data['fields'][ $field_id ]['choices'] ) ) {
			return array();
		}

		$value   = $this->field_value( $fields, $field_id );
		$values  = is_array( $value ) ? $value : array( $value );
		$choices = $form_data['fields'][ $field_id ]['choices'];
		$ids     = array();

		foreach ( $choices as $choice_id => $choice ) {
			$label = isset( $choice['label'] ) ? sanitize_text_field( $choice['label'] ) : '';
			$raw   = isset( $choice['value'] ) && '' !== $choice['value'] ? sanitize_text_field( $choice['value'] ) : $label;
			foreach ( $values as $selected ) {
				if ( $this->same_choice_value( $selected, $raw ) || $this->same_choice_value( $selected, $label ) ) {
					$ids[] = absint( $choice_id );
					break;
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	private function same_choice_value( $left, $right ) {
		$normalize = static function ( $value ) {
			$value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
			$value = preg_replace( '/\s+/u', ' ', trim( $value ) );
			return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		};
		return $normalize( $left ) === $normalize( $right );
	}

	private function first_choice_id( $field_id, $choice_ids ) {
		return isset( $choice_ids[ $field_id ][0] ) ? absint( $choice_ids[ $field_id ][0] ) : 0;
	}

	private function extract_name( $field ) {
		$first = isset( $field['first'] ) ? sanitize_text_field( $field['first'] ) : '';
		$last  = isset( $field['last'] ) ? sanitize_text_field( $field['last'] ) : '';
		if ( $first || $last ) {
			return array( 'first' => $first, 'last' => $last );
		}

		$value = isset( $field['value'] ) ? trim( sanitize_text_field( $field['value'] ) ) : '';
		$parts = preg_split( '/\s+/u', $value, 2 );
		return array(
			'first' => isset( $parts[0] ) ? $parts[0] : '',
			'last'  => isset( $parts[1] ) ? $parts[1] : '',
		);
	}

	private function extract_geolocation( $fields, $entry ) {
		$haystacks = array();
		if ( isset( $fields[31] ) && is_array( $fields[31] ) ) {
			$haystacks[] = $fields[31];
		}
		if ( is_array( $entry ) ) {
			$haystacks[] = $entry;
		}

		$result = array( 'lat' => null, 'lng' => null, 'place_id' => '' );
		foreach ( $haystacks as $haystack ) {
			$this->scan_geo_values( $haystack, $result );
		}
		return $result;
	}

	private function scan_geo_values( $value, &$result ) {
		if ( ! is_array( $value ) ) {
			return;
		}
		foreach ( $value as $key => $item ) {
			$key = strtolower( (string) $key );
			if ( in_array( $key, array( 'lat', 'latitude' ), true ) && is_numeric( $item ) ) {
				$result['lat'] = (float) $item;
			} elseif ( in_array( $key, array( 'lng', 'lon', 'longitude' ), true ) && is_numeric( $item ) ) {
				$result['lng'] = (float) $item;
			} elseif ( in_array( $key, array( 'place_id', 'placeid' ), true ) && is_scalar( $item ) ) {
				$result['place_id'] = sanitize_text_field( (string) $item );
			} elseif ( is_array( $item ) ) {
				$this->scan_geo_values( $item, $result );
			}
		}
	}

	private function request_header_hash( $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			return '';
		}
		$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
		return hash_hmac( 'sha256', $value, wp_salt( 'auth' ) );
	}
}
