<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical, transport-independent report contract.
 *
 * This schema is the semantic source of truth for the Native Report Engine.
 * Presentation labels and WPForms field/choice IDs are deliberately excluded.
 */
class BadAround_Report_Schema {
	const VERSION = 'badaround-report/v1';

	const PRIVACY_PUBLIC          = 'public';
	const PRIVACY_PRIVATE         = 'private';
	const PRIVACY_QUARANTINE      = 'quarantine';
	const PRIVACY_MODERATION_ONLY = 'moderation-only';
	const PRIVACY_DERIVED_PUBLIC  = 'derived-public';

	public static function definition() {
		return array(
			'version' => self::VERSION,
			'category_subtypes' => array(
				'vehicle' => array(
					'vehicle_stolen',
					'vehicle_parked_damage',
					'vehicle_hit_and_run',
					'vehicle_parts_stolen',
					'vehicle_observed',
					'vehicle_other',
				),
				'public_space' => array(
					'civic_waste',
					'civic_surface_damage',
					'civic_public_lighting',
					'civic_road_signage',
					'civic_public_furniture_green',
					'civic_other',
				),
				'property' => array(
					'property_theft',
					'property_attempted_breakin',
					'property_intrusion',
					'property_vandalism',
					'property_observed_behavior',
					'property_other',
				),
				'animal' => array(
					'animal_missing',
					'animal_sighted_or_found',
					'animal_injured',
					'animal_risk',
				),
				'item_document' => array(
					'wallet_docs',
					'keys',
					'electronics',
					'valuables',
					'other',
				),
				'hazard' => array(
					'hazard_falling_element',
					'hazard_road_disruption',
					'hazard_flood_or_ice',
					'hazard_road_obstruction',
					'hazard_fire_or_smoke',
					'hazard_other',
				),
			),
			'fields' => self::fields(),
		);
	}

	public static function fields() {
		return array(
			'schema_version' => self::field( 'string', true, self::PRIVACY_PRIVATE, 'report-envelope', 'none', array( 'enum' => array( self::VERSION ) ) ),
			'submission_id' => self::field( 'uuid', true, self::PRIVACY_PRIVATE, 'source-identity', 'none' ),

			'event.category' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_tipo_evento',
				'direct-after-moderation',
				array( 'enum' => array( 'vehicle', 'public_space', 'property', 'animal', 'item_document', 'hazard' ) )
			),
			'event.subtype' => self::field( 'enum', true, self::PRIVACY_PUBLIC, 'ba_tipo_evento', 'direct-after-moderation' ),
			'event.other_type' => self::field(
				'string',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated-text',
				array( 'max_length' => 160 ),
				array(
					array( array( 'path' => 'event.subtype', 'op' => 'in', 'value' => array( 'vehicle_other', 'civic_other', 'property_other', 'other', 'hazard_other' ) ) ),
				)
			),
			'reporter.relationship' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'none',
				array(
					'enum' => array( 'directly_involved', 'witness', 'on_behalf', 'found_subject', 'has_information', 'territory_observer' ),
				)
			),

