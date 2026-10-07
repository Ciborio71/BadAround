<?php
if (!defined('ABSPATH')) { exit; }

/** Temporary publication prohibition until native review and derivatives are implemented. */
class BadAround_Native_Media_Fence {
	public static function check($event,$media=0) {
		global $wpdb;$event=(int)$event;$media=(int)$media;
		$rows=$wpdb->get_results($wpdb->prepare("SELECT m.*, r.source_type, r.content_original FROM {$wpdb->prefix}ba_report_media m LEFT JOIN {$wpdb->prefix}ba_reports r ON r.id=m.report_id WHERE m.event_id=%d OR m.id=%d",$event,$media),ARRAY_A);
		if (!empty($wpdb->last_error)) { return self::blocked($event); }
		foreach((array)$rows as $row) {
			if ($row['source_type']==='native' || $row['review_status']==='pending_native_review' || strpos($row['review_status'],'native_')===0) { return self::blocked($event); }
			if (empty($row['source_type'])) { return self::blocked($event); }
		}
		$source=get_post_meta($event,'_ba_source_type',true);$report=(int)get_post_meta($event,'_ba_primary_report_id',true);
		// Ledger provenance remains authoritative if post metadata/final rows are missing.
		$native=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_native_media_items WHERE event_id=%d OR (report_id=%d AND report_id>0) OR (report_media_id=%d AND report_media_id>0)",$event,$report,$media));
		if (!empty($wpdb->last_error) || (int)$native>0) { return self::blocked($event); }
		if ($source==='native' || $report>0) {
			$row=$wpdb->get_row($wpdb->prepare("SELECT source_type, content_original FROM {$wpdb->prefix}ba_reports WHERE id=%d",$report),ARRAY_A);
			if (!empty($wpdb->last_error) || ($source==='native' && (!$row || $row['source_type']!=='native'))) { return self::blocked($event); }
			if ($row && $row['source_type']==='native') {
				$data=json_decode($row['content_original'],true);
				if (!isset($data['canonical']) || !is_array($data['canonical'])) { return self::blocked($event); }
				if (!empty($data['canonical']['media']['items'])) { return self::blocked($event); }
			}
		}
		return true;
	}
	private static function blocked($event) {
		BadAround_Native_Media_Config::audit('publication_fence_blocked',$event,'native_derivative_pipeline_required');
		return new WP_Error('ba_native_media_publication_blocked','Native media require the native review and derivative pipeline.');
	}
}
