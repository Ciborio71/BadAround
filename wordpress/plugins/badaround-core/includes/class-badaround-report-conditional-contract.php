<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Read-only, hash-pinned NF-CONDITIONAL-CONTRACT v1.0 evaluator. Never used by v1. */
class BadAround_Report_Conditional_Contract {
	const SHA256 = 'dc282153243ffb80f3bd4f2a222ea0f4c4f42806fba6f3a0bde0a76855bd8e3c';
	private static $contract;
	private static $rows;
	public static function definition() {
		if ( null === self::$contract ) {
			$bytes = file_get_contents( dirname( __DIR__ ) . '/contracts/nf-conditional-v1.0.json' );
			if ( ! is_string( $bytes ) || ! hash_equals( self::SHA256, hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'Conditional contract integrity failed.' ); }
			self::$contract = json_decode( $bytes, true, 512, JSON_THROW_ON_ERROR );
			self::$rows = array();
			// Versioned contextual overlay; the hash-pinned v1 artifact stays unchanged.
            foreach ( self::$contract['matrix'] as &$row ) {
                $row['fields']['location.place_type']['constraints_override']['enum']=self::place_options($row['category'],$row['subtype']);
                self::$rows[$row['subtype']]=$row;
            }
            unset($row);
		}
		return self::$contract;
	}
    public static function place_options($category,$subtype) {
        $groups=array(
            'vehicle'=>array('road_sidewalk','public_parking','private_parking','garage_outbuilding','condominium','industrial_work_area'),
            'property'=>array('private_home','condominium','commercial_premises','office','warehouse','garage_outbuilding','public_building'),
            'hazard'=>array('road_sidewalk','park_green','public_parking','condominium','industrial_work_area','rural_natural'),
            'public_space'=>array('road_sidewalk','public_parking','park_green','public_transport','public_building','rural_natural'),
            'animal'=>array('road_sidewalk','park_green','rural_natural','private_home','condominium','public_parking'),
            'item_document'=>array('road_sidewalk','public_transport','commercial_premises','public_building','park_green','public_parking','office')
        );
        $specific=array(
            'vehicle_hit_and_run'=>array('road_sidewalk','public_parking','private_parking','industrial_work_area'),
            'hazard_falling_element'=>array('road_sidewalk','park_green','rural_natural','condominium','private_parking','industrial_work_area'),
            'hazard_road_disruption'=>array('road_sidewalk','public_parking','private_parking','rural_natural','industrial_work_area'),
            'hazard_road_obstruction'=>array('road_sidewalk','public_parking','private_parking','industrial_work_area'),
            'hazard_flood_or_ice'=>array('road_sidewalk','public_parking','private_parking','condominium','park_green','rural_natural'),
            'hazard_fire_or_smoke'=>array('road_sidewalk','private_home','condominium','commercial_premises','industrial_work_area','rural_natural','park_green'),
            'civic_surface_damage'=>array('road_sidewalk','public_parking','park_green','public_transport'),
            'civic_road_signage'=>array('road_sidewalk','public_parking','public_transport'),
            'civic_public_lighting'=>array('road_sidewalk','public_parking','park_green','public_building'),
            'civic_public_furniture_green'=>array('park_green','road_sidewalk','public_parking','public_building'),
        );
        return array_merge($specific[$subtype]??$groups[$category]??array(),array('other'));
    }
    public static function place_question($category,$subtype) {
        if($category==='vehicle')return $subtype==='vehicle_hit_and_run'?'In quale tipo di luogo è avvenuto l’incidente?':'Dove si trovava il veicolo?';
        if($category==='property')return 'Quale tipo di immobile o luogo è interessato?';
        if($category==='animal')return $subtype==='animal_missing'?'Dove è stato visto l’animale l’ultima volta?':'In quale tipo di luogo si trova l’animale?';
        return $category==='item_document'?'In quale tipo di luogo è stato smarrito o trovato l’oggetto?':'Quale tipo di luogo è interessato?';
    }
	public static function fields() {
		$c = self::definition();
		$out = BadAround_Report_Schema::fields();
		foreach ( $c['fields'] as $path => $field ) {
			if ( isset( $field['source_field'] ) ) {
				$out[ $path ] = array( 'type' => 'string', 'required' => false, 'nullable' => false, 'conditions' => array(), 'constraints' => $field['constraints'],
					'privacy' => 'PRIVATE ONLY' === $field['classification'] ? 'private' : 'moderation-only', 'exposure' => 'private', 'destination' => 'ba_reports.content_original', 'public_projection' => 'none' );
			}
		}
        // Optional neighborhood proposal, private until explicit public-detail review.
        $out['location.neighborhood_label']=array('type'=>'string','required'=>false,'nullable'=>false,'conditions'=>array(),'constraints'=>array('max_length'=>100,'single_line'=>true),'privacy'=>'moderation-only','exposure'=>'private','destination'=>'ba_reports.content_original','public_projection'=>'explicit-public-detail-review');
        $out['location.neighborhood_source']=array('type'=>'enum','required'=>false,'nullable'=>false,'conditions'=>array(),'constraints'=>array('enum'=>array('google_neighborhood','google_sublocality','user_proposed')),'privacy'=>'private','exposure'=>'private','destination'=>'ba_reports.content_original','public_projection'=>'none');
        $out['location.neighborhood_confirmed']=array('type'=>'bool','required'=>false,'nullable'=>false,'conditions'=>array(),'constraints'=>array(),'privacy'=>'private','exposure'=>'private','destination'=>'ba_reports.content_original','public_projection'=>'none');
		// Explicit technical overlay from NF-UX-02A; frozen v1 schema is untouched.
		$out['schema_version']['constraints']['enum'] = array( 'badaround-report/v2' );
		return $out;
	}
	public static function row( $report ) {
		self::definition();
		$subtype = self::value( $report, 'event.subtype' );
		$category = self::value( $report, 'event.category' );
		$row = is_string( $subtype ) ? ( self::$rows[ $subtype ] ?? null ) : null;
		return $row && $row['category'] === $category ? $row : null;
	}
	public static function value( $report, $path ) {
		foreach ( explode( '.', $path ) as $part ) {
			if ( ! is_array( $report ) || ! array_key_exists( $part, $report ) ) { return null; }
			$report = $report[ $part ];
		}
		return $report;
	}
	public static function put( &$out, $path, $value ) {
		$parts = explode( '.', $path ); $cursor =& $out;
		foreach ( $parts as $i => $part ) {
			if ( $i === count( $parts ) - 1 ) { $cursor[ $part ] = $value; return; }
			if ( ! isset( $cursor[ $part ] ) || ! is_array( $cursor[ $part ] ) ) { $cursor[ $part ] = array(); }
			$cursor =& $cursor[ $part ];
		}
	}
	public static function active( $path, $report, $row, $seen = array() ) {
		// NF-UX-02A technical envelope overlay: questionnaire visibility cannot
		// strip the explicit discriminator or the existing global UUID identity.
		if ( in_array( $path, array( 'schema_version', 'submission_id', 'consents.public_details_version', 'location.neighborhood_label', 'location.neighborhood_source', 'location.neighborhood_confirmed' ), true ) ) { return true; }
		if ( ! $row || isset( $seen[ $path ] ) || ! isset( $row['fields'][ $path ] ) ) { return false; }
		$seen[ $path ] = true; $rule = $row['fields'][ $path ];
		if ( 'HIDDEN' === $rule['state'] ) { return false; }
		return 'CONDITIONAL' !== $rule['state'] || self::matches( $rule['condition'], $report, $row, $seen );
	}
	private static function matches( $expr, $report, $row, $seen ) {
		if ( isset( $expr['all'] ) ) { foreach ( $expr['all'] as $item ) { if ( ! self::matches( $item, $report, $row, $seen ) ) { return false; } } return true; }
		if ( isset( $expr['any'] ) ) { foreach ( $expr['any'] as $item ) { if ( self::matches( $item, $report, $row, $seen ) ) { return true; } } return false; }
		$path = $expr['field'];
		if ( 'active' === $expr['op'] ) { return self::active( $path, $report, $row, $seen ); }
		if ( ! self::active( $path, $report, $row, $seen ) ) { return false; }
		$value = self::value( $report, $path );
		if ( null === $value ) { return false; }
		if ( 'eq' === $expr['op'] ) { return $value === $expr['value']; }
		if ( 'in' === $expr['op'] ) { return in_array( $value, $expr['values'], true ); }
		if ( 'contains' === $expr['op'] ) { return is_array( $value ) && in_array( $expr['value'], $value, true ); }
		throw new RuntimeException( 'Unsupported frozen condition.' );
	}
}
