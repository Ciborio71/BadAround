<?php
/**
 * BA-IDENTITY-01C: dormant MySQL enrollment repository for isolated CI.
 * Requires provisioned InnoDB tables; NOT bootstrapped by BadAround Core.
 */
declare(strict_types=1);
require_once __DIR__ . '/enrollment-policy.php';

final class BadAround_Identity_Mysql_Enrollment_Store implements BadAround_Identity_Pending_Enrollment_Store {
    public function __construct(
        private PDO $pdo,
        private string $pendingTable,
        private string $identityTable,
        private string $encryptionKey
    ) {
        foreach ([$pendingTable, $identityTable] as $table) {
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
     * Atomic activation within this database: lock pending row, verify unused,
     * insert identity with UNIQUE email_lookup, mark consumed, then commit.
     * OTP is consumed separately by the verifier before this transaction.
     */
    public function activate_once(string $challengeId, string $emailLookup, int $verifiedAt): ?string {
        $this->pdo->beginTransaction();
        try {
            $query = $this->pdo->prepare("SELECT email_lookup,protected_profile,verified_at
                FROM `{$this->pendingTable}` WHERE challenge_id=:id FOR UPDATE");
            $query->execute([':id'=>$challengeId]);
            $pending = $query->fetch(PDO::FETCH_ASSOC);
            if (!$pending || $pending['verified_at'] !== null
                || !hash_equals($pending['email_lookup'], $emailLookup)) {
                $this->pdo->rollBack();
                return null;
            }
            $id = 'identity_' . bin2hex(random_bytes(16));
            $insert = $this->pdo->prepare("INSERT INTO `{$this->identityTable}`
                (identity_id,email_lookup,protected_profile,verified_at,revoked_at)
                VALUES (:id,:lookup,:profile,:verified,NULL)");
            $insert->execute([
                ':id'=>$id, ':lookup'=>$emailLookup,
                ':profile'=>$pending['protected_profile'], ':verified'=>$verifiedAt
            ]);
            $update = $this->pdo->prepare("UPDATE `{$this->pendingTable}`
                SET verified_at=:verified WHERE challenge_id=:id AND verified_at IS NULL");
            $update->execute([':verified'=>$verifiedAt, ':id'=>$challengeId]);
            if ($update->rowCount() !== 1) { throw new RuntimeException('Activation race'); }
            $this->pdo->commit();
            return $id;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            // Duplicate verified email or identity id: fail closed, never reassign.
            if ($e->getCode() === '23000') { return null; }
            throw $e;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }
}
