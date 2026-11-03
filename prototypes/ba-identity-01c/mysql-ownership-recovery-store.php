<?php
/**
 * Dormant persistence for ownership recovery intents; disposable MySQL CI only.
 * Caller must validate verified session and canonical receipt BEFORE insert.
 * Never activate as WordPress route or infer ownership from supplied email.
 */
declare(strict_types=1);
require_once __DIR__.'/ownership-recovery-policy.php';
final class BadAround_Identity_Mysql_Ownership_Recovery_Store {
    public function __construct(private PDO $db,private string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) {
            throw new InvalidArgumentException('Invalid table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function create(array $intent): bool {
        if (($intent['state'] ?? null)!=='pending'
            || !preg_match('/^[a-f0-9]{32,64}$/D',(string)($intent['operation_id']??''))
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',(string)($intent['identity_id']??''))
            || !is_int($intent['report_id']??null) || $intent['report_id']<=0
            || !is_int($intent['event_id']??null) || $intent['event_id']<=0
            || ($intent['attempts']??null)!==0
            || !is_int($intent['created_at']??null) || $intent['created_at']<=0) {
            throw new InvalidArgumentException('Invalid recovery intent');
        }
        try {
            $stmt=$this->db->prepare("INSERT INTO `{$this->table}`
                (operation_id,identity_id,report_id,event_id,state,attempts,created_at,resolved_at,revision)
                VALUES (:op,:identity,:report,:event,'pending',0,:created,NULL,0)");
            $stmt->execute([
                ':op'=>$intent['operation_id'],':identity'=>$intent['identity_id'],
                ':report'=>$intent['report_id'],':event'=>$intent['event_id'],
                ':created'=>$intent['created_at']
            ]);
            return $stmt->rowCount()===1;
        } catch(PDOException $e) {
            if ($e->getCode()==='23000') { return false; }
            throw $e;
        }
    }
    public function load(string $operationId): ?array {
        if (!preg_match('/^[a-f0-9]{32,64}$/D',$operationId)) { return null; }
        $stmt=$this->db->prepare("SELECT * FROM `{$this->table}` WHERE operation_id=:op LIMIT 1");
        $stmt->execute([':op'=>$operationId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if ($row===false) { return null; }
        foreach(['report_id','event_id','attempts','created_at','revision'] as $key) {
            $row[$key]=(int)$row[$key];
        }
        $row['resolved_at']=$row['resolved_at']===null?null:(int)$row['resolved_at'];
        return $row;
    }
    /** Optimistic CAS: last writer cannot overwrite a resolved request. */
    public function transition(string $operationId,int $expectedRevision,string $outcome,int $now): bool {
        $old=$this->load($operationId);
        if ($old===null || $old['revision']!==$expectedRevision) { return false; }
        $next=BadAround_Identity_Ownership_Recovery_Policy::transition($old,$outcome,$now);
        $stmt=$this->db->prepare("UPDATE `{$this->table}`
            SET state=:state,attempts=:attempts,resolved_at=:resolved,revision=revision+1
            WHERE operation_id=:op AND revision=:revision AND state='pending'");
        $stmt->bindValue(':state',$next['state'],PDO::PARAM_STR);
        $stmt->bindValue(':attempts',$next['attempts'],PDO::PARAM_INT);
        if ($next['resolved_at']===null) {
            $stmt->bindValue(':resolved',null,PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':resolved',$next['resolved_at'],PDO::PARAM_INT);
        }
        $stmt->bindValue(':op',$operationId,PDO::PARAM_STR);
        $stmt->bindValue(':revision',$expectedRevision,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount()===1;
    }
}
