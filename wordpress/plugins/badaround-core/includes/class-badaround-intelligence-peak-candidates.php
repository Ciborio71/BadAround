<?php
/**
 * INTELLIGENCE-02B — conservative descriptive candidate screening.
 * Consumes only prevalidated INTELLIGENCE-02A output, no WordPress hooks or public alerts.
 * Thresholds are provisional QA fixtures, NOT approved public alert thresholds.
 */
if (!defined('ABSPATH')) exit;
final class BadAround_Intelligence_Peak_Candidates {
    public static function screen(array $summary) {
        if (($summary['contract'] ?? null) !== 'badaround-intelligence-descriptive-trends/v0.1'
            || ($summary['unit'] ?? null) !== 'published_reports_not_unique_incidents'
            || !in_array($summary['period_days'] ?? null, array(7,30), true)
            || !is_array($summary['groups'] ?? null)
            || ($summary['anomaly_detection_enabled'] ?? null) !== false
            || ($summary['risk_inference_enabled'] ?? null) !== false
            || ($summary['public_alerts'] ?? null) !== array()) {
            throw new InvalidArgumentException('Validated descriptive summary required');
        }
        $out = array();
        foreach ($summary['groups'] as $group) {
            $c = $group['current_reports'] ?? null;
            $p = $group['previous_reports'] ?? null;
            $territory = $group['territory_slug'] ?? null;
            $type = $group['event_type_slug'] ?? null;
            if (!is_int($c) || !is_int($p) || $c < 0 || $p < 0
                || !is_string($territory) || !preg_match('/^[a-z0-9-]+$/D', $territory)
                || !is_string($type) || !preg_match('/^[a-z0-9-]+$/D', $type)) {
                throw new InvalidArgumentException('Malformed group');
            }
            // Deliberately strict, provisional diagnostic gate.
            // Do not present this as statistical significance.
            $eligible = $p >= 5 && $c >= 10 && $c >= 2 * $p;
            $out[] = array(
                'territory_slug'=>$territory,
                'event_type_slug'=>$type,
                'current_reports'=>$c,
                'previous_reports'=>$p,
                'candidate_for_manual_review'=>$eligible,
                'suppression_reason'=>$p < 5 ? 'insufficient_previous_reports'
                    : ($c < 10 ? 'insufficient_current_reports'
                    : ($c < 2*$p ? 'increase_below_provisional_gate' : null))
            );
        }
        return array(
            'contract'=>'badaround-intelligence-peak-candidates/v0.1',
            'period_days'=>$summary['period_days'],
            'unit'=>'published_reports_not_unique_incidents',
            'candidate_count'=>count(array_filter($out, static function($g) {
                return $g['candidate_for_manual_review'];
            })),
            'groups'=>$out,
            'thresholds_approved'=>false,
            'statistically_significant'=>false,
            'public_alerts'=>array(),
            'risk_inference_enabled'=>false
        );
    }
}
