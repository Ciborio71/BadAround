<?php
/**
 * BA-REPORT-IDENTITY-02: account-free, report-specific OTP persistence.
 *
 * This is an isolated adapter with NO routes or mail delivery. Caller must:
 * 1. enforce a persistent per-IP/email quota before issue(),
 * 2. persist/send only through a reviewed transactional mail adapter,
 * 3. invalidate undelivered codes,
 * 4. call claim_in_transaction() on the SAME PDO connection/transaction
 *    as canonical report persistence, COMMITTING both together.
 *
 * A successful verify() is NOT a report ownership grant or a submission.
 */
declare(strict_types=1);
require_once __DIR__.'/report-submission-verification-policy.php';

final class BadAround_Report_Otp_Store {
    private PDO $db;
    private string $table;
    private string $emailKey;
    private string $codeKey;
    public function __construct(PDO $db,string $table,string $rootKey) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table) || strlen($rootKey)<48) {
            throw new InvalidArgumentException('Invalid trusted report OTP configuration');
        }
        $this->db=$db; $this->table=$table;
        $this->emailKey=hash_hmac('sha256','BA-REPORT-OTP:email-v1',$rootKey,true);
        $this->codeKey=hash_hmac('sha256','BA-REPORT-OTP:code-v1',$rootKey,true);
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function lookup(string $email):string {
        $normalized=BadAround_Report_Verification_Policy::private_profile('Valid','Person',$email)['email'];
        return hash_hmac('sha256','report-email'.chr(0).$normalized,$this->emailKey);
    }
    private function digest(string $id,string $code):string {
        return hash_hmac('sha256','report-otp'.chr(0).$id.chr(0).$code,$this->codeKey);
    }
    /**
     * Internal-only issuance; code MUST be delivered to the normalized email
     * and MUST NEVER be included in REST JSON, logs, analytics or SQL storage.
     * A service-level quota MUST be enforced before calling.
     */
    public function issue(string $submissionId,string $email,int $now):array {
        if (!self::uuid($submissionId) || $now<=0 || $this->db->inTransaction()) {
            throw new InvalidArgumentException('Invalid intake submission');
        }
        $lookup=$this->lookup($email);
        $id=bin2hex(random_bytes(16));
        $code=str_pad((string)random_int(0,99999999),8,'0',STR_PAD_LEFT);
        $q=$this->db->prepare("INSERT INTO `{$this->table}`
            (challenge_id,submission_id,email_lookup,code_digest,purpose,
             created_at,expires_at,attempts,verified_at,consumed_at,version)
            VALUES (:id,:submission,:lookup,:digest,'report_submission',
                    :created,:expiry,0,NULL,NULL,0)");
        $q->execute([
            ':id'=>$id,':submission'=>$submissionId,':lookup'=>$lookup,
            ':digest'=>$this->digest($id,$code),':created'=>$now,
            ':expiry'=>$now+600
        ]);
        return ['challenge_id'=>$id,'delivery_code'=>$code]; // trusted mailer ONLY
    }
    /** Atomic, durable one-time verification; invalid attempts are persisted. */
    public function verify(string $challengeId,string $code,int $now):bool {
        if (!preg_match('/^[a-f0-9]{32}$/D',$challengeId) || $now<=0 ||
            $this->db->inTransaction()) {return false;}
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare("SELECT code_digest,attempts,expires_at,verified_at,
                consumed_at,purpose FROM `{$this->table}`
                WHERE challenge_id=:id FOR UPDATE");
            $q->execute([':id'=>$challengeId]);
            $row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['purpose']!=='report_submission'
                || $row['consumed_at']!==null || $row['verified_at']!==null
                || $now>=(int)$row['expires_at'] || (int)$row['attempts']>=5) {
                $this->db->rollBack();return false;
            }
            $valid=preg_match('/^[0-9]{8}$/D',$code)===1
                && hash_equals($row['code_digest'],$this->digest($challengeId,$code));
            $stmt=$this->db->prepare("UPDATE `{$this->table}`
                SET attempts=attempts+1,verified_at=:verified,version=version+1
                WHERE challenge_id=:id AND verified_at IS NULL
                    AND consumed_at IS NULL AND attempts<5");
            $stmt->execute([':id'=>$challengeId,
                ':verified'=>$valid?$now:null]);
            if ($stmt->rowCount()!==1) {throw new RuntimeException('OTP verification race');}
            $this->db->commit();
            return $valid;
        } catch(Throwable $e) {
            if ($this->db->inTransaction()){$this->db->rollBack();}
            throw $e;
        }
    }
    /**
     * Caller MUST have started the same PDO transaction used to insert the
     * canonical report. The returned receipt is INTERNAL, never browser JSON.
     * Caller commits after BOTH report and consumption have succeeded.
     * A rollback restores eligibility for a safe re-attempt.
     */
    public function claim_in_transaction(
        string $id,string $submissionId,string $expectedEmailLookup,int $now
    ):?array {
        if (!$this->db->inTransaction() || !preg_match('/^[a-f0-9]{32}$/D',$id)
            || !self::uuid($submissionId)
            || !preg_match('/^[a-f0-9]{64}$/D',$expectedEmailLookup) || $now<=0) {
            return null;
        }
        $q=$this->db->prepare("SELECT submission_id,email_lookup,purpose,
            verified_at,consumed_at,expires_at FROM `{$this->table}`
            WHERE challenge_id=:id FOR UPDATE");
        $q->execute([':id'=>$id]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['purpose']!=='report_submission'
            || !hash_equals($submissionId,$row['submission_id'])
            || !hash_equals($expectedEmailLookup,$row['email_lookup'])
            || $row['verified_at']===null || $row['consumed_at']!==null
            || $now>=(int)$row['expires_at']) {return null;}
        $update=$this->db->prepare("UPDATE `{$this->table}`
            SET consumed_at=:now,version=version+1
            WHERE challenge_id=:id AND verified_at IS NOT NULL
                AND consumed_at IS NULL AND expires_at>:now");
        $update->execute([':now'=>$now,':id'=>$id]);
        if ($update->rowCount()!==1) {return null;}
        return ['purpose'=>'report_submission',
            'email_lookup'=>$row['email_lookup'],
            'submission_id'=>$row['submission_id'],
            'verified_at'=>(int)$row['verified_at'],
            'consumed_at'=>$now,'expires_at'=>(int)$row['expires_at'],
            'duplicate'=>false];
    }
    /** Called by the delivery coordinator after failure, never on success. */
    public function invalidate_undelivered(string $id,int $now):void {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id) || $now<=0) {
            throw new InvalidArgumentException('Invalid challenge');
        }
        $q=$this->db->prepare("UPDATE `{$this->table}`
            SET consumed_at=:now,version=version+1
            WHERE challenge_id=:id AND consumed_at IS NULL");
        $q->execute([':now'=>$now,':id'=>$id]);
    }
    private static function uuid(string $id):bool {
        return preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[1-8][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
            $id
        )===1;
    }
}
