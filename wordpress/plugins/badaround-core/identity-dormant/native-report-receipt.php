<?php
/**
 * BA-IDENTITY-WIZARD-01 — server-only bridge from native Golden Path result.
 *
 * No hooks, routes, or database writes. The object passed here MUST be the
 * return value of the trusted native Golden Path invocation, not request JSON.
 * Never infer ownership from email, event_id or submission_id in a request.
 */
declare(strict_types=1);

final class BadAround_Identity_Native_Report_Receipt {
    /**
     * Return a strict, private ownership candidate only for a newly created
     * canonical event and report. Never return a candidate for recovered,
     * duplicated or ambiguous native persistence results.
     *
     * @param array $result Trusted Golden Path result, never browser payload
     * @return array{report_id:int,event_id:int,duplicate:false}|null
     */
    public static function from_golden_path(array $result): ?array {
        if (($result['status'] ?? null) !== 'success'
            || !array_key_exists('duplicate', $result)
            || $result['duplicate'] !== false
            || !is_array($result['persistence'] ?? null)) {
            return null;
        }
        $persistence = $result['persistence'];
        if (($persistence['completed'] ?? null) !== true
            || ($persistence['duplicate'] ?? null) !== false
            || ($persistence['recovered'] ?? null) !== false) {
            return null;
        }
        $reportId = $result['report_id'] ?? null;
        $eventId = $result['event_id'] ?? null;
        if (!is_int($reportId) || $reportId <= 0
            || !is_int($eventId) || $eventId <= 0) {
            return null;
        }
        return ['report_id' => $reportId, 'event_id' => $eventId, 'duplicate' => false];
    }
}
