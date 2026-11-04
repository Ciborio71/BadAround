<?php
/**
 * BA-IDENTITY-01C — first-time enrollment policy, isolated prototype.
 * No WordPress bootstrap, no DB migration, no email sending, no API.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-store.php';

interface BadAround_Identity_Pending_Enrollment_Store {
    /** Store a pending profile bound to an issued challenge, never visible publicly. */
    public function create_pending(array $pending): void;
    /** Read a trusted private record; must not be populated from request body. */
    public function find_by_challenge(string $challengeId): ?array;
    /**
     * Atomically finish one pending enrollment after challenge verification.
     * A real adapter must prevent multiple identities with the same email_lookup,
     * avoid modifying existing verified profiles, and commit before returning.
     */
    public function activate_once(string $challengeId, string $emailLookup, int $verifiedAt): ?string;
}

/**
 * Transactional enrollment adapter: OTP verification, attempts, consumption
 * and identity creation commit together in one database transaction.
 */
interface BadAround_Identity_Atomic_Enrollment_Store extends BadAround_Identity_Pending_Enrollment_Store {
    public function verify_and_activate_atomic(string $challengeId, string $code, string $serverKey, int $now): ?string;
}

final class BadAround_Identity_Enrollment {
    public function __construct(
        private BadAround_Identity_Pending_Enrollment_Store $enrollments,
        private BadAround_Identity_Challenge_Store $challenges,
        private BadAround_Identity_Challenge_Verifier $verifier,
        private string $serverKey,
        private ?string $otpPurposeKey = null
    ) {
        if (strlen($serverKey) < 32 || ($otpPurposeKey !== null && strlen($otpPurposeKey) < 32)) {
            throw new InvalidArgumentException('Server key too short');
        }
    }

    public static function validate_profile(string $firstName, string $lastName, string $email): array {
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        $email = BadAround_Identity_Challenge_Primitives::normalize_email($email);
        foreach ([$firstName, $lastName] as $name) {
            if ($name === '' || mb_strlen($name, 'UTF-8') > 191
                || preg_match('/[\x00-\x1f\x7f<>]/u', $name)) {
                throw new InvalidArgumentException('Invalid private identity fields');
            }
        }
        return ['first_name'=>$firstName, 'last_name'=>$lastName, 'email'=>$email];
    }

    /**
     * Caller must deliver OTP separately; no identity is active before verify.
     * Do not return private fields in public JSON responses.
     */
    public function stage(array $profile, array $challengeRecord, int $now, ?array $privacyConsent = null): void {
        foreach (['first_name', 'last_name', 'email'] as $field) {
            if (!array_key_exists($field, $profile) || !is_string($profile[$field])) {
                throw new InvalidArgumentException('Missing mandatory profile data');
            }
        }
        $profile = self::validate_profile($profile['first_name'], $profile['last_name'], $profile['email']);
        $challengeId = $challengeRecord['challenge_id'] ?? '';
        $lookup = $challengeRecord['email_lookup'] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId)
            || !preg_match('/^[a-f0-9]{64}$/D', $lookup)
            || !hash_equals(
                BadAround_Identity_Challenge_Primitives::digest('email', 'lookup', $profile['email'], $this->serverKey),
                $lookup
            )) {
            throw new InvalidArgumentException('Invalid challenge metadata');
        }
        // Never stage against a fabricated or expired client-supplied challenge.
        $stored = $this->challenges->load($challengeId);
        if (!$stored || ($stored['email_lookup'] ?? null) !== $lookup
            || ($stored['challenge_id'] ?? null) !== $challengeId
            || ($stored['consumed_at'] ?? null) !== null
            || !isset($stored['expires_at']) || $now >= (int)$stored['expires_at']) {
            throw new InvalidArgumentException('Challenge unavailable for enrollment');
        }
        // No implicit consent: the caller must get affirmative user action.
        // Legacy stage() remains available to old CI callers, but the only
        // new public controller uses consent-required registration service.
        if ($privacyConsent !== null
            && (($privacyConsent['accepted'] ?? null) !== true
                || ($privacyConsent['method'] ?? null) !== 'explicit_checkbox'
                || !preg_match('/^[A-Za-z0-9._-]{1,64}$/D',
                    (string)($privacyConsent['notice_version'] ?? ''))
                || !is_int($privacyConsent['accepted_at'] ?? null)
                || $privacyConsent['accepted_at'] !== $now)) {
            throw new InvalidArgumentException('Invalid consent evidence');
        }
        $this->enrollments->create_pending([
            'challenge_id'=>$challengeId,
            'email_lookup'=>$lookup,
            'first_name'=>$profile['first_name'],
            'last_name'=>$profile['last_name'],
            'email'=>$profile['email'],
            'created_at'=>$now,
            'verified_at'=>null,
            'privacy_consent'=>$privacyConsent,
        ]);
    }

    /**
     * Returns identity id only after verified OTP was atomically consumed.
     * No session is issued here. Caller MUST NOT create ownership for old
     * reports based solely on email.
     */
    public function verify_and_activate(string $challengeId, string $code, int $now): ?string {
        if ($this->enrollments instanceof BadAround_Identity_Atomic_Enrollment_Store) {
            return $this->enrollments->verify_and_activate_atomic($challengeId, $code, $this->otpPurposeKey ?? $this->serverKey, $now);
        }
        $pending = $this->enrollments->find_by_challenge($challengeId);
        $challenge = $this->challenges->load($challengeId);
        if (!$pending || !$challenge || ($pending['challenge_id'] ?? null) !== $challengeId
            || !is_string($challenge['email_lookup'] ?? null)
            || !hash_equals((string)($pending['email_lookup'] ?? ''), $challenge['email_lookup'])) {
            return null;
        }
        if ($this->verifier->verify($challengeId, $code, $now) !== 'verified') {
            return null;
        }
        return $this->enrollments->activate_once($challengeId, $challenge['email_lookup'], $now);
    }
}

/** Test double only; no persistent identities or email delivery. */
final class BadAround_Identity_Enrollment_Memory_Store implements BadAround_Identity_Pending_Enrollment_Store {
    private array $pending = [];
    private array $verified = [];
    public function create_pending(array $pending): void {
        $id = $pending['challenge_id'];
        if (isset($this->pending[$id])) { throw new RuntimeException('Duplicate challenge'); }
        $this->pending[$id] = $pending;
    }
    public function find_by_challenge(string $challengeId): ?array {
        return $this->pending[$challengeId] ?? null;
    }
    public function activate_once(string $challengeId, string $emailLookup, int $verifiedAt): ?string {
        $row = $this->pending[$challengeId] ?? null;
        if (!$row || $row['verified_at'] !== null || $row['email_lookup'] !== $emailLookup
            || isset($this->verified[$emailLookup])) {
            return null; // never overwrite a previously verified identity
        }
        $id = 'identity_' . bin2hex(random_bytes(12));
        $this->verified[$emailLookup] = $id;
        $this->pending[$challengeId]['verified_at'] = $verifiedAt;
        return $id;
    }
    public function verified_count(): int { return count($this->verified); }
}
