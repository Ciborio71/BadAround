<?php
/**
 * BA-IDENTITY-01D — framework-free OTP issuance boundary.
 * Prepared for the existing BadAround_Transactional_Mailer via an injected
 * adapter; this module DOES NOT send real email or register REST routes.
 */
declare(strict_types=1);
require_once __DIR__ . '/purpose-keys.php';

interface BadAround_Identity_Issue_Quota {
    /** Quotas must be enforced by shared DB, not request-local counters. */
    public function allow(string $purpose, string $emailLookup, string $ip, int $now): bool;
}

interface BadAround_Identity_Challenge_Writer {
    /** Atomic unique insert, MUST NOT retain delivery_code or raw email. */
    public function insert(array $record): void;
    /** Make a persisted but undelivered challenge unusable immediately. */
    public function invalidate(string $challengeId, int $now): void;
}

interface BadAround_Identity_Otp_Transport {
    /** Only the injected provider sees plaintext OTP and recipient email. */
    public function send_code(string $email, string $code, string $purpose): bool;
}

final class BadAround_Identity_Otp_Issuer {
    private string $rootKey;
    private BadAround_Identity_Issue_Quota $quota;
    private BadAround_Identity_Challenge_Writer $writer;
    private BadAround_Identity_Otp_Transport $transport;

    public function __construct(
        string $rootKey,
        BadAround_Identity_Issue_Quota $quota,
        BadAround_Identity_Challenge_Writer $writer,
        BadAround_Identity_Otp_Transport $transport
    ) {
        if (strlen($rootKey) < 32) { throw new InvalidArgumentException('Server key too short'); }
        $this->rootKey = $rootKey;
        $this->quota = $quota;
        $this->writer = $writer;
        $this->transport = $transport;
    }

    /**
     * Returns the opaque challenge ID on success, null on any failure.
     * Controller MUST expose the same generic receipt for all outcomes.
     * A trusted HTTP layer must derive the IP; do not consume X-Forwarded-For
     * without an explicit trusted-proxy allowlist.
     */
    public function issue(string $email, string $purpose, string $trustedIp, int $now): ?string {
        if (!in_array($purpose, ['enrollment','login','verification'],true)
            || !filter_var($trustedIp,FILTER_VALIDATE_IP)
            || $now <= 0) {
            return null;
        }
        try {
            $normalized = BadAround_Identity_Challenge_Primitives::normalize_email($email);
            $emailLookup = BadAround_Identity_Challenge_Primitives::digest(
                'email','lookup',$normalized,$this->rootKey
            );
            // Fail closed if shared quota store fails; never send first.
            if (!$this->quota->allow($purpose,$emailLookup,$trustedIp,$now)) { return null; }
            $created = BadAround_Identity_Purpose_Keys::new_challenge_for_purpose(
                $normalized,$purpose,$this->rootKey,$now
            );
            $this->writer->insert($created['record']);
            $delivered = false;
            try {
                $delivered = $this->transport->send_code(
                    $normalized, $created['delivery_code'], $purpose
                );
            } catch (Throwable $deliveryError) {
                // Treat provider exceptions exactly as a rejected delivery.
            }
            if (!$delivered) {
                $this->writer->invalidate($created['record']['challenge_id'], $now);
                return null;
            }
            return $created['record']['challenge_id'];
        } catch (Throwable $e) {
            // Never leak PII, OTP, provider errors or SQL details to the caller.
            // Production adapter may log a non-sensitive error code only.
            return null;
        }
    }
}
