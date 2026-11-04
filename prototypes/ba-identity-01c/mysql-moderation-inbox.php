<?php
/** Dormant encrypted moderation inbox for comments and revisions.
 * No WordPress hooks, migrations, REST routes or public writes.
 * Caller must authenticate the identity and authorize moderator separately.
 */
declare(strict_types=1);
final class BadAround_Identity_Moderation_Inbox {
    public function __construct(private PDO $db, private string $table, private string $key) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table) || strlen($key)!==32) {
            throw new InvalidArgumentException('Invalid configuration');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function submit(string $id,string $kind,string $identity,int $resource,array $intent,int $now): bool {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)
            || !in_array($kind,['comment','revision'],true)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identity)
            || $resource<=0 || $now<=0
            || ($intent['identity_id']??null)!==$identity
            || ($intent['moderation_status']??null)!=='pending'
            || ($intent[$kind==='comment'?'event_id':'report_id']??null)!==$resource
            || ($kind==='revision' && ($intent['public_mutation']??null)!==false)) {
            throw new InvalidArgumentException('Invalid trusted intent');
        }
        $nonce=random_bytes(12); $tag='';
        $cipher=openssl_encrypt(json_encode($intent,JSON_THROW_ON_ERROR),'aes-256-gcm',
            $this->key,OPENSSL_RAW_DATA,$nonce,$tag);
        if ($cipher===false) { throw new RuntimeException('Encryption failed'); }
        try {
            $stmt=$this->db->prepare("INSERT INTO `{$this->table}`
                (intent_id,kind,identity_id,resource_id,payload,nonce,tag,state,version,created_at)
                VALUES (:id,:kind,:identity,:resource,:payload,:nonce,:tag,'pending',0,:now)");
            foreach([':id'=>$id,':kind'=>$kind,':identity'=>$identity,':resource'=>$resource,
                ':payload'=>$cipher,':nonce'=>$nonce,':tag'=>$tag,':now'=>$now] as $k=>$v) {
                $stmt->bindValue($k,$v);
            }
            $stmt->execute();
            return $stmt->rowCount()===1;
        } catch(PDOException $e) {
            if ($e->getCode()==='23000') { return false; }
            throw $e;
        }
    }
    /** Read private data only in trusted server-side workflows. */
    public function load_private(string $id): ?array {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { return null; }
        $stmt=$this->db->prepare("SELECT * FROM `{$this->table}` WHERE intent_id=:id LIMIT 1");
        $stmt->execute([':id'=>$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($row===false) { return null; }
        $plain=openssl_decrypt($row['payload'],'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,
            $row['nonce'],$row['tag']);
        if ($plain===false) { throw new RuntimeException('Authenticated decryption failed'); }
        $row['intent']=json_decode($plain,true,512,JSON_THROW_ON_ERROR);
        return $row;
    }
    /** CAS transition only, never publishes content. */
    public function decide(string $id,int $version,string $decision,string $moderator,int $now): bool {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id) || $version<0 || $now<=0
            || !in_array($decision,['approved','rejected'],true)
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$moderator)) { return false; }
        $stmt=$this->db->prepare("UPDATE `{$this->table}`
            SET state=:decision,version=version+1,moderator_id=:moderator,moderated_at=:now
            WHERE intent_id=:id AND state='pending' AND version=:version AND created_at<:now");
        $stmt->execute([':decision'=>$decision,':moderator'=>$moderator,':now'=>$now,
            ':id'=>$id,':version'=>$version]);
        return $stmt->rowCount()===1;
    }
}
