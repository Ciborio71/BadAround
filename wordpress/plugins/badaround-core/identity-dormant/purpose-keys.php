<?php
/**
 * BA-IDENTITY-01D — dormant cryptographic challenge purpose separation.
 * Different derived keys for enrollment vs login/verification: a valid
 * one-time code for one action cannot authenticate another action.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

final class BadAround_Identity_Purpose_Keys {
    private const ALLOWED = ['enrollment','login','verification','access'];
    /**
     * Keep the identity email lookup stable across authentication purposes.
     * Only OTP digest uses the purpose-derived key; never use the derived
     * purpose key to compute the long-lived identity lookup.
     */
    public static function new_challenge_for_purpose(
        string $email, string $purpose, string $rootKey, int $now, int $ttl = 600
    ): array {
        $email = BadAround_Identity_Challenge_Primitives::normalize_email($email);
        $otpKey = self::for_purpose($purpose,$rootKey);
        $challenge = BadAround_Identity_Challenge_Primitives::new_challenge(
            $email,$otpKey,$now,$ttl
        );
        $challenge['record']['email_lookup'] = BadAround_Identity_Challenge_Primitives::digest(
            'email','lookup',$email,$rootKey
        );
        return $challenge;
    }

    public static function for_purpose(string $purpose, string $rootKey): string {
        if (!in_array($purpose,self::ALLOWED,true) || strlen($rootKey) < 32) {
            throw new InvalidArgumentException('Invalid challenge purpose');
        }
        // Output 32 bytes, suitable for challenge primitive HMAC key.
        return hash_hmac('sha256','BadAround:identity:otp-purpose:v1:'.$purpose,$rootKey,true);
    }
}
