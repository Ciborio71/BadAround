<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Temporary compatibility adapter:
 * WPForms #6 payload -> canonical BadAround report.
 *
 * WPForms IDs are intentionally confined to this adapter.
 */
class BadAround_WPForms_Report_Adapter {
	private $legacy_mapper;

	public function __construct( $legacy_mapper = null ) {
		$this->legacy_mapper = $legacy_mapper ?: new BadAround_WPForms_Field_Mapper();
	}

	public function to_canonical( $fields, $entry, $form_data, $entry_id ) {
		$fields    = is_array( $fields ) ? $fields : array();
		$entry     = is_array( $entry ) ? $entry : array();
		$form_data = is_array( $form_data ) ? $form_data : array();
		$entry_id  = absint( $entry_id );
		$form_id   = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$legacy    = $this->legacy_mapper->normalize( $fields, $entry, $form_data );
		$choice    = isset( $legacy['choice_ids'] ) ? $legacy['choice_ids'] : array();
		$public    = isset( $legacy['public_candidates'] ) ? $legacy['public_candidates'] : array();
		$private   = isset( $legacy['private_fields'] ) ? $legacy['private_fields'] : array();

		$category_choice = $this->first_choice( $choice, 2 );
		$category        = $this->map_choice( $category_choice, array(
			4 => 'vehicle',
			5 => 'public_space',
			6 => 'property',
			7 => 'animal',
			8 => 'item_document',
			9 => 'hazard',
		) );

		$subtype_field = array(
			'vehicle'       => 3,
			'public_space'  => 4,
			'property'      => 5,
			'animal'        => 6,
			'item_document' => 7,
			'hazard'        => 8,
		);
		$subtype_maps = array(
			3 => array(
				4 => 'vehicle_stolen',
				5 => 'vehicle_parked_damage',
				6 => 'vehicle_hit_and_run',
				7 => 'vehicle_parts_stolen',
				8 => 'vehicle_observed',
				9 => 'vehicle_other',
			),
			4 => array(
				17 => 'civic_waste',
				18 => 'civic_surface_damage',
				19 => 'civic_public_lighting',
				20 => 'civic_road_signage',
				21 => 'civic_public_furniture_green',
				22 => 'civic_other',
			),
			5 => array(
				34 => 'property_theft',
				35 => 'property_attempted_breakin',
				36 => 'property_intrusion',
				37 => 'property_vandalism',
				38 => 'property_observed_behavior',
				39 => 'property_other',
			),
			6 => array(
				46 => 'animal_missing',
				47 => 'animal_sighted_or_found',
				48 => 'animal_injured',
				49 => 'animal_risk',
			),
			7 => array(
				55 => 'wallet_docs',
				56 => 'keys',
				57 => 'electronics',
				58 => 'valuables',
				59 => 'other',
			),
			8 => array(
				65 => 'hazard_falling_element',
				66 => 'hazard_road_disruption',
				67 => 'hazard_flood_or_ice',
				68 => 'hazard_road_obstruction',
				69 => 'hazard_fire_or_smoke',
				70 => 'hazard_other',
			),
		);
		$current_subtype_field = isset( $subtype_field[ $category ] ) ? $subtype_field[ $category ] : 0;
		$subtype = $current_subtype_field && isset( $subtype_maps[ $current_subtype_field ] )
			? $this->map_choice( $this->first_choice( $choice, $current_subtype_field ), $subtype_maps[ $current_subtype_field ] )
			: '';

		$reporter = isset( $legacy['reporter'] ) ? $legacy['reporter'] : array();
		$location = isset( $legacy['location'] ) ? $legacy['location'] : array();
		$vehicle  = isset( $legacy['vehicle'] ) ? $legacy['vehicle'] : array();

		return array(
			'schema_version' => BadAround_Report_Schema::VERSION,
			'submission_id'  => $this->wpforms_submission_id( $form_id, $entry_id ),
			'event' => array(
				'category'   => $category,
				'subtype'    => $subtype,
				'other_type' => $this->value( $public, $private, 10 ),
			),
			'reporter' => array(
				'relationship'        => $this->map_choice( $this->first_choice( $choice, 11 ), array(
					85 => 'directly_involved',
					86 => 'witness',
					87 => 'on_behalf',
					88 => 'found_subject',
					89 => 'has_information',
					90 => 'territory_observer',
				) ),
				'first_name'           => isset( $reporter['first_name'] ) ? $reporter['first_name'] : '',
				'last_name'            => isset( $reporter['last_name'] ) ? $reporter['last_name'] : '',
				'email'                => isset( $reporter['email'] ) ? $reporter['email'] : '',
				'phone'                => isset( $reporter['phone'] ) ? $reporter['phone'] : '',
				'public_identity_mode' => $this->map_choice( $this->first_choice( $choice, 76 ), array(
					4 => 'name_initial',
					5 => 'first_name',
					6 => 'pseudonym',
					7 => 'anonymous',
				) ),
				'pseudonym'            => $this->value( $public, $private, 77 ),
				'contact_preference'   => $this->map_choice( $this->first_choice( $choice, 78 ), array(
					8  => 'community',
					9  => 'badaround_only',
					10 => 'none',
				) ),
			),
			'location' => array(
				'exact_address'     => isset( $location['exact_address'] ) ? $location['exact_address'] : '',
				'exact_lat'         => isset( $location['exact_lat'] ) ? $location['exact_lat'] : null,
				'exact_lng'         => isset( $location['exact_lng'] ) ? $location['exact_lng'] : null,
				'place_id'          => isset( $location['external_place_id'] ) ? $location['external_place_id'] : '',
				'area_label'        => isset( $location['area_label'] ) ? $location['area_label'] : '',
				'public_precision'  => $this->map_choice( $this->first_choice( $choice, 32 ), array(
					4 => 'point',
					5 => 'street',
					6 => 'area',
					7 => 'municipality',
				) ),
				'place_type'        => $this->map_choice( $this->first_choice( $choice, 33 ), array(
					8  => 'road_sidewalk',
					9  => 'public_parking',
					10 => 'private_parking',
					11 => 'private_home',
					12 => 'condominium',
					13 => 'commercial_premises',
					14 => 'office',
					15 => 'warehouse',
					16 => 'garage_outbuilding',
					17 => 'park_green',
					18 => 'public_transport',
					19 => 'public_building',
					20 => 'industrial_work_area',
					21 => 'rural_natural',
					22 => 'other',
				) ),
				'immediate_danger'  => $this->map_choice( $this->first_choice( $choice, 34 ), array(
					19 => 'yes',
					20 => 'no',
					21 => 'unsure',
				) ),
			),
			'time' => array(
				'mode' => $this->map_choice( $this->first_choice( $choice, 16 ), array(
					4 => 'exact',
					5 => 'approximate',
					6 => 'ongoing',
					7 => 'repeated',
					8 => 'unknown',
				) ),
				'date' => $this->value( $public, $private, 17 ),
				'knowledge' => $this->map_choice( $this->first_choice( $choice, 18 ), array(
					4 => 'exact',
					5 => 'range',
					6 => 'unknown',
				) ),
				'exact_time'         => $this->value( $public, $private, 19 ),
				'range_start'        => $this->value( $public, $private, 20 ),
				'range_end'          => $this->value( $public, $private, 22 ),
				'approximate_period' => $this->map_choice( $this->first_choice( $choice, 23 ), array(
					4  => 'today',
					5  => 'yesterday',
					6  => 'last_7_days',
					7  => 'last_30_days',
					8  => 'one_to_three_months',
					9  => 'over_three_months',
					10 => 'unknown',
				) ),
				'duration' => $this->map_choice( $this->first_choice( $choice, 24 ), array(
					4 => 'today',
					5 => 'days',
					6 => 'weeks',
					7 => 'one_to_three_months',
					8 => 'over_three_months',
					9 => 'unknown',
				) ),
				'frequency' => $this->map_choice( $this->first_choice( $choice, 25 ), array(
					9  => 'unknown',
					10 => 'daily',
					11 => 'several_times_week',
					12 => 'weekly',
					13 => 'several_times_month',
					14 => 'occasional',
					15 => 'indeterminate',
				) ),
			),
			'vehicle' => array(
				'role' => $this->map_choice( $this->first_choice( $choice, 37 ), array(
					4 => 'involved',
					5 => 'witness',
				) ),
				'type' => $this->map_choice( $this->first_choice( $choice, 38 ), array(
					9  => 'car',
					10 => 'motorcycle',
					11 => 'scooter',
					12 => 'van',
					13 => 'truck',
					14 => 'camper',
					15 => 'bus',
					16 => 'bicycle',
					17 => 'scooter_device',
					18 => 'agricultural_work',
					19 => 'other',
				) ),
				'make' => isset( $vehicle['make'] ) ? $vehicle['make'] : '',
				'model' => isset( $vehicle['model'] ) ? $vehicle['model'] : '',
				'color' => $this->map_choice( $this->first_choice( $choice, 41 ), array(
					5 => 'white', 6 => 'black', 7 => 'gray', 8 => 'silver', 9 => 'blue', 10 => 'light_blue',
					11 => 'red', 12 => 'green', 13 => 'yellow', 14 => 'orange', 15 => 'brown', 16 => 'beige',
					17 => 'purple', 18 => 'pink', 19 => 'multicolor', 20 => 'other', 21 => 'unknown',
				) ),
				'plate_knowledge' => $this->map_choice( $this->first_choice( $choice, 42 ), array(
					20 => 'full',
					21 => 'partial',
					22 => 'unknown',
					23 => 'none',
				) ),
				'plate_raw' => isset( $vehicle['plate_raw'] ) ? $vehicle['plate_raw'] : '',
				'distinctive_features' => $this->value( $public, $private, 44 ),
			),
			'animal' => array(
				'type' => $this->map_choice( $this->first_choice( $choice, 45 ), array(
					24 => 'dog',
					25 => 'cat',
					26 => 'bird',
					27 => 'rabbit',
					28 => 'livestock',
					29 => 'wild',
					30 => 'reptile',
					31 => 'other',
					32 => 'unknown',
				) ),
				'breed'      => $this->value( $public, $private, 46 ),
				'name'       => $this->value( $public, $private, 47 ),
				'appearance' => $this->value( $public, $private, 48 ),
			),
			'object' => array(
				'description'               => $this->value( $public, $private, 49 ),
				'owner_verification_detail' => $this->value( $public, $private, 50 ),
				'status'                    => $this->map_choice( $this->first_choice( $choice, 51 ), array( 33 => 'lost', 34 => 'found' ) ),
			),
			'property' => array(
				'tampered_entry_point'   => $this->raw_custom_value( $fields, 87 ),
				'stolen_item_categories' => $this->raw_custom_values( $fields, 88 ),
				'access_method'          => $this->raw_custom_value( $fields, 89 ),
				'alarm_status'           => $this->map_choice( $this->first_choice( $choice, 91 ), array(
					1 => 'yes_triggered',
					2 => 'yes_not_triggered',
					3 => 'yes_unknown',
					4 => 'no',
					5 => 'unknown',
					6 => 'prefer_not',
				) ),
			),
			'content' => array(
				'description' => $this->value( $public, $private, 55 ),
			),
			'damage' => array(
				'status' => $this->map_choice( $this->first_choice( $choice, 56 ), array(
					4 => 'yes',
					5 => 'no',
					6 => 'unverified',
					7 => 'not_applicable',
				) ),
				'description' => $this->value( $public, $private, 57 ),
			),
			'witness' => array(
				'status' => $this->map_choice( $this->first_choice( $choice, 59 ), array(
					8  => 'contacts_available',
					9  => 'known_no_contacts',
					10 => 'seeking',
					11 => 'none',
					12 => 'unknown',
				) ),
			),
			'authority' => array(
				'status' => $this->map_choice( $this->first_choice( $choice, 60 ), array(
					13 => 'yes',
					14 => 'no',
					15 => 'not_yet',
					16 => 'not_applicable',
					17 => 'prefer_not',
				) ),
				'type' => $this->map_choice( $this->first_choice( $choice, 61 ), array(
					5  => 'police',
					6  => 'carabinieri',
					7  => 'local_police',
					8  => 'fire_service',
					9  => 'municipality',
					10 => 'public_service',
					11 => 'veterinary_association',
					12 => 'insurance',
					13 => 'other',
				) ),
				'reference' => $this->value( $public, $private, 62 ),
			),
			'media' => array(
				'availability' => $this->map_choice( $this->first_choice( $choice, 64 ), array(
					13 => 'yes',
					14 => 'no',
					17 => 'possible',
					18 => 'surveillance',
				) ),
				'items' => array(),
			),
			'reward' => array(
				'status' => $this->map_choice( $this->first_choice( $choice, 65 ), array(
					18 => 'none',
					19 => 'fixed',
					20 => 'negotiable',
				) ),
				'amount'     => $this->value( $public, $private, 66 ),
				'conditions' => $this->value( $public, $private, 68 ),
				'expires_on' => $this->value( $public, $private, 69 ),
				'confirmed'  => $this->has_value( $public, $private, 70 ),
			),
			'consents' => array(
				'truthfulness'      => $this->has_value( $public, $private, 81 ),
				'media_rights'      => $this->has_value( $public, $private, 82 ),
				'publication_rules' => $this->has_value( $public, $private, 83 ),
				'terms'             => $this->has_value( $public, $private, 84 ),
				'privacy'           => $this->has_value( $public, $private, 85 ),
				'version'           => BadAround_Report_Schema::VERSION,
			),
		);
	}

