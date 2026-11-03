<?php
/** BA-IDENTITY-01C: isolated session policy, no cookies or routes emitted. */
declare(strict_types=1);
require_once __DIR__ . '/challenge-primitives.php';

interface BadAround_Identity_Session_Store {
    public function insert(array $record): void;
    public function find(string $digest): ?array;
    public function revoke(string $digest, int $now): bool;
}

final class BadAround_Identity_Sessions {
    public const LIFETIME = 604800; // 7 days absolute
    public const IDLE_TIMEOUT = 86400; // 24 hours

    private BadAround_Identity_Session_Store $store;
    private string $serverKey;

    public function __construct(BadAround_Identity_Session_Store $store, string $serverKey) {
        if (strlen($serverKey) < 32) { throw new InvalidArgumentException('Server key too short'); }
        $this->store = $store;
        $this->serverKey = $serverKey;
    }

    public function issue(string $identityId, int $now): string {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,128}$/D', $identityId)) {
            throw new InvalidArgumentException('Invalid identity');
        }
        $secret = BadAround_Identity_Challenge_Primitives::generate_session_secret();
        $digest = $this->digest($secret);
        $this->store->insert([
            'identity_id'=>$identityId, 'session_digest'=>$digest,
            'created_at'=>$now, 'last_seen_at'=>$now,
            'expires_at'=>$now + self::LIFETIME, 'revoked_at'=>null
        ]);
        // The caller alone sets a Secure/HttpOnly/SameSite=Lax, Path=/,
        // no-Domain __Host-ba_identity cookie. Never log this secret.
        return $secret;
    }

    public function identity_for(string $secret, int $now): ?string {
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) { return null; }
        $row = $this->store->find($this->digest($secret));
        if ($row === null || $row['revoked_at'] !== null
            || $now >= (int)$row['expires_at']
            || $now - (int)$row['last_seen_at'] >= self::IDLE_TIMEOUT
            || $now < (int)$row['created_at']) { return null; }
        return (string)$row['identity_id'];
    }

    public function revoke(string $secret, int $now): bool {
        if (!preg_match('/^[a-f0-9]{64}$/D', $secret)) { return false; }
        return $this->store->revoke($this->digest($secret), $now);
    }

    public static function cookie_options(int $expiresAt): array {
        return ['expires'=>$expiresAt,'path'=>'/','secure'=>true,
                'httponly'=>true,'samesite'=>'Lax'];
    }

    private function digest(string $secret): string {
        return BadAround_Identity_Challenge_Primitives::digest(
            'session','cookie',$secret,$this->serverKey
        );
    }
}

/** In-memory test double, not a persistence solution. */
final class BadAround_Identity_Session_Memory_Store implements BadAround_Identity_Session_Store {
    public array $rows = [];
    public function insert(array $record): void {
        $this->rows[$record['session_digest']] = $record;
    }
    public function find(string $digest): ?array {
        return $this->rows[$digest] ?? null;
    }
    public function revoke(string $digest, int $now): bool {
        if (!isset($this->rows[$digest]) || $this->rows[$digest]['revoked_at'] !== null) {
            return false;
        }
        $this->rows[$digest]['revoked_at'] = $now;
        return true;
    }
}
