<?php
/**
 * INTELLIGENCE-02C — quality gate for OFFLINE descriptive report summaries.
 * This is not a statistical-significance test, event deduplication or public alert.
 * No hooks, writes, cron, endpoints, coordinates or private input.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Intelligence_Data_Quality {
    public static function assess(array $summary, array $candidates) {
        if (($summary['contract'] ?? null) !== 'badaround-intelligence-descriptive-trends/v0.1'
            || ($candidates['contract'] ?? null) !== 'badaround-intelligence-peak-candidates/v0.1'
            || ($summary['unit'] ?? null) !== 'published_reports_not_unique_incidents'
            || ($candidates['unit'] ?? null) !== $summary['unit']
            || !in_array($summary['period_days'] ?? null, array(7,30), true)
            || ($candidates['period_days'] ?? null) !== $summary['period_days']
            || !is_array($summary['groups'] ?? null) || !is_array($candidates['groups'] ?? null)
            || count($summary['groups']) !== count($candidates['groups'])
            || ($candidates['thresholds_approved'] ?? null) !== false
            || ($candidates['public_alerts'] ?? null) !== array()
            || ($summary['public_alerts'] ?? null) !== array()) {
            throw new InvalidArgumentException('Consistent private QA summaries required');
        }
        $total = $summary['input_report_count'] ?? null;
        $missing = $summary['undated_or_invalid_report_count'] ?? null;
        if (!is_int($total) || !is_int($missing) || $total < 0 || $missing < 0 || $missing > $total) {
            throw new InvalidArgumentException('Invalid coverage totals');
        }
        $flags = array();
        foreach ($summary['groups'] as $index => $group) {
            $candidate = $candidates['groups'][$index];
            foreach (array('territory_slug','event_type_slug','current_reports','previous_reports') as $field) {
                if (!array_key_exists($field,$group) || !array_key_exists($field,$candidate)
                    || $group[$field] !== $candidate[$field]) {
                    throw new InvalidArgumentException('Mismatched candidate/source row');
                }
            }
            $slug = $group['territory_slug']; $type = $group['event_type_slug'];
            if (!is_string($slug) || !preg_match('/^[a-z0-9-]+$/D',$slug)
                || !is_string($type) || !preg_match('/^[a-z0-9-]+$/D',$type)
                || !is_int($group['current_reports']) || $group['current_reports'] < 0
                || !is_int($group['previous_reports']) || $group['previous_reports'] < 0
                || !is_bool($candidate['candidate_for_manual_review'] ?? null)) {
                throw new InvalidArgumentException('Invalid group');
            }
            $reasons = array();
            // Coverage is global, NOT an estimate of territory-specific reporting completeness.
            if ($total === 0 || $missing * 5 > $total) $reasons[]='unreliable_date_coverage';
            if ($group['previous_reports'] < 5) $reasons[]='small_comparison_base';
            $flags[]=array('territory_slug'=>$slug,'event_type_slug'=>$type,
                'candidate_for_manual_review'=>$candidate['candidate_for_manual_review'],
                'quality_review_required'=>count($reasons)>0,
                'quality_reasons'=>$reasons);
        }
        return array(
            'contract'=>'badaround-intelligence-quality-gate/v0.1',
            'unit'=>'published_reports_not_unique_incidents',
            'date_coverage_count'=>$total-$missing,
            'undated_or_invalid_count'=>$missing,
            'date_coverage_ratio'=>$total>0?round(($total-$missing)/$total,4):null,
            'groups'=>$flags,
            'statistical_significance_assessed'=>false,
            'representativeness_assessed'=>false,
            'duplicate_incidents_resolved'=>false,
            'thresholds_approved'=>false,
            'public_alerts'=>array());
    }
}
