<?php
/** Dormant shared-MySQL write quota for authenticated community sessions.
 * Stores HMAC digests only; no raw bearer/session tokens, IPs or emails.
 * Controller must still validate session/CSRF/Origin and actual identity.
 */
declare(strict_types=1);
require_once __DIR__.'/wordpress-community-rest-adapter.php';
final class BadAround_Identity_Mysql_Community_Budget implements BadAround_Identity_Community_Rest_Budget {
    public function __construct(
        private PDO $db, private string $table, private string $serverKey
    ) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)
            || strlen($serverKey)<32) { throw new InvalidArgumentException('Invalid budget config'); }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function consume(string $sessionToken,string $kind,int $now):bool {
        if (!preg_match('/^[a-f0-9]{64}$/D',$sessionToken)
            || !in_array($kind,['comment','revision'],true) || $now<=0) {
            return false;
        }
        if ($this->db->inTransaction()) { return false; }
        $limit=$kind==='comment'?6:3;
        $window=900;
        $start=intdiv($now,$window)*$window;
        $digest=hash_hmac('sha256','BA-community:'.$kind.':'.$sessionToken,$this->serverKey);
        try {
            $this->db->beginTransaction();
            $stmt=$this->db->prepare("INSERT IGNORE INTO `{$this->table}`
                (kind,bucket,window_start,hits,expires_at)
                VALUES (:kind,:bucket,:start,0,:expiry)");
            $stmt->execute([':kind'=>$kind,':bucket'=>$digest,
                ':start'=>$start,':expiry'=>$start+$window]);
            $stmt=$this->db->prepare("SELECT hits FROM `{$this->table}`
                WHERE kind=:kind AND bucket=:bucket AND window_start=:start FOR UPDATE");
            $stmt->execute([':kind'=>$kind,':bucket'=>$digest,':start'=>$start]);
            $hits=$stmt->fetchColumn();
            if ($hits===false || (int)$hits >= $limit) {
                $this->db->rollBack(); return false;
            }
            $stmt=$this->db->prepare("UPDATE `{$this->table}` SET hits=hits+1
                WHERE kind=:kind AND bucket=:bucket AND window_start=:start");
            $stmt->execute([':kind'=>$kind,':bucket'=>$digest,':start'=>$start]);
            if ($stmt->rowCount()!==1) { throw new RuntimeException('Quota race'); }
            $this->db->commit();
            return true;
        } catch(Throwable $error) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            return false;
        }
    }
}
