<?php
/**
 * BA-IDENTITY-01C: dormant MySQL enrollment repository for isolated CI.
 * Requires provisioned InnoDB tables; NOT bootstrapped by BadAround Core.
 */
declare(strict_types=1);
require_once __DIR__ . '/enrollment-policy.php';

final class BadAround_Identity_Mysql_Enrollment_Store implements BadAround_Identity_Atomic_Enrollment_Store {
    public function __construct(
        private PDO $pdo,
        private string $pendingTable,
        private string $identityTable,
        private string $encryptionKey,
        private string $challengeTable = 'ba_identity_challenges'
    ) {
        foreach ([$pendingTable, $identityTable, $challengeTable] as $table) {
            if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
                throw new InvalidArgumentException('Invalid table identifier');
            }
        }
        if (strlen($encryptionKey) !== 32 || !function_exists('openssl_encrypt')) {
            throw new InvalidArgumentException('Expected dedicated 32-byte encryption key');
        }
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function encrypt(array $profile): string {
        $nonce = random_bytes(12);
        $tag = '';
        $plaintext = json_encode($profile, JSON_THROW_ON_ERROR);
        $ciphertext = openssl_encrypt(
            $plaintext, 'aes-256-gcm', $this->encryptionKey,
            OPENSSL_RAW_DATA, $nonce, $tag, 'BA-ID-01C-ENROLL'
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Cannot protect private enrollment');
        }
        return base64_encode($nonce . $tag . $ciphertext);
    }

