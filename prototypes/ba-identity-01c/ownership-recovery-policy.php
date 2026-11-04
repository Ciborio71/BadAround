<?php
/**
 * Dormant BA-IDENTITY-01D recovery plan for canonical report ownership.
 * A real WordPress adapter MUST persist the intent durably before returning
 * success to a user, and must never infer identity from supplied email.
 * No routes, DB mutations, timers or hooks are registered here.
 */
declare(strict_types=1);

final class BadAround_Identity_Ownership_Recovery_Policy {
    public const PENDING = 'pending';
    public const BOUND = 'bound';
    public const REVIEW = 'needs_review';

    public static function new_intent(
        string $identityId, array $trustedReceipt, string $operationId, int $now
    ): array {
        if (!preg_match('/^[A-Za-z0-9_-]{8,128}$/D', $identityId)
            || !preg_match('/^[a-f0-9]{32,64}$/D', $operationId)
            || $now <= 0
            || ($trustedReceipt['duplicate'] ?? null) !== false
            || !is_int($trustedReceipt['report_id'] ?? null)
            || !is_int($trustedReceipt['event_id'] ?? null)
            || $trustedReceipt['report_id'] <= 0
            || $trustedReceipt['event_id'] <= 0) {
            throw new InvalidArgumentException('Untrusted binding intent');
        }
        return [
            'operation_id'=>$operationId,
            'identity_id'=>$identityId,
            'report_id'=>$trustedReceipt['report_id'],
            'event_id'=>$trustedReceipt['event_id'],
            'state'=>self::PENDING,
            'attempts'=>0,
            'created_at'=>$now,
            'resolved_at'=>null,
        ];
    }

    /**
     * Only server-loaded, verified identity and canonical new-intake receipt
     * may enter this state machine. The caller must lock the durable intent
     * row and compare its version when applying a transition.
     */
    public static function transition(array $stored, string $outcome, int $now): array {
        if (($stored['state'] ?? null) !== self::PENDING
            || !is_int($stored['attempts'] ?? null)
            || $stored['attempts'] < 0 || $now <= (int)($stored['created_at'] ?? 0)
            || !in_array($outcome,['granted','retry','conflict'],true)) {
            throw new DomainException('Invalid recovery transition');
        }
        $next=$stored;
        $next['attempts']++;
        if ($outcome === 'granted') {
            $next['state']=self::BOUND;
            $next['resolved_at']=$now;
        } elseif ($outcome === 'conflict' || $next['attempts'] >= 5) {
            $next['state']=self::REVIEW;
            $next['resolved_at']=$now;
        }
        return $next;
    }

    public static function can_show_as_owned(array $stored): bool {
        return ($stored['state'] ?? null) === self::BOUND;
    }
}
