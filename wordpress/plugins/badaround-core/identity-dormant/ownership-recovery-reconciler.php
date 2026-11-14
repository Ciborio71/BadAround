<?php
/**
 * BA-IDENTITY-WIZARD-04: dormant, explicit recovery of journaled ownership.
 * No WordPress hooks, public API, cron registration or SQL on inclusion.
 * Caller must independently authorize invocation; operation ID is looked up
 * in trusted persistent storage. Never accept ownership claims from HTTP.
 */
declare(strict_types=1);
require_once __DIR__ . '/durable-ownership-handoff.php';

interface BadAround_Identity_Recovery_Owner_Reader {
    /** Read a canonical, database-backed creator record for a report. */
    public function find_for_report(int $reportId): ?array;
}

final class BadAround_Identity_Ownership_Recovery_Reconciler {
    public const CONFIRMED = 'confirmed';
    public const PENDING = 'pending';
    public const REVIEW = 'needs_review';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(
        private BadAround_Identity_Recovery_Journal $journal,
        private BadAround_Identity_Intake_Receipt $receipts,
        private BadAround_Identity_Active_Status $status,
        private BadAround_Identity_Recovery_Owner_Reader $owners,
        private BadAround_Identity_Ownership_Grant $grants
    ) {}

    /** Executed only from an independently authorized private recovery process. */
    public function reconcile(string $operationId, int $now): string {
        if (!preg_match('/^[a-f0-9]{32}$/D', $operationId) || $now <= 0) {
            return self::UNAVAILABLE;
        }
        try {
            $row = $this->journal->load($operationId);
            if (!self::valid_row($row, $operationId)) return self::UNAVAILABLE;
            if ($row['state'] === 'needs_review') return self::REVIEW;
            if ($row['state'] === 'bound') {
                return $this->matches_owner($row, $this->owners->find_for_report($row['report_id']))
                    && $this->status->is_active_verified($row['identity_id'])
                    ? self::CONFIRMED : self::REVIEW;
            }
            if ($row['state'] !== 'pending') return self::UNAVAILABLE;
            // A pending journal row is not proof of a live, valid identity.
            if (!$this->status->is_active_verified($row['identity_id'])
                || !$this->receipts->is_new_canonical_report($row['report_id'], $row['event_id'])) {
                return $this->resolve($row, 'conflict', $now);
            }
            $owner = $this->owners->find_for_report($row['report_id']);
            if ($owner !== null && !$this->matches_owner($row, $owner)) {
                return $this->resolve($row, 'conflict', $now);
            }
            if ($owner === null) {
                // Grant may race: the canonical store must be re-read even if
                // grant_creator() returns false.
                $this->grants->grant_creator($row['identity_id'], 'report', $row['report_id'], $now);
                $owner = $this->owners->find_for_report($row['report_id']);
            }
            if ($owner === null) return $this->resolve($row, 'retry', $now);
            if (!$this->matches_owner($row, $owner)) return $this->resolve($row, 'conflict', $now);
            if (!$this->status->is_active_verified($row['identity_id'])) {
                return $this->resolve($row, 'conflict', $now);
            }
            // CAS conflicts cannot be interpreted as ownership success.
            return $this->resolve($row, 'granted', $now);
        } catch (Throwable $error) {
            return self::UNAVAILABLE;
        }
    }

    private function resolve(array $row, string $outcome, int $now): string {
        if ($now <= $row['created_at']) return self::UNAVAILABLE;
        if (!$this->journal->transition($row['operation_id'], $row['revision'], $outcome, $now)) {
            return self::UNAVAILABLE;
        }
        if ($outcome === 'granted') return self::CONFIRMED;
        if ($outcome === 'conflict' || $row['attempts'] + 1 >= 5) return self::REVIEW;
        return self::PENDING;
    }

    private static function valid_row(?array $row, string $operationId): bool {
        return is_array($row)
            && ($row['operation_id'] ?? null) === $operationId
            && in_array($row['state'] ?? null, ['pending','bound','needs_review'], true)
            && is_string($row['identity_id'] ?? null)
            && preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $row['identity_id']) === 1
            && is_int($row['report_id'] ?? null) && $row['report_id'] > 0
            && is_int($row['event_id'] ?? null) && $row['event_id'] > 0
            && is_int($row['revision'] ?? null) && $row['revision'] >= 0
            && is_int($row['attempts'] ?? null) && $row['attempts'] >= 0
            && is_int($row['created_at'] ?? null) && $row['created_at'] > 0;
    }

    private function matches_owner(array $row, ?array $owner): bool {
        return is_array($owner)
            && ($owner['resource_kind'] ?? null) === 'report'
            && ($owner['resource_id'] ?? null) === $row['report_id']
            && ($owner['identity_id'] ?? null) === $row['identity_id']
            && ($owner['relation'] ?? null) === 'creator'
            && ($owner['revoked_at'] ?? null) === null;
    }
}