	public function legacy_payload( $fields, $entry, $form_data ) {
		return $this->legacy_mapper->normalize(
			is_array( $fields ) ? $fields : array(),
			is_array( $entry ) ? $entry : array(),
			is_array( $form_data ) ? $form_data : array()
		);
	}

	public function wpforms_submission_id( $form_id, $entry_id ) {
		$hash = hash( 'sha1', sprintf( 'badaround:wpforms:%d:%d', absint( $form_id ), absint( $entry_id ) ) );
		$hex  = substr( $hash, 0, 32 );
		$hex  = substr_replace( $hex, '5', 12, 1 );
		$variant = dechex( ( hexdec( $hex[16] ) & 0x3 ) | 0x8 );
		$hex  = substr_replace( $hex, $variant, 16, 1 );
		return sprintf(
			'%s-%s-%s-%s-%s',
			substr( $hex, 0, 8 ),
			substr( $hex, 8, 4 ),
			substr( $hex, 12, 4 ),
			substr( $hex, 16, 4 ),
			substr( $hex, 20, 12 )
		);
	}

	private function first_choice( $choice_ids, $field_id ) {
		return ! empty( $choice_ids[ $field_id ][0] ) ? absint( $choice_ids[ $field_id ][0] ) : 0;
	}

	private function map_choice( $choice_id, $map ) {
		$choice_id = absint( $choice_id );
		return isset( $map[ $choice_id ] ) ? $map[ $choice_id ] : '';
	}

