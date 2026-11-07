<?php
/**
 * INTELLIGENCE-02A — offline descriptive trend analysis of canonical public reports.
 * No hooks, REST routes, cron, storage, risk scores, or public alerts.
 * Input MUST be a complete validated Discovery-derived snapshot of public rows.
 */
if (!defined('ABSPATH')) { exit; }

final class BadAround_Intelligence_Trends {
    /**
     * @param array $rows Canonical public Discovery projections, already gated.
     * @param DateTimeImmutable $today Explicit civil date for reproducibility.
     * @param int $days Window length (7 or 30).
     * @return array Descriptive counts only; not unique incidents or risk.
     */
    public static function summarize(array $rows, DateTimeImmutable $today, $days = 7) {
        if (!in_array($days, array(7,30), true)) {
            throw new InvalidArgumentException('Unsupported window');
        }
        $today = $today->setTime(0,0,0);
        $tz = $today->getTimezone();
        $current_start = $today->sub(new DateInterval('P' . ($days-1) . 'D'));
        $previous_end = $current_start->sub(new DateInterval('P1D'));
        $previous_start = $previous_end->sub(new DateInterval('P' . ($days-1) . 'D'));
        $seen = array();
        $groups = array();
        $missing_dates = 0;
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['id']) || !is_int($row['id'])
                || $row['id'] < 1 || isset($seen[$row['id']])) {
                throw new InvalidArgumentException('Invalid or duplicate public report');
            }
            $seen[$row['id']] = true;
            $date = self::date($row['occurred_date'] ?? null, $tz);
            if (!$date) { $missing_dates++; continue; }
            if ($date < $previous_start || $date > $today) { continue; }
            $type = $row['event_type']['slug'] ?? null;
            $territory = $row['territory']['slug'] ?? null;
            // Do not mix missing/ambiguous territory or type with confirmed groups.
            if (!is_string($type) || !preg_match('/^[a-z0-9-]+$/D',$type)
                || !is_string($territory) || !preg_match('/^[a-z0-9-]+$/D',$territory)) {
                continue;
            }
            $key = $territory . '/' . $type;
            if (!isset($groups[$key])) {
                $groups[$key] = array('territory_slug'=>$territory,'event_type_slug'=>$type,
                    'current_reports'=>0,'previous_reports'=>0);
            }
            if ($date >= $current_start) { $groups[$key]['current_reports']++; }
            else { $groups[$key]['previous_reports']++; }
        }
        ksort($groups, SORT_STRING);
        $groups = array_values($groups);
        foreach ($groups as &$group) {
            $c = $group['current_reports']; $p = $group['previous_reports'];
            // No percentage when the comparison base is zero.
            $group['delta_reports'] = $c - $p;
            $group['percentage_change'] = $p > 0 ? round(($c - $p) * 100 / $p, 1) : null;
            $group['comparison_available'] = $p > 0;
        }
        unset($group);
        return array(
            'contract'=>'badaround-intelligence-descriptive-trends/v0.1',
            'unit'=>'published_reports_not_unique_incidents',
            'period_days'=>$days,
            'current_start'=>$current_start->format('Y-m-d'),
            'current_end'=>$today->format('Y-m-d'),
            'previous_start'=>$previous_start->format('Y-m-d'),
            'previous_end'=>$previous_end->format('Y-m-d'),
            'input_report_count'=>count($rows),
            'undated_or_invalid_report_count'=>$missing_dates,
            'groups'=>$groups,
            'anomaly_detection_enabled'=>false,
            'risk_inference_enabled'=>false,
            'public_alerts'=>array()
        );
    }
    private static function date($value, DateTimeZone $tz) {
        if (!is_string($value)) { return null; }
        $format = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',$value) ? 'Y-m-d'
            : (preg_match('/^\\d{2}\\/\\d{2}\\/\\d{4}$/D',$value) ? 'd/m/Y' : null);
        if (!$format) { return null; }
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value, $tz);
        return $date && $date->format($format) === $value ? $date : null;
    }
}