    private function decrypt(string $encoded): array {
        $data = base64_decode($encoded, true);
        if ($data === false || strlen($data) < 29) {
            throw new RuntimeException('Invalid protected enrollment');
        }
        $decoded = openssl_decrypt(
            substr($data, 28), 'aes-256-gcm', $this->encryptionKey,
            OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16),
            'BA-ID-01C-ENROLL'
        );
        if ($decoded === false) { throw new RuntimeException('Enrollment authenticity failure'); }
        return json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
    }

    public function create_pending(array $pending): void {
        $stmt = $this->pdo->prepare("INSERT INTO `{$this->pendingTable}`
            (challenge_id,email_lookup,protected_profile,created_at,verified_at)
            VALUES (:challenge,:lookup,:profile,:created,NULL)");
        $stmt->execute([
            ':challenge'=>$pending['challenge_id'],
            ':lookup'=>$pending['email_lookup'],
            ':profile'=>$this->encrypt([
                'first_name'=>$pending['first_name'],
                'last_name'=>$pending['last_name'],
                'email'=>$pending['email'],
            ]),
            ':created'=>(int)$pending['created_at'],
        ]);
    }

    public function find_by_challenge(string $challengeId): ?array {
        $stmt = $this->pdo->prepare("SELECT challenge_id,email_lookup,protected_profile,
            created_at,verified_at FROM `{$this->pendingTable}`
            WHERE challenge_id=:challenge LIMIT 1");
        $stmt->execute([':challenge'=>$challengeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        $profile = $this->decrypt($row['protected_profile']);
        return [
            'challenge_id'=>$row['challenge_id'],
            'email_lookup'=>$row['email_lookup'],
            'first_name'=>$profile['first_name'],
            'last_name'=>$profile['last_name'],
            'email'=>$profile['email'],
            'created_at'=>(int)$row['created_at'],
            'verified_at'=>$row['verified_at'] === null ? null : (int)$row['verified_at'],
        ];
    }

    /**
     * Verify and activate as ONE InnoDB transaction. The challenge row is
     * locked first: concurrent requests serialize on the same OTP.
     * Invalid attempts are persisted, valid OTP and identity creation commit
     * together; rollback restores OTP if identity insertion fails.
     */
    public function verify_and_activate_atomic(string $challengeId, string $code, string $serverKey, int $now): ?string {
        if (!preg_match('/^[a-f0-9]{32}$/D', $challengeId) || strlen($serverKey) < 32) {
            return null;
        }
        if ($this->pdo->inTransaction()) {
            throw new RuntimeException('Unexpected outer transaction');
        }
        $this->pdo->beginTransaction();
        try {
            // Use the same database/connection as the pending+identity tables.
            $challengeTable = $this->challengeTable;
            $q = $this->pdo->prepare("SELECT challenge_id,email_lookup,code_digest,created_at,
                expires_at,attempts,consumed_at,version FROM `{$challengeTable}`
                WHERE challenge_id=:id FOR UPDATE");
            $q->execute([':id'=>$challengeId]);
            $challenge = $q->fetch(PDO::FETCH_ASSOC);
            if (!$challenge) {
                $this->pdo->rollBack();
                return null;
            }
            foreach (['created_at','expires_at','attempts','version'] as $key) {
                $challenge[$key] = (int)$challenge[$key];
            }
            $challenge['consumed_at'] = $challenge['consumed_at'] === null ? null : (int)$challenge['consumed_at'];
            $result = BadAround_Identity_Challenge_Primitives::verify($challenge, $code, $serverKey, $now);
            if (in_array($result['reason'], ['expired','consumed','locked'], true)) {
                $this->pdo->rollBack();
                return null;
            }
            $next = $result['record'];
            if (!$result['ok']) {
                $q = $this->pdo->prepare("UPDATE `{$challengeTable}`
                    SET attempts=:attempts,version=version+1
                    WHERE challenge_id=:id AND consumed_at IS NULL AND version=:version");
                $q->execute([
                    ':attempts'=>$next['attempts'], ':id'=>$challengeId,
                    ':version'=>$challenge['version']
                ]);
                if ($q->rowCount() !== 1) { throw new RuntimeException('Unexpected OTP race'); }
                $this->pdo->commit();
                return null;
            }
            $q = $this->pdo->prepare("SELECT email_lookup,protected_profile,verified_at
                FROM `{$this->pendingTable}` WHERE challenge_id=:id FOR UPDATE");
            $q->execute([':id'=>$challengeId]);
            $pending = $q->fetch(PDO::FETCH_ASSOC);
            if (!$pending || $pending['verified_at'] !== null
                || !is_string($pending['email_lookup'])
                || !hash_equals($challenge['email_lookup'], $pending['email_lookup'])) {
                $this->pdo->rollBack();
                return null;
            }
            $identityId = 'identity_' . bin2hex(random_bytes(16));
            $q = $this->pdo->prepare("INSERT INTO `{$this->identityTable}`
                (identity_id,email_lookup,protected_profile,verified_at,revoked_at)
                VALUES (:id,:lookup,:profile,:verified,NULL)");
            $q->execute([
                ':id'=>$identityId, ':lookup'=>$challenge['email_lookup'],
                ':profile'=>$pending['protected_profile'], ':verified'=>$now
            ]);
            $q = $this->pdo->prepare("UPDATE `{$challengeTable}`
                SET attempts=:attempts, consumed_at=:consumed, version=version+1
                WHERE challenge_id=:id AND version=:version AND consumed_at IS NULL");
            $q->execute([
                ':attempts'=>$next['attempts'], ':consumed'=>$now,
                ':id'=>$challengeId, ':version'=>$challenge['version']
            ]);
            if ($q->rowCount() !== 1) { throw new RuntimeException('OTP claim lost'); }
            $q = $this->pdo->prepare("UPDATE `{$this->pendingTable}`
                SET verified_at=:verified WHERE challenge_id=:id AND verified_at IS NULL");
            $q->execute([':verified'=>$now, ':id'=>$challengeId]);
            if ($q->rowCount() !== 1) { throw new RuntimeException('Pending enrollment race'); }
            $this->pdo->commit();
            return $identityId;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            if ($e->getCode() === '23000') { return null; }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    /**
     * Legacy two-phase activation is forbidden for MySQL. Always use
     * verify_and_activate_atomic(), which owns the challenge transaction.
     */
    public function activate_once(string $challengeId, string $emailLookup, int $verifiedAt): ?string {
        throw new LogicException('Use atomic verification and activation');
    }
}
