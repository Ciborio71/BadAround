<?php
/**
 * RADAR-LIVE-02 — inert private orchestration bridge.
 * Not included by the plugin bootstrap, no hooks, REST, cron or public UI.
 * Caller must supply canonical Discovery instance and explicit local civil date.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Offline_Bridge {
    public static function snapshot($discovery, DateTimeImmutable $today, $days=7) {
        if (!class_exists('BadAround_Radar_Canonical_Adapter')
            || !class_exists('BadAround_Intelligence_Trends')
            || !class_exists('BadAround_Intelligence_Peak_Candidates')
            || !class_exists('BadAround_Intelligence_Data_Quality')
            || !class_exists('BadAround_Radar_View_Model')) {
            throw new RuntimeException('Radar components not loaded');
        }
        $rows=BadAround_Radar_Canonical_Adapter::rows($discovery);
        $trends=BadAround_Intelligence_Trends::summarize($rows,$today,$days);
        $candidates=BadAround_Intelligence_Peak_Candidates::screen($trends);
        $quality=BadAround_Intelligence_Data_Quality::assess($trends,$candidates);
        $view=BadAround_Radar_View_Model::build($trends,$quality);
        if (($view['internal_preview_only']??null)!==true
            || ($view['public_alerts']??null)!==array()) {
            throw new RuntimeException('Private preview invariant failed');
        }
        return array('view'=>$view,'quality'=>$quality,
            'candidate_review_count'=>$candidates['candidate_count'],
            'public_exposure_enabled'=>false);
    }
}
