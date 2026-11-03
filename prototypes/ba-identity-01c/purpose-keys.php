<?php
/**
 * BA-IDENTITY-01D — dormant cryptographic challenge purpose separation.
 * Different derived keys for enrollment vs login/verification: a valid
 * one-time code for one action cannot authenticate another action.
 */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

final class BadAround_Identity_Purpose_Keys {
    private const ALLOWED = ['enrollment','login','verification'];
    public static function for_purpose(string $purpose, string $rootKey): string {
        if (!in_array($purpose,self::ALLOWED,true) || strlen($rootKey) < 32) {
            throw new InvalidArgumentException('Invalid challenge purpose');
        }
        // Output 32 bytes, suitable for challenge primitive HMAC key.
        return hash_hmac('sha256','BadAround:identity:otp-purpose:v1:'.$purpose,$rootKey,true);
    }
}
