<?php
/** Private QA report ownership boundary. No hooks, routes or DDL on include. */
declare(strict_types=1);
require_once __DIR__.'/wordpress-native-receipt-proof.php';
require_once __DIR__.'/mysql-ownership-store.php';
require_once __DIR__.'/report-otp-wordpress-reconciler.php';
require_once __DIR__.'/pre-intake-schema-candidate.php';

final class BadAround_Identity_Mysql_Report_Creator_Grant implements BadAround_Identity_Ownership_Grant {
    public function __construct(
        private PDO $db, private string $prefix,
        private BadAround_Report_Otp_Native_Lock $lock
    ) {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix))
            throw new InvalidArgumentException('Invalid WordPress prefix');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function grant_creator(string $identityId,string $resourceKind,int $resourceId,int $now):bool {
        // Never commit or roll back a caller's transaction. This boundary owns
        // its transaction and re-reads all authorization facts inside it.
        if ($resourceKind!=='report' || $resourceId<=0 || $now<=0
            || !preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identityId)
            || $this->db->inTransaction()) return false;
        $locked=false;$started=false;$submission=null;
        $p=$this->prefix;
        try {
            // Discovery is not authorization. Re-read this row under a row lock
            // after acquiring the same submission lock used by native intake.
            if (!$this->storage_ready())return false;
            $row=$this->one("SELECT submission_id FROM `{$p}ba_identity_pre_intake`
                WHERE report_id=:report",[':report'=>$resourceId]);
            $submission=$row['submission_id']??null;
            if (!is_string($submission)
                || !BadAround_Identity_Pre_Intake_Reservation::valid_submission($submission))return false;
            $locked=$this->lock->acquire($submission);
            if (!$locked)return false;
            $this->db->beginTransaction();$started=true;
            // Locking the identity row serializes grants with revocation.
            $identity=$this->one("SELECT identity_id,verified_at,revoked_at
                FROM `{$p}ba_identities` WHERE identity_id=:identity FOR UPDATE",
                [':identity'=>$identityId]);
            if ($identity===null || $identity['identity_id']!==$identityId
                || $identity['verified_at']===null || (int)$identity['verified_at']<=0
                || $identity['revoked_at']!==null)return false;
            $pre=$this->one("SELECT * FROM `{$p}ba_identity_pre_intake`
                WHERE submission_id=:submission FOR UPDATE",[':submission'=>$submission]);
            if ($pre===null || $pre['identity_id']!==$identityId || $pre['state']!=='linked'
                || (int)$pre['report_id']!==$resourceId || (int)$pre['event_id']<=0
                || (int)$pre['created_at']<=0 || (int)$pre['created_at']>$now
                || !preg_match('/^[a-f0-9]{32}$/D',$pre['operation_id']))return false;
            $event=(int)$pre['event_id'];
            // Protect canonical rows while the proof reader validates their
            // contents. The submission lock protects native creation/replay.
            if ($this->one("SELECT id FROM `{$p}ba_reports` WHERE id=:report FOR UPDATE",
                [':report'=>$resourceId])===null
                || $this->one("SELECT ID FROM `{$p}posts` WHERE ID=:event FOR UPDATE",
                [':event'=>$event])===null)return false;
            $binding=$this->one("SELECT meta_value FROM `{$p}postmeta`
                WHERE post_id=:event AND meta_key='_ba_primary_report_id' FOR UPDATE",
                [':event'=>$event]);
            if ($binding===null || (string)$binding['meta_value']!==(string)$resourceId)return false;
            $journal=$this->one("SELECT * FROM `{$p}ba_identity_recovery`
                WHERE operation_id=:op FOR UPDATE",[':op'=>$pre['operation_id']]);
            if ($journal===null || $journal['identity_id']!==$identityId
                || (int)$journal['report_id']!==$resourceId || (int)$journal['event_id']!==$event
                || !in_array($journal['state'],['pending','bound'],true))return false;
            $proof=new BadAround_Identity_WordPress_Native_Receipt_Proof($this->db,$p);
            if (!$proof->is_new_canonical_report($resourceId,$event)
                || !$proof->created_after_reservation($resourceId,$event,(int)$pre['created_at']))return false;
            $owner=$this->one("SELECT identity_id,relation,revoked_at FROM `{$p}ba_identity_ownership`
                WHERE resource_kind='report' AND resource_id=:report FOR UPDATE",
                [':report'=>$resourceId]);
            if ($owner!==null) {
                if ($owner['identity_id']!==$identityId || $owner['relation']!=='creator'
                    || $owner['revoked_at']!==null)return false;
            } elseif ($journal['state']!=='pending'
                || !(new BadAround_Identity_Mysql_Ownership_Store(
                    $this->db,$p.'ba_identity_ownership'
                ))->grant_creator($identityId,'report',$resourceId,$now))return false;
            $this->db->commit();$started=false;
            return true;
        } catch (Throwable $error) {
            return false;
        } finally {
            if ($started && $this->db->inTransaction()) {
                try {$this->db->rollBack();}catch(Throwable $ignored){}
            }
            if ($locked && is_string($submission)) {
                try {$this->lock->release($submission);}catch(Throwable $ignored){}
            }
        }
    }

    private function storage_ready():bool {
        $names=['ba_identities','ba_identity_pre_intake','ba_identity_recovery',
            'ba_identity_ownership','ba_reports','posts','postmeta'];
        $q=$this->db->prepare("SELECT ENGINE FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name");
        foreach($names as $name) {
            $q->execute([':name'=>$this->prefix.$name]);
            if ($q->fetchColumn()!=='InnoDB')return false;
        }
        $q=$this->db->prepare("SELECT INDEX_NAME,COLUMN_NAME FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=:name AND NON_UNIQUE=0
            ORDER BY INDEX_NAME,SEQ_IN_INDEX");
        $q->execute([':name'=>$this->prefix.'ba_identity_ownership']);
        $unique=[];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)
            $unique[$row['INDEX_NAME']][]=$row['COLUMN_NAME'];
        return in_array(['resource_kind','resource_id'],$unique,true)
            && BadAround_Identity_Pre_Intake_Schema::inspect($this->db,$this->prefix);
    }

    private function one(string $sql,array $params):?array {
        $sql=preg_replace('/ FOR UPDATE$/',' LIMIT 2 FOR UPDATE',$sql,1,$replaced);
        if ($replaced===0)$sql.=' LIMIT 2';
        $q=$this->db->prepare($sql);$q->execute($params);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        return count($rows)===1?$rows[0]:null;
    }
}
