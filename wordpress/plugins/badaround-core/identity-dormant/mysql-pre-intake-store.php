<?php
/**
 * BA-IDENTITY-WIZARD-07B: disposable-DB-capable MySQL outbox adapter.
 * Do not invoke against production without reviewed schema migration.
 * Unique submission_id enforces one verified creator reservation.
 * No hooks, SQL DDL, live database actions or routes on inclusion.
 */
declare(strict_types=1);
require_once __DIR__ . '/pre-intake-reservation.php';

final class BadAround_Identity_Mysql_Pre_Intake_Store implements BadAround_Identity_Pre_Intake_Store {
    public function __construct(private PDO $db,private string $table) {
        if (preg_match('/^[A-Za-z0-9_]{1,128}$/D',$table)!==1)
            throw new InvalidArgumentException('Invalid table');
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }
    public function reserve(string $op,string $submission,string $identity,int $now):bool {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_operation($op)
            || !BadAround_Identity_Pre_Intake_Reservation::valid_submission($submission)
            || preg_match('/^[A-Za-z0-9_-]{8,128}$/D',$identity)!==1 || $now<=0) return false;
        try {
            $stmt=$this->db->prepare("INSERT INTO `{$this->table}`
                (operation_id,submission_id,identity_id,state,created_at,revision)
                VALUES (:op,:submission,:identity,'reserved',:created,0)");
            $stmt->execute([':op'=>$op,':submission'=>$submission,':identity'=>$identity,':created'=>$now]);
            return $stmt->rowCount()===1;
        } catch (PDOException $error) {
            if($error->getCode()==='23000')return false;
            throw $error;
        }
    }
    public function find_by_submission(string $submission):?array {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_submission($submission))return null;
        $q=$this->db->prepare("SELECT operation_id,submission_id,identity_id,state,
                report_id,event_id,created_at,linked_at,revision
            FROM `{$this->table}` WHERE submission_id=:submission LIMIT 1");
        $q->execute([':submission'=>$submission]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row)return null;
        foreach(['report_id','event_id','linked_at'] as $key)
            $row[$key]=$row[$key]===null?null:(int)$row[$key];
        $row['revision']=(int)$row['revision'];
        $row['created_at']=(int)$row['created_at'];
        return $row;
    }
    public function link(string $op,string $submission,int $report,int $event,int $now):bool {
        if (!BadAround_Identity_Pre_Intake_Reservation::valid_operation($op)
            || !BadAround_Identity_Pre_Intake_Reservation::valid_submission($submission)
            || $report<=0 || $event<=0 || $now<=0) return false;
        $q=$this->db->prepare("UPDATE `{$this->table}`
            SET state='linked',report_id=:report,event_id=:event,linked_at=:now,revision=revision+1
            WHERE operation_id=:op AND submission_id=:submission
                AND state='reserved' AND report_id IS NULL AND event_id IS NULL");
        try {
            $q->execute([':report'=>$report,':event'=>$event,':now'=>$now,
                ':op'=>$op,':submission'=>$submission]);
            return $q->rowCount()===1;
        } catch (PDOException $error) {
            // A unique canonical report already linked elsewhere is an
            // ownership conflict, never an uncaught HTTP 500 or a success.
            if ($error->getCode()==='23000') return false;
            throw $error;
        }
    }
}
