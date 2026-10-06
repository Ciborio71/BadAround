<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves canonical BadAround event keys to materialized ba_tipo_evento terms.
 *
 * Runtime is deliberately read-only: this class never creates, updates, moves,
 * renames or merges taxonomy terms.
 */
class BadAround_Event_Taxonomy_Map {
	const CANONICAL_META = '_ba_canonical_event_key';

	private const CATEGORY_SLUGS = array(
		'vehicle'       => 'veicoli',
		'public_space'  => 'spazi-pubblici',
		'property'      => 'case-e-attivita',
		'animal'        => 'animali',
		'item_document' => 'oggetti-e-documenti',
		'hazard'        => 'pericoli',
	);

	private const SUBTYPE_SLUGS = array(
		'vehicle_stolen'               => 'veicolo-rubato',
		'vehicle_parked_damage'        => 'danno-o-incidente-durante-la-sosta',
		'vehicle_hit_and_run'          => 'incidente-con-fuga',
		'vehicle_parts_stolen'         => 'furto-di-parti-o-accessori',
		'vehicle_observed'             => 'veicolo-sospetto-o-abbandonato',
		'vehicle_other'                => 'altro-evento-su-veicoli',
		'civic_waste'                  => 'rifiuti-abbandonati-o-discarica',
		'civic_surface_damage'         => 'buca-o-marciapiede-dissestato',
		'civic_public_lighting'        => 'illuminazione-pubblica-spenta-o-guasta',
		'civic_road_signage'           => 'segnaletica-danneggiata-o-illeggibile',
		'civic_public_furniture_green' => 'arredo-urbano-verde-o-parco-degradato',
		'civic_other'                  => 'altro-disservizio-o-degrado',
		'property_theft'               => 'furto-avvenuto',
		'property_attempted_breakin'   => 'tentato-furto-o-scasso',
		'property_intrusion'           => 'accesso-abusivo-o-intrusione',
		'property_vandalism'           => 'vandalismo-o-danneggiamento',
		'property_observed_behavior'   => 'persone-o-comportamenti-sospetti',
		'property_other'               => 'altro-evento-su-immobili',
		'animal_missing'               => 'ho-smarrito-il-mio-animale',
		'animal_sighted_or_found'      => 'ho-avvistato-o-trovato-un-animale-vagante',
		'animal_injured'               => 'animale-ferito-o-in-difficolta',
		'animal_risk'                  => 'animale-o-situazione-potenzialmente-pericolosa',
		'wallet_docs'                  => 'documenti-portafoglio-o-borsa',
		'keys'                         => 'chiavi',
		'electronics'                  => 'dispositivi-elettronici',
		'valuables'                    => 'oggetto-di-valore-o-accessorio',
		'other'                        => 'altro-oggetto-personale',
		'hazard_falling_element'       => 'albero-ramo-o-elemento-pericolante',
		'hazard_road_disruption'       => 'strada-interrotta-o-dissesto-grave',
		'hazard_flood_or_ice'          => 'allagamento-o-ghiaccio-insidioso',
		'hazard_road_obstruction'      => 'ostacolo-pericoloso-sulla-carreggiata',
		'hazard_fire_or_smoke'         => 'fumo-incendio-o-situazione-anomala',
		'hazard_other'                 => 'altro-pericolo',
	);

	public function assign( $post_id, $report ) {
		$post_id  = absint( $post_id );
		$category = isset( $report['event']['category'] ) ? sanitize_key( $report['event']['category'] ) : '';
		$subtype  = isset( $report['event']['subtype'] ) ? sanitize_key( $report['event']['subtype'] ) : '';

		$category_term = $this->resolve_category( $category );
		$subtype_term  = $this->resolve_subtype( $subtype, $category_term );

		if ( ! $category_term || ! $subtype_term ) {
			return new WP_Error( 'ba_event_taxonomy_mapping_missing', __( 'Mappatura tassonomica BadAround non disponibile.', 'badaround-core' ) );
		}

		$result = wp_set_object_terms(
			$post_id,
			array( (int) $category_term->term_id, (int) $subtype_term->term_id ),
			BadAround_Event_Post_Type::EVENT_TYPE_TAX,
			false
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'category_term_id' => (int) $category_term->term_id,
			'subtype_term_id'  => (int) $subtype_term->term_id,
		);
	}

	public function resolve_category( $canonical_key ) {
		$canonical_key = sanitize_key( $canonical_key );
		if ( empty( self::CATEGORY_SLUGS[ $canonical_key ] ) ) {
			return null;
		}

		$term = $this->term_by_canonical_key( $canonical_key );
		if ( ! $term || self::CATEGORY_SLUGS[ $canonical_key ] !== $term->slug || 0 !== (int) $term->parent ) {
			return null;
		}

		return $term;
	}

	public function resolve_subtype( $canonical_key, $category_term = null ) {
		$canonical_key = sanitize_key( $canonical_key );
		if ( empty( self::SUBTYPE_SLUGS[ $canonical_key ] ) ) {
			return null;
		}

		$term = $this->term_by_canonical_key( $canonical_key );
		if ( ! $term || self::SUBTYPE_SLUGS[ $canonical_key ] !== $term->slug ) {
			return null;
		}

		$expected_category = self::category_for_subtype( $canonical_key );
		if ( ! $expected_category ) {
			return null;
		}

		$expected_parent = $category_term instanceof WP_Term ? $category_term : $this->resolve_category( $expected_category );
		if ( ! $expected_parent || (int) $term->parent !== (int) $expected_parent->term_id ) {
			return null;
		}

		return $term;
	}

	public static function category_for_subtype( $subtype_key ) {
		$subtype_key = sanitize_key( $subtype_key );
		foreach ( BadAround_Report_Schema::category_subtypes() as $category => $subtypes ) {
			if ( in_array( $subtype_key, $subtypes, true ) ) {
				return $category;
			}
		}
		return null;
	}

	public static function mapping() {
		return array(
			'categories' => self::CATEGORY_SLUGS,
			'subtypes'   => self::SUBTYPE_SLUGS,
		);
	}

	private function term_by_canonical_key( $canonical_key ) {
		$terms = get_terms(
			array(
				'taxonomy'   => BadAround_Event_Post_Type::EVENT_TYPE_TAX,
				'hide_empty' => false,
				'meta_key'   => self::CANONICAL_META,
				'meta_value' => $canonical_key,
			)
		);

		if ( is_wp_error( $terms ) || 1 !== count( $terms ) ) {
			return null;
		}

		return $terms[0] instanceof WP_Term ? $terms[0] : null;
	}
}
