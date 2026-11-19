<?php
/**
 * BA-IDENTITY-01C isolated prototype: atomic challenge transition contract.
 * Not bootstrapped by WordPress; no database tables, routes or sessions.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

interface BadAround_Identity_Challenge_Store {
    /** Returns null when not found. Each stored record carries integer version >= 0. */
    public function load(string $challengeId): ?array;
    /**
     * Atomically replace only when version matches and the old row is not
     * consumed. Implementations MUST make this a single conditional write.
     * Return false on a concurrent update.
     */
    public function compare_and_swap(string $challengeId, int $expectedVersion, array $newRecord): bool;
}

final class BadAround_Identity_Challenge_Verifier {
    private BadAround_Identity_Challenge_Store $store;
    private string $key;

    public function __construct(BadAround_Identity_Challenge_Store $store, string $key) {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('Server key too short');
        }
        $this->store = $store;
        $this->key = $key;
    }

    /**
     * Returns only an outcome; caller may issue a session AFTER an atomic
     * successful consume, never on a stale or concurrent row.
     */
    public function verify(string $id, string $code, int $now): string {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            return 'invalid';
        }
        for ($retry = 0; $retry < 3; $retry++) {
            $current = $this->store->load($id);
            if ($current === null || !isset($current['version']) ||
                !is_int($current['version']) || ($current['challenge_id'] ?? null) !== $id) {
                return 'invalid';
            }
            $transition = BadAround_Identity_Challenge_Primitives::verify($current, $code, $this->key, $now);
            if (in_array($transition['reason'], ['consumed', 'expired', 'locked'], true)) {
                return 'invalid';
            }
            $next = $transition['record'];
            $next['version'] = $current['version'] + 1;
            if ($this->store->compare_and_swap($id, $current['version'], $next)) {
                return $transition['ok'] ? 'verified' : 'invalid';
            }
        }
        return 'invalid'; // fail closed after persistent contention
    }
}

/** Test-only in-memory store, NOT a production persistence adapter. */
final class BadAround_Identity_Challenge_Memory_Store implements BadAround_Identity_Challenge_Store {
    private array $records = [];
    public function insert(array $record): void {
        $record['version'] = 0;
        $this->records[$record['challenge_id']] = $record;
    }
    public function load(string $challengeId): ?array {
        return $this->records[$challengeId] ?? null;
    }
    public function compare_and_swap(string $challengeId, int $expectedVersion, array $next): bool {
        $old = $this->records[$challengeId] ?? null;
        if (!$old || $old['version'] !== $expectedVersion || $old['consumed_at'] !== null
            || $next['version'] !== $expectedVersion + 1 || $next['challenge_id'] !== $challengeId) {
            return false;
        }
        $this->records[$challengeId] = $next;
        return true;
    }
}