	private function value( $public, $private, $field_id ) {
		if ( array_key_exists( $field_id, $private ) ) {
			return $private[ $field_id ];
		}
		return array_key_exists( $field_id, $public ) ? $public[ $field_id ] : '';
	}

	private function has_value( $public, $private, $field_id ) {
		$value = $this->value( $public, $private, $field_id );
		return is_array( $value ) ? ! empty( $value ) : '' !== trim( (string) $value );
	}

	private function raw_custom_value( $fields, $field_id ) {
		if ( empty( $fields[ $field_id ] ) || ! is_array( $fields[ $field_id ] ) ) {
			return '';
		}
		$field = $fields[ $field_id ];
		$value = array_key_exists( 'value_raw', $field ) && '' !== $field['value_raw']
			? $field['value_raw']
			: ( isset( $field['value'] ) ? $field['value'] : '' );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		return is_scalar( $value ) ? sanitize_key( (string) $value ) : '';
	}

	private function raw_custom_values( $fields, $field_id ) {
		if ( empty( $fields[ $field_id ] ) || ! is_array( $fields[ $field_id ] ) ) {
			return array();
		}
		$field = $fields[ $field_id ];
		$value = array_key_exists( 'value_raw', $field ) && '' !== $field['value_raw']
			? $field['value_raw']
			: ( isset( $field['value'] ) ? $field['value'] : array() );
		$values = is_array( $value ) ? $value : preg_split( '/[\r\n,]+/', (string) $value );
		return array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $item ) {
							return is_scalar( $item ) ? sanitize_key( (string) $item ) : '';
						},
						$values
					)
				)
			)
		);
	}
}
