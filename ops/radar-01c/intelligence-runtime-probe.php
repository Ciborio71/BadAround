<?php
/**
 * INTELLIGENCE-01P ephemeral, administrator-only runtime probe.
 * Must be manually included only during controlled certification and removed
 * immediately afterward. Never loads through normal production bootstrap.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Intelligence_Runtime_Probe {
 public static function register() {
  add_action('rest_api_init', static function () {
   register_rest_route('badaround-internal/v1','/intelligence-verify',[
    'methods'=>'GET',
    'permission_callback'=>static function () {
     return is_user_logged_in() && current_user_can('manage_options');
    },
    'callback'=>static function () {
     if (!class_exists('BadAround_Intelligence_Read_Model') ||
         !class_exists('BadAround_Discovery_Query')) {
      return new WP_Error('ba_intelligence_unavailable','Runtime unavailable',['status'=>503]);
     }
     try {
      $summary=BadAround_Intelligence_Read_Model::snapshot(new BadAround_Discovery_Query());
      // Whitelisted aggregate fields only; no event-level data or private geometry.
      return rest_ensure_response([
       'contract'=>$summary['contract'],
       'unit'=>$summary['unit'],
       'report_count'=>$summary['report_count'],
       'occurred_date_available'=>$summary['occurred_date_available'],
       'by_event_type'=>$summary['by_event_type'],
       'by_territory_label'=>$summary['by_territory_label'],
       'certified_episode_count'=>$summary['certified_episode_count'],
       'transactional_snapshot'=>$summary['transactional_snapshot']
      ]);
     } catch (Throwable $e) {
      return new WP_Error('ba_intelligence_snapshot_unavailable','Snapshot unavailable',['status'=>503]);
     }
    }
   ]);
  });
 }
}
