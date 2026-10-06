<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only WPForms #6 -> canonical contract coverage matrix.
 *
 * This class is documentation/compatibility metadata only. It is not a source
 * of truth for the canonical report schema and is never consulted by it.
 */
class BadAround_WPForms_Contract_Map {
	const FORM_ID = 6;
	const CANONICAL = 'CANONICAL';
	const PRESENTATION_ONLY = 'PRESENTATION_ONLY';
	const LEGACY_ONLY = 'LEGACY_ONLY';
	const DERIVED = 'DERIVED';
	const INTERNAL = 'INTERNAL';

	public static function fields() {
		return array(
			13=>self::p(),1=>self::p(),
			2=>self::c('event.category'),3=>self::c('event.subtype'),4=>self::c('event.subtype'),5=>self::c('event.subtype'),6=>self::c('event.subtype'),7=>self::c('event.subtype'),8=>self::c('event.subtype'),
			10=>self::c('event.other_type'),11=>self::c('reporter.relationship'),12=>self::p(),15=>self::p(),
			16=>self::c('time.mode'),17=>self::c('time.date'),18=>self::c('time.knowledge'),19=>self::c('time.exact_time'),20=>self::c('time.range_start'),22=>self::c('time.range_end'),23=>self::c('time.approximate_period'),24=>self::c('time.duration'),25=>self::c('time.frequency'),
			31=>self::c(array('location.exact_address','location.exact_lat','location.exact_lng','location.place_id')),29=>self::c('location.area_label'),32=>self::c('location.public_precision'),33=>self::c('location.place_type'),34=>self::c('location.immediate_danger'),
			35=>self::p(),36=>self::p(),
			37=>self::c('vehicle.role'),38=>self::c('vehicle.type'),39=>self::c('vehicle.make'),40=>self::c('vehicle.model'),41=>self::c('vehicle.color'),42=>self::c('vehicle.plate_knowledge'),43=>self::c('vehicle.plate_raw'),44=>self::c('vehicle.distinctive_features'),
			45=>self::c('animal.type'),46=>self::c('animal.breed'),47=>self::c('animal.name'),48=>self::c('animal.appearance'),
			51=>self::c('object.status'),49=>self::c('object.description'),50=>self::c('object.owner_verification_detail'),
			87=>self::c('property.tampered_entry_point'),88=>self::c('property.stolen_item_categories'),89=>self::c('property.access_method'),91=>self::c('property.alarm_status'),
			52=>self::p(),54=>self::p(),
			55=>self::c('content.description'),56=>self::c('damage.status'),57=>self::c('damage.description'),59=>self::c('witness.status'),
			60=>self::c('authority.status'),61=>self::c('authority.type'),62=>self::c('authority.reference'),63=>self::c('media.items'),64=>self::c('media.availability'),
			65=>self::c('reward.status'),66=>self::c('reward.amount'),68=>self::c('reward.conditions'),69=>self::c('reward.expires_on'),70=>self::c('reward.confirmed'),
			71=>self::p(),72=>self::p(),
			73=>self::c(array('reporter.first_name','reporter.last_name')),74=>self::c('reporter.email'),75=>self::c('reporter.phone'),76=>self::c('reporter.public_identity_mode'),77=>self::c('reporter.pseudonym'),78=>self::c('reporter.contact_preference'),
			80=>self::p(),81=>self::c('consents.truthfulness'),82=>self::c('consents.media_rights'),83=>self::c('consents.publication_rules'),84=>self::c('consents.terms'),85=>self::c('consents.privacy'),86=>self::p(),14=>self::p(),
		);
	}

	public static function counts() {
		$out=array(self::CANONICAL=>0,self::PRESENTATION_ONLY=>0,self::LEGACY_ONLY=>0,self::DERIVED=>0,self::INTERNAL=>0);
		foreach(self::fields() as $item){ $out[$item['classification']]++; }
		return $out;
	}

	private static function c($target){ return array('classification'=>self::CANONICAL,'canonical'=>$target); }
	private static function p(){ return array('classification'=>self::PRESENTATION_ONLY,'canonical'=>array()); }
}
