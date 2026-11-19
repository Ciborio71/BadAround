<?php
/**
 * BA-IDENTITY-01C — isolated, no WordPress bootstrap or persistent storage.
 * Pure primitives only; never loaded by production.
 */
declare(strict_types=1);

final class BadAround_Identity_Challenge_Primitives {
    public const MIN_TTL = 60;
    public const MAX_TTL = 900;
    public const MAX_ATTEMPTS = 5;

    public static function normalize_email(string $email): string {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            throw new InvalidArgumentException('Invalid email');
        }
        return $email;
    }

    public static function generate_code(): string {
        // Six decimal digits; CSPRNG, short TTL, five attempts and shared issuance quotas.
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function generate_session_secret(): string {
        // Not used as a public ID, and never stored unhashed.
        return bin2hex(random_bytes(32));
    }

    public static function digest(string $purpose, string $subject, string $secret, string $server_key): string {
        if (strlen($server_key) < 32 || $purpose === '' || $subject === '' || $secret === '') {
            throw new InvalidArgumentException('Invalid challenge context');
        }
        // Separate email-lookup keys, OTP keys and session keys by domain.
        return hash_hmac('sha256', 'BA-ID-01C:' . $purpose . "\0" . $subject . "\0" . $secret, $server_key);
    }

    public static function new_challenge(string $email, string $server_key, int $now, int $ttl = 600): array {
        $email = self::normalize_email($email);
        if ($ttl < self::MIN_TTL || $ttl > self::MAX_TTL) {
            throw new InvalidArgumentException('Invalid lifetime');
        }
        $code = self::generate_code();
        $nonce = bin2hex(random_bytes(16));
        return [
            'delivery_code' => $code, // Caller must never persist or log this field.
            'record' => [
                'challenge_id' => $nonce,
                'email_lookup' => self::digest('email', 'lookup', $email, $server_key),
                'code_digest' => self::digest('otp', $nonce, $code, $server_key),
                'created_at' => $now,
                'expires_at' => $now + $ttl,
                'attempts' => 0,
                'consumed_at' => null,
            ],
        ];
    }

    /**
     * Pure transition: caller must persist the result with an atomic
     * compare-and-swap on challenge_id/version to prevent concurrent reuse.
     * On success, caller must commit consumed_at BEFORE issuing a session.
     */
    public static function verify(array $record, string $code, string $server_key, int $now): array {
        if (!isset($record['challenge_id'], $record['code_digest'], $record['expires_at'], $record['attempts'])
            || !array_key_exists('consumed_at', $record)) {
            throw new InvalidArgumentException('Invalid challenge record');
        }
        if ($record['consumed_at'] !== null) {
            return ['ok' => false, 'reason' => 'consumed', 'record' => $record];
        }
        if ($now >= (int)$record['expires_at']) {
            return ['ok' => false, 'reason' => 'expired', 'record' => $record];
        }
        if ((int)$record['attempts'] >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'reason' => 'locked', 'record' => $record];
        }
        $record['attempts'] = (int)$record['attempts'] + 1;
        $valid_format = preg_match('/^(?:[0-9]{6}|[0-9]{8})$/D', $code) === 1;
        $candidate = $valid_format
            ? self::digest('otp', (string)$record['challenge_id'], $code, $server_key)
            : str_repeat('0', 64);
        if (!$valid_format || !hash_equals((string)$record['code_digest'], $candidate)) {
            return ['ok' => false, 'reason' => 'invalid', 'record' => $record];
        }
        $record['consumed_at'] = $now;
        return ['ok' => true, 'reason' => 'verified', 'record' => $record];
    }
}
