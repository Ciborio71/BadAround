<?php
/**
 * Temporary INTELLIGENCE-01P runtime certification probe.
 * Never exposes private records, and requires administrator authentication.
 */
if (!defined('ABSPATH')) exit;
add_action('rest_api_init',static function(){
 register_rest_route('badaround-internal/v1','/intelligence-verify',[
  'methods'=>WP_REST_Server::READABLE,
  'permission_callback'=>static function(){return is_user_logged_in() && current_user_can('manage_options');},
  'callback'=>static function(){
   if (!class_exists('BadAround_Intelligence_Read_Model')||!class_exists('BadAround_Discovery_Query'))
    return new WP_Error('ba_intelligence_not_loaded','Not loaded',['status'=>503]);
   try {
    $r=BadAround_Intelligence_Read_Model::snapshot(new BadAround_Discovery_Query());
    return rest_ensure_response([
     'contract'=>$r['contract'],'unit'=>$r['unit'],'report_count'=>$r['report_count'],
     'occurred_date_available'=>$r['occurred_date_available'],
     'by_event_type'=>$r['by_event_type'],'by_territory_label'=>$r['by_territory_label'],
     'certified_episode_count'=>$r['certified_episode_count'],
     'transactional_snapshot'=>$r['transactional_snapshot']
    ]);
   }catch(Throwable $e){return new WP_Error('ba_snapshot_unavailable','Unavailable',['status'=>503]);}
  }
 ]);
});
