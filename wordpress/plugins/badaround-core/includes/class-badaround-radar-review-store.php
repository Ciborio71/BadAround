<?php
/**
 * RADAR-QUALITY-03 — explicit administrator-only, one-time review persistence.
 * Dormant: no hooks/routes; callers must perform nonce validation.
 * Decisions are immutable in v1; changes require a separate reviewed workflow.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_Review_Store {
    const META_KEY = '_ba_radar_editorial_review_v1';

    public static function save(array $input, $nonce) {
        if (!defined('BADAROUND_RADAR_EDITORIAL_WRITE_ENABLED')
            || BADAROUND_RADAR_EDITORIAL_WRITE_ENABLED !== true
            || !is_user_logged_in() || !current_user_can('manage_options')
            || !is_string($nonce) || !wp_verify_nonce($nonce,'ba_radar_editorial_review')) {
            return new WP_Error('ba_radar_access_denied','Not authorized');
        }
        if (!class_exists('BadAround_Radar_Editorial_Decision')) {
            return new WP_Error('ba_radar_missing_contract','Review contract unavailable');
        }
        try { $data=BadAround_Radar_Editorial_Decision::validate($input); }
        catch (Throwable $e) { return new WP_Error('ba_radar_invalid_review','Invalid review'); }
        $id=$data['event_id'];
        if ((int)$data['reviewer_id']!==get_current_user_id()
            || get_post_type($id)!=='ba_evento' || get_post_status($id)!=='publish') {
            return new WP_Error('ba_radar_invalid_target','Invalid review target');
        }
        $fingerprint=self::fingerprint($id);
        if (!hash_equals($fingerprint,$data['expected_revision'])) {
            return new WP_Error('ba_radar_stale_revision','Event changed since review');
        }
        global $wpdb;
        // Require transactional tables; no write is attempted when isolation cannot be established.
        if ($wpdb->query('START TRANSACTION') === false) {
            return new WP_Error('ba_radar_transaction_unavailable','Transaction unavailable');
        }
        $existing=get_post_meta($id,self::META_KEY,true);
        if ($existing !== '' || !hash_equals(self::fingerprint($id),$data['expected_revision'])) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('ba_radar_already_reviewed','Review already exists or event changed');
        }
        $record=array('version'=>'radar-editorial/v1','status'=>$data['status'],
            'reviewer_id'=>$data['reviewer_id'],'reviewed_at'=>$data['reviewed_at'],
            'evidence_ref'=>$data['evidence_ref'],'reason'=>$data['reason'],
            'expected_revision'=>$data['expected_revision']);
        $saved=add_post_meta($id,self::META_KEY,$record,true);
        $audited=$saved && BadAround_Audit_Log::record('event',$id,'radar_editorial_review',
            'radar_quality',$data['reason']);
        if (!$saved || !$audited || $wpdb->query('COMMIT')===false) {
            $wpdb->query('ROLLBACK');
            wp_cache_delete($id,'post_meta');
            return new WP_Error('ba_radar_review_write_failed','Review not committed');
        }
        wp_cache_delete($id,'post_meta');
        return array('event_id'=>$id,'status'=>$record['status'],'audited'=>true);
    }

    public static function fingerprint($id) {
        $post=get_post($id);
        if (!$post || $post->post_type!=='ba_evento') return str_repeat('0',64);
        return hash('sha256',wp_json_encode(array(
            'id'=>(int)$post->ID,'modified_gmt'=>$post->post_modified_gmt,
            'status'=>$post->post_status,'title'=>$post->post_title,
            'date'=>get_post_meta($id,'_ba_occurred_date',true),
            'source'=>get_post_meta($id,'_ba_source_type',true))));
    }
}
