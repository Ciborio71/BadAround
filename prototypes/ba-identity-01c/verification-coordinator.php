<?php
/**
 * BA-IDENTITY-01C — isolated verified OTP -> session coordinator.
 * Prototype only: no WordPress hooks, HTTP endpoints, cookies or live database.
 * Identity creation is deliberately OUT OF SCOPE: only a trusted, existing,
 * verified identity may receive a session.
 */
declare(strict_types=1);

require_once __DIR__ . '/challenge-store.php';
require_once __DIR__ . '/session-policy.php';

interface BadAround_Identity_Verified_Resolver {
    /**
     * Resolve a verified identity using the trusted, server-sourced email HMAC.
     * Must NOT resolve from client-supplied email or public pseudonym.
     * Returns null for missing/unverified/revoked identities.
     */
    public function find_verified_identity(string $emailLookup): ?string;
}

final class BadAround_Identity_Verification_Coordinator {
    public function __construct(
        private BadAround_Identity_Challenge_Store $challenges,
        private BadAround_Identity_Challenge_Verifier $verifier,
        private BadAround_Identity_Verified_Resolver $identities,
        private BadAround_Identity_Sessions $sessions
    ) {}

    /**
     * Return a session bearer ONLY after the single-use verification succeeds.
     * Caller alone sets the secure cookie and MUST NEVER log the secret.
     * No session is issued for unknown, unverified or revoked identities.
     *
     * Concurrency: first successful atomic OTP consumer wins. Second caller
     * must fail. A failure after OTP consume requires a new challenge.
     */
    public function verify_and_issue(string $challengeId, string $code, int $now): ?string {
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId)) { return null; }
        $record = $this->challenges->load($challengeId);
        if (!$record || !is_string($record['email_lookup'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $record['email_lookup'])) {
            return null;
        }
        // Resolve BEFORE consuming OTP, but do not trust this as authorization:
        // the verifier still must atomically consume the challenge.
        $identity = $this->identities->find_verified_identity($record['email_lookup']);
        if ($identity === null || !preg_match('/^[a-zA-Z0-9_-]{8,128}$/D', $identity)) {
            return null;
        }
        if ($this->verifier->verify($challengeId, $code, $now) !== 'verified') {
            return null;
        }
        // Check again after OTP consumption: identity may have been revoked
        // between the initial read and the successful compare-and-swap.
        if ($this->identities->find_verified_identity($record['email_lookup']) !== $identity) {
            return null;
        }
        return $this->sessions->issue($identity, $now);
    }
}

/** In-memory resolver is ONLY for isolated tests. */
final class BadAround_Identity_Verified_Memory_Resolver implements BadAround_Identity_Verified_Resolver {
    /** @var array<string,string> */
    public array $verified = [];
    public function find_verified_identity(string $emailLookup): ?string {
        return $this->verified[$emailLookup] ?? null;
    }
}
