<?php
/**
 * RADAR-02A — dormant view-model for internal dashboard preview.
 * No WordPress hooks, REST, queries, templates or production exposure.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Radar_View_Model {
    public static function build(array $trends, array $quality) {
        if (($trends['contract'] ?? null) !== 'badaround-intelligence-descriptive-trends/v0.1'
            || ($quality['contract'] ?? null) !== 'badaround-intelligence-quality-gate/v0.1'
            || ($trends['unit'] ?? null) !== 'published_reports_not_unique_incidents'
            || ($quality['unit'] ?? null) !== $trends['unit']
            || !in_array($trends['period_days'] ?? null,array(7,30),true)
            || !is_array($trends['groups'] ?? null) || !is_array($quality['groups'] ?? null)
            || count($trends['groups']) !== count($quality['groups'])
            || ($trends['public_alerts'] ?? null) !== array()
            || ($quality['public_alerts'] ?? null) !== array()
            || ($quality['statistical_significance_assessed'] ?? null) !== false
            || ($quality['representativeness_assessed'] ?? null) !== false
            || ($quality['thresholds_approved'] ?? null) !== false) {
            throw new InvalidArgumentException('Unverified dashboard sources');
        }
        $cards=array();
        foreach ($trends['groups'] as $i=>$group) {
            $q=$quality['groups'][$i];
            foreach (array('territory_slug','event_type_slug') as $field) {
                if (!isset($group[$field],$q[$field]) || !is_string($group[$field])
                    || !preg_match('/^[a-z0-9-]+$/D',$group[$field])
                    || $group[$field]!==$q[$field]) {
                    throw new InvalidArgumentException('Unmatched group');
                }
            }
            $current=$group['current_reports'] ?? null;
            $previous=$group['previous_reports'] ?? null;
            if (!is_int($current)||!is_int($previous)||$current<0||$previous<0
                || ($q['quality_review_required'] ?? null) !== true
                    && ($q['quality_review_required'] ?? null) !== false) {
                throw new InvalidArgumentException('Invalid counts or quality');
            }
            // Suppress comparative visual claims when quality is questionable.
            $usable=!$q['quality_review_required'];
            $cards[]=array(
                'territory_slug'=>$group['territory_slug'],
                'event_type_slug'=>$group['event_type_slug'],
                'current_reports'=>$current,
                'previous_reports'=>$usable ? $previous : null,
                'delta_reports'=>$usable ? $current-$previous : null,
                'percentage_change'=>$usable && $previous>0
                    ? round(($current-$previous)*100/$previous,1) : null,
                'comparison_displayable'=>$usable,
                'message'=>$usable
                    ? 'Confronto descrittivo di segnalazioni pubblicate'
                    : 'Dati insufficienti per un confronto affidabile'
            );
        }
        return array(
            'contract'=>'badaround-radar-preview/v0.1',
            'internal_preview_only'=>true,
            'title'=>'Andamento delle segnalazioni pubblicate',
            'period_days'=>$trends['period_days'],
            'current_start'=>$trends['current_start'] ?? null,
            'current_end'=>$trends['current_end'] ?? null,
            'cards'=>$cards,
            'disclaimer'=>'I conteggi riguardano segnalazioni pubblicate, non episodi distinti né livelli di rischio.',
            'public_alerts'=>array()
        );
    }
}