			'location.exact_address' => self::field( 'string', true, self::PRIVACY_PRIVATE, 'ba_reports.exact_address', 'never', array( 'max_length' => 500 ) ),
			'location.exact_lat' => self::field( 'decimal', false, self::PRIVACY_PRIVATE, 'ba_reports.exact_lat', 'never', array( 'min' => -90, 'max' => 90 ) ),
			'location.exact_lng' => self::field( 'decimal', false, self::PRIVACY_PRIVATE, 'ba_reports.exact_lng', 'never', array( 'min' => -180, 'max' => 180 ) ),
			'location.place_id' => self::field( 'string', false, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'never', array( 'max_length' => 255 ) ),
			'location.area_label' => self::field( 'string', false, self::PRIVACY_PUBLIC, '_ba_public_place_name', 'direct-after-moderation', array( 'max_length' => 191 ) ),
			'location.public_precision' => self::field(
				'enum',
				true,
				self::PRIVACY_DERIVED_PUBLIC,
				'_ba_public_location_precision',
				'drives-public-geo-derivation',
				array( 'enum' => array( 'point', 'street', 'area', 'municipality' ) )
			),
			'location.place_type' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_reports.content_original',
				'direct-after-moderation',
				array(
					'enum' => array(
						'road_sidewalk',
						'public_parking',
						'private_parking',
						'private_home',
						'condominium',
						'commercial_premises',
						'office',
						'warehouse',
						'garage_outbuilding',
						'park_green',
						'public_transport',
						'public_building',
						'industrial_work_area',
						'rural_natural',
						'other',
					),
				)
			),
			'location.immediate_danger' => self::field(
				'enum',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderation-only',
				array( 'enum' => array( 'yes', 'no', 'unsure' ) )
			),

			'time.mode' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_time_precision',
				'direct-after-moderation',
				array( 'enum' => array( 'exact', 'approximate', 'ongoing', 'repeated', 'unknown' ) )
			),
			'time.date' => self::field(
				'date',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_occurred_date',
				'direct-after-moderation',
				array(),
				array( array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'exact' ) ) )
			),
			'time.knowledge' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'none',
				array( 'enum' => array( 'exact', 'range', 'unknown' ) ),
				array( array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'exact' ) ) )
			),
			'time.exact_time' => self::field(
				'time',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_occurred_time',
				'direct-after-moderation',
				array(),
				array( array( array( 'path' => 'time.knowledge', 'op' => 'eq', 'value' => 'exact' ) ) )
			),
			'time.range_start' => self::field(
				'time',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated',
				array(),
				array( array( array( 'path' => 'time.knowledge', 'op' => 'eq', 'value' => 'range' ) ) )
			),
			'time.range_end' => self::field(
				'time',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated',
				array(),
				array( array( array( 'path' => 'time.knowledge', 'op' => 'eq', 'value' => 'range' ) ) )
			),
			'time.approximate_period' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_reports.content_original',
				'direct-after-moderation',
				array( 'enum' => array( 'today', 'yesterday', 'last_7_days', 'last_30_days', 'one_to_three_months', 'over_three_months', 'unknown' ) ),
				array( array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'approximate' ) ) )
			),
			'time.duration' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_reports.content_original',
				'direct-after-moderation',
				array( 'enum' => array( 'today', 'days', 'weeks', 'one_to_three_months', 'over_three_months', 'unknown' ) ),
				array(
					array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'ongoing' ) ),
					array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'repeated' ) ),
				)
			),
			'time.frequency' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_reports.content_original',
				'direct-after-moderation',
				array( 'enum' => array( 'unknown', 'daily', 'several_times_week', 'weekly', 'several_times_month', 'occasional', 'indeterminate' ) ),
				array( array( array( 'path' => 'time.mode', 'op' => 'eq', 'value' => 'repeated' ) ) )
			),

			'vehicle.role' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'none',
				array( 'enum' => array( 'involved', 'witness' ) ),
				array( array( array( 'path' => 'event.subtype', 'op' => 'eq', 'value' => 'vehicle_hit_and_run' ) ) )
			),
			'vehicle.type' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_vehicle_type',
				'direct-after-moderation',
				array( 'enum' => array( 'car', 'motorcycle', 'scooter', 'van', 'truck', 'camper', 'bus', 'bicycle', 'scooter_device', 'agricultural_work', 'other' ) ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),
			'vehicle.make' => self::field(
				'string',
				false,
				self::PRIVACY_PUBLIC,
				'_ba_vehicle_make',
				'moderated-free-text',
				array( 'max_length' => 100 ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),
			'vehicle.model' => self::field(
				'string',
				false,
				self::PRIVACY_PUBLIC,
				'_ba_vehicle_model',
				'moderated-free-text',
				array( 'max_length' => 100 ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),
			'vehicle.color' => self::field(
				'enum',
				false,
				self::PRIVACY_PUBLIC,
				'_ba_vehicle_color',
				'direct-after-moderation',
				array( 'enum' => array( 'white', 'black', 'gray', 'silver', 'blue', 'light_blue', 'red', 'green', 'yellow', 'orange', 'brown', 'beige', 'purple', 'pink', 'multicolor', 'other', 'unknown' ) ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),
			'vehicle.plate_knowledge' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'none',
				array( 'enum' => array( 'full', 'partial', 'unknown', 'none' ) ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),
			'vehicle.plate_raw' => self::field(
				'plate',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.full_plate',
				'masked-derived-only',
				array( 'max_length' => 32 ),
				array(
					array( array( 'path' => 'vehicle.plate_knowledge', 'op' => 'in', 'value' => array( 'full', 'partial' ) ) ),
				)
			),
			'vehicle.distinctive_features' => self::field(
				'string',
				false,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated-text',
				array( 'max_length' => 1000 ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'vehicle' ) ) )
			),

			'animal.type' => self::field(
				'enum',
				true,
				self::PRIVACY_PUBLIC,
				'ba_reports.content_original',
				'direct-after-moderation',
				array( 'enum' => array( 'dog', 'cat', 'bird', 'rabbit', 'livestock', 'wild', 'reptile', 'other', 'unknown' ) ),
				array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'animal' ) ) )
			),
			'animal.breed' => self::field( 'string', false, self::PRIVACY_MODERATION_ONLY, 'ba_reports.content_original', 'moderated-text', array( 'max_length' => 120 ), array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'animal' ) ) ) ),
			'animal.name' => self::field( 'string', false, self::PRIVACY_MODERATION_ONLY, 'ba_reports.content_original', 'moderated-text', array( 'max_length' => 120 ), array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'animal' ) ) ) ),
			'animal.appearance' => self::field( 'string', false, self::PRIVACY_MODERATION_ONLY, 'ba_reports.content_original', 'moderated-text', array( 'max_length' => 500 ), array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'animal' ) ) ) ),

			'object.description' => self::field( 'string', true, self::PRIVACY_MODERATION_ONLY, 'ba_reports.content_original', 'moderated-text', array( 'max_length' => 500 ), array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'item_document' ) ) ) ),
			'object.status' => self::field( 'enum', true, self::PRIVACY_PUBLIC, 'ba_reports.content_original', 'direct-after-moderation', array( 'enum' => array( 'lost', 'found' ) ), array( array( array( 'path' => 'event.category', 'op' => 'eq', 'value' => 'item_document' ) ) ) ),
			'object.owner_verification_detail' => self::field(
				'string',
				false,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'max_length' => 500 ),
				array( array( array( 'path' => 'object.status', 'op' => 'eq', 'value' => 'found' ) ) )
			),

			'property.tampered_entry_point' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'enum' => array( 'door', 'lock', 'window', 'shutter', 'gate', 'garage_door', 'shop_window', 'other' ) ),
				array( array( array( 'path' => 'event.subtype', 'op' => 'eq', 'value' => 'property_attempted_breakin' ) ) )
			),
			'property.stolen_item_categories' => self::field(
				'array',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated',
				array( 'max_items' => 8, 'item_enum' => array( 'cash', 'jewelry', 'electronics', 'documents', 'keys', 'vehicle', 'tools_merchandise', 'other' ) ),
				array( array( array( 'path' => 'event.subtype', 'op' => 'eq', 'value' => 'property_theft' ) ) )
			),
			'property.access_method' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'enum' => array( 'door_forced', 'window_balcony', 'lock_tampered', 'garage_access', 'no_evident_signs', 'unknown', 'other' ) ),
				array( array( array( 'path' => 'event.subtype', 'op' => 'eq', 'value' => 'property_theft' ) ) )
			),
			'property.alarm_status' => self::field(
				'enum',
				false,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'enum' => array( 'yes_triggered', 'yes_not_triggered', 'yes_unknown', 'no', 'unknown', 'prefer_not' ) ),
				array(
					array( array( 'path' => 'event.subtype', 'op' => 'in', 'value' => array( 'property_theft', 'property_attempted_breakin', 'property_intrusion' ) ) ),
				)
			),

			'content.description' => self::field( 'string', true, self::PRIVACY_MODERATION_ONLY, 'ba_reports.content_original', 'moderated-text', array( 'min_length' => 10, 'max_length' => 5000 ) ),
			'damage.status' => self::field( 'enum', true, self::PRIVACY_PUBLIC, 'ba_reports.content_original', 'direct-after-moderation', array( 'enum' => array( 'yes', 'no', 'unverified', 'not_applicable' ) ) ),
			'damage.description' => self::field(
				'string',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated-text',
				array( 'max_length' => 2000 ),
				array( array( array( 'path' => 'damage.status', 'op' => 'eq', 'value' => 'yes' ) ) )
			),
			'witness.status' => self::field(
				'enum',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated',
				array( 'enum' => array( 'contacts_available', 'known_no_contacts', 'seeking', 'none', 'unknown' ) ),
				array(
					array( array( 'path' => 'event.subtype', 'op' => 'in', 'value' => array(
						'vehicle_stolen',
						'vehicle_parked_damage',
						'vehicle_hit_and_run',
						'vehicle_parts_stolen',
						'property_theft',
						'property_attempted_breakin',
						'property_intrusion',
						'property_vandalism',
						'property_observed_behavior',
					) ) ),
				)
			),
			'authority.status' => self::field( 'enum', true, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'never', array( 'enum' => array( 'yes', 'no', 'not_yet', 'not_applicable', 'prefer_not' ) ) ),
			'authority.type' => self::field(
				'enum',
				false,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'enum' => array( 'police', 'carabinieri', 'local_police', 'fire_service', 'municipality', 'public_service', 'veterinary_association', 'insurance', 'other' ) ),
				array( array( array( 'path' => 'authority.status', 'op' => 'eq', 'value' => 'yes' ) ) )
			),
			'authority.reference' => self::field(
				'string',
				false,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'never',
				array( 'max_length' => 191 ),
				array( array( array( 'path' => 'authority.status', 'op' => 'eq', 'value' => 'yes' ) ) )
			),

			'media.availability' => self::field( 'enum', false, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'none', array( 'enum' => array( 'yes', 'no', 'possible', 'surveillance' ) ) ),
			'media.items' => self::field( 'array', false, self::PRIVACY_QUARANTINE, 'ba_report_media', 'approved-derivative-only', array( 'max_items' => 5 ) ),

			'reward.status' => self::field( 'enum', true, self::PRIVACY_PUBLIC, 'ba_reports.content_original', 'direct-after-moderation', array( 'enum' => array( 'none', 'fixed', 'negotiable' ) ) ),
			'reward.amount' => self::field(
				'number',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_reward_amount',
				'direct-after-moderation',
				array( 'min' => 0.01, 'max' => 1000000 ),
				array( array( array( 'path' => 'reward.status', 'op' => 'eq', 'value' => 'fixed' ) ) )
			),
			'reward.conditions' => self::field(
				'string',
				true,
				self::PRIVACY_MODERATION_ONLY,
				'ba_reports.content_original',
				'moderated-text',
				array( 'max_length' => 2000 ),
				array( array( array( 'path' => 'reward.status', 'op' => 'in', 'value' => array( 'fixed', 'negotiable' ) ) ) )
			),
			'reward.expires_on' => self::field(
				'date',
				true,
				self::PRIVACY_PUBLIC,
				'_ba_expires_at',
				'direct-after-moderation',
				array(),
				array( array( array( 'path' => 'reward.status', 'op' => 'in', 'value' => array( 'fixed', 'negotiable' ) ) ) )
			),
			'reward.confirmed' => self::field(
				'bool',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.content_original',
				'none',
				array(),
				array( array( array( 'path' => 'reward.status', 'op' => 'in', 'value' => array( 'fixed', 'negotiable' ) ) ) )
			),

			'reporter.first_name' => self::field( 'string', true, self::PRIVACY_PRIVATE, 'ba_reports.author_name', 'never', array( 'max_length' => 191 ) ),
			'reporter.last_name' => self::field( 'string', true, self::PRIVACY_PRIVATE, 'ba_reports.author_surname', 'never', array( 'max_length' => 191 ) ),
			'reporter.email' => self::field( 'email', true, self::PRIVACY_PRIVATE, 'ba_reports.author_email', 'never', array( 'max_length' => 191 ) ),
			'reporter.phone' => self::field( 'phone', false, self::PRIVACY_PRIVATE, 'ba_reports.author_phone', 'never', array( 'max_length' => 64 ) ),
			'reporter.public_identity_mode' => self::field(
				'enum',
				true,
				self::PRIVACY_DERIVED_PUBLIC,
				'ba_reports.content_original',
				'derived-display-name',
				array( 'enum' => array( 'name_initial', 'first_name', 'pseudonym', 'anonymous' ) )
			),
			'reporter.pseudonym' => self::field(
				'string',
				true,
				self::PRIVACY_DERIVED_PUBLIC,
				'ba_reports.content_original',
				'derived-display-name',
				array( 'max_length' => 80 ),
				array( array( array( 'path' => 'reporter.public_identity_mode', 'op' => 'eq', 'value' => 'pseudonym' ) ) )
			),
			'reporter.contact_preference' => self::field(
				'enum',
				true,
				self::PRIVACY_PRIVATE,
				'ba_reports.contact_preference',
				'never',
				array( 'enum' => array( 'community', 'badaround_only', 'none' ) )
			),

			'consents.truthfulness' => self::field( 'bool', true, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'never' ),
			'consents.media_rights' => self::field( 'bool', true, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'never' ),
			'consents.publication_rules' => self::field( 'bool', true, self::PRIVACY_PRIVATE, 'ba_reports.consent_publication', 'never' ),
			'consents.terms' => self::field( 'bool', true, self::PRIVACY_PRIVATE, 'ba_reports.content_original', 'never' ),
			'consents.privacy' => self::field( 'bool', true, self::PRIVACY_PRIVATE, 'ba_reports.consent_privacy', 'never' ),
			'consents.version' => self::field( 'string', true, self::PRIVACY_PRIVATE, 'ba_reports.consent_version', 'never', array( 'max_length' => 64 ) ),
		);
	}

	public static function field_definition( $path ) {
		$fields = self::fields();
		return isset( $fields[ $path ] ) ? $fields[ $path ] : null;
	}

	public static function category_subtypes() {
		$definition = self::definition();
		return $definition['category_subtypes'];
	}

	private static function field( $type, $required, $privacy, $destination, $public_projection, $constraints = array(), $conditions = array() ) {
		return array(
			'type'              => $type,
			'required'          => (bool) $required,
			'conditions'        => $conditions,
			'privacy'           => $privacy,
			'destination'       => $destination,
			'public_projection' => $public_projection,
			'constraints'       => $constraints,
		);
	}
}
