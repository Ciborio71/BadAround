<?php
/**
 * BA-REPORT-IDENTITY-04: transactional CAS reservation outbox.
 * Only callable by a trusted backend holding the native submission lock.
 * Does NOT call WordPress, create reports, send mail or publish.
 */
declare(strict_types=1);
require_once __DIR__.'/report-otp-reservation-policy.php';

final class BadAround_Report_Otp_Outbox_Store {
    public function __construct(private PDO $db,private string $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) {
            throw new InvalidArgumentException('Invalid outbox table');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    }

    public function create(array $trusted):bool {
        if (($trusted['state']??null)!==BadAround_Report_Otp_Reservation_Policy::VERIFIED
            || ($trusted['version']??null)!==0 || !preg_match('/^[a-f0-9]{32}$/D',(string)($trusted['operation_id']??''))
            || !preg_match('/^[a-f0-9]{32}$/D',(string)($trusted['challenge_id']??''))) {
            throw new InvalidArgumentException('Untrusted operation');
        }
        $stmt=$this->db->prepare("INSERT INTO `{$this->table}`
          (operation_id,challenge_id,submission_id,email_lookup,state,revision,attempts,created_at)
          VALUES (:op,:challenge,:submission,:email,'verified',0,0,:created)");
        try {
            $stmt->execute([
                ':op'=>$trusted['operation_id'],':challenge'=>$trusted['challenge_id'],
                ':submission'=>$trusted['submission_id'],':email'=>$trusted['email_lookup'],
                ':created'=>$trusted['created_at']
            ]);
            return $stmt->rowCount()===1;
        } catch(PDOException $error) {
            if ($error->getCode()==='23000') return false;
            throw $error;
        }
    }

    public function read(string $operationId):?array {
        if (!preg_match('/^[a-f0-9]{32}$/D',$operationId))return null;
        $q=$this->db->prepare("SELECT * FROM `{$this->table}` WHERE operation_id=:id LIMIT 1");
        $q->execute([':id'=>$operationId]);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        return $row?self::normalize($row):null;
    }

    /**
     * One atomic state/version transition. Never resets a reserved operation
     * to verified: crash recovery must inspect canonical data first.
     */
    public function transition(string $operationId,int $expectedVersion,array $next):bool {
        if (!preg_match('/^[a-f0-9]{32}$/D',$operationId)
            || $expectedVersion<0 || ($next['version']??null)!==$expectedVersion+1
            || !in_array($next['state']??null,['reserved','linked','needs_review'],true)) {
            return false;
        }
        $previous=['reserved'=>'verified','linked'=>'reserved','needs_review'=>'reserved'][$next['state']];
        $stmt=$this->db->prepare("UPDATE `{$this->table}`
            SET state=:state,revision=revision+1,
                reserved_at=:reserved,report_id=:report,event_id=:event,
                resolved_at=:resolved,attempts=:attempts
            WHERE operation_id=:id AND revision=:expected AND state=:previous");
        $stmt->bindValue(':state',$next['state'],PDO::PARAM_STR);
        $stmt->bindValue(':reserved',$next['reserved_at']??null,
            ($next['reserved_at']??null)===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $stmt->bindValue(':report',$next['report_id']??null,
            ($next['report_id']??null)===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $stmt->bindValue(':event',$next['event_id']??null,
            ($next['event_id']??null)===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $stmt->bindValue(':resolved',$next['resolved_at']??null,
            ($next['resolved_at']??null)===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $stmt->bindValue(':attempts',(int)($next['attempts']??0),PDO::PARAM_INT);
        $stmt->bindValue(':id',$operationId,PDO::PARAM_STR);
        $stmt->bindValue(':expected',$expectedVersion,PDO::PARAM_INT);
        $stmt->bindValue(':previous',$previous,PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount()===1;
    }

    private static function normalize(array $row):array {
        $ints=['revision','attempts','created_at'];
        foreach($ints as $key)$row[$key]=(int)$row[$key];
        foreach(['reserved_at','report_id','event_id','resolved_at'] as $key) {
            $row[$key]=$row[$key]===null?null:(int)$row[$key];
        }
        $row['version']=$row['revision'];
        return $row;
    }
}
