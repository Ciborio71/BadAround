<?php
/**
 * BA-IDENTITY-GATE-01: dormant fail-closed eligibility check.
 * No hooks, routes, grants, or SQL on inclusion.
 * Trusted DB-backed inputs ONLY. Never pass request JSON.
 */
declare(strict_types=1);

final class BadAround_Identity_Ownership_Chronology_Gate {
    public static function eligible(
        ?array $reservation,
        ?array $canonical,
        callable $newReceiptProof,
        callable $creationOrderProof
    ): bool {
        if (!is_array($reservation) || !is_array($canonical)) return false;
        $submission = $reservation['submission_id'] ?? null;
        $operation = $reservation['operation_id'] ?? null;
        $identity = $reservation['identity_id'] ?? null;
        $reservedAt = $reservation['created_at'] ?? null;
        $report = $canonical['report_id'] ?? null;
        $event = $canonical['event_id'] ?? null;
        if (!is_string($submission) || !preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $submission
        ) || !is_string($operation) || !preg_match('/^[a-f0-9]{32}$/D', $operation)
            || !is_string($identity) || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $identity)
            || !is_int($reservedAt) || $reservedAt <= 0
            || !in_array($reservation['state'] ?? null, ['reserved', 'linked'], true)
            || !is_int($report) || $report <= 0 || !is_int($event) || $event <= 0
            || ($canonical['submission_id'] ?? null) !== $submission
            || ($canonical['duplicate'] ?? null) !== false) return false;
        if ($reservation['state'] === 'linked'
            && (($reservation['report_id'] ?? null) !== $report
                || ($reservation['event_id'] ?? null) !== $event)) return false;
        try {
            return $newReceiptProof($report, $event) === true
                && $creationOrderProof($report, $event, $reservedAt) === true;
        } catch (Throwable $error) {
            return false;
        }
    }
}
