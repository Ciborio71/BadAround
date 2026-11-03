<?php
/** Isolated, framework-free CSRF policy. Not loaded by WordPress. */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

final class BadAround_Identity_Csrf_Policy {
    public static function issue(string $sessionSecret, string $serverKey): string {
        if (!preg_match('/^[a-f0-9]{64}$/D', $sessionSecret) || strlen($serverKey) < 32) {
            throw new InvalidArgumentException('Invalid CSRF context');
        }
        $nonce = bin2hex(random_bytes(16));
        $signature = BadAround_Identity_Challenge_Primitives::digest(
            'csrf', $sessionSecret, $nonce, $serverKey
        );
        return $nonce . '.' . $signature;
    }

    public static function verify(string $token, string $sessionSecret, string $serverKey): bool {
        if (!preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D', $token)
            || !preg_match('/^[a-f0-9]{64}$/D', $sessionSecret)
            || strlen($serverKey) < 32) {
            return false;
        }
        [$nonce, $signature] = explode('.', $token, 2);
        $expected = BadAround_Identity_Challenge_Primitives::digest(
            'csrf', $sessionSecret, $nonce, $serverKey
        );
        return hash_equals($expected, $signature);
    }

    /** Enforce same-origin for authenticated JSON write routes. */
    public static function origin_allowed(?string $origin, string $expectedOrigin): bool {
        if (!is_string($origin) || $origin === '') { return false; }
        $actual = parse_url($origin);
        $expected = parse_url($expectedOrigin);
        if (!is_array($actual) || !is_array($expected)) { return false; }
        if (strtolower((string)($actual['scheme'] ?? '')) !== 'https'
            || strtolower((string)($expected['scheme'] ?? '')) !== 'https') { return false; }
        $portActual = (int)($actual['port'] ?? 443);
        $portExpected = (int)($expected['port'] ?? 443);
        return strtolower((string)($actual['host'] ?? '')) === strtolower((string)($expected['host'] ?? ''))
            && $portActual === $portExpected
            && !isset($actual['user']) && !isset($actual['pass']);
    }
}
