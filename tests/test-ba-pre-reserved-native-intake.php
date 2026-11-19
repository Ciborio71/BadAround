<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/pre-reserved-native-intake.php';
function assertRes(bool $ok,string $message):void {
    if (!$ok)throw new RuntimeException('FAIL '.$message);
    echo 'PASS '.$message.PHP_EOL;
}
final class PreTestStore implements BadAround_Identity_Pre_Intake_Store {
    public array $rows=[];
    public array $order=[];
    public function reserve(string $op,string $submission,string $identity,int $now):bool {
        $this->order[]='reserve';
        if(isset($this->rows[$submission]))return false;
        $this->rows[$submission]=['operation_id'=>$op,'submission_id'=>$submission,
            'identity_id'=>$identity,'state'=>'reserved'];
        return true;
    }
    public function find_by_submission(string $submission):?array{return $this->rows[$submission]??null;}
    public function link(string $op,string $submission,int $report,int $event,int $now):bool {
        $this->order[]='link';
        if(($this->rows[$submission]['operation_id']??null)!==$op)return false;
        $this->rows[$submission]['state']='linked';
        return true;
    }
}
final class PreTestReader implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public bool $yes=true;
    public string $submission;
    public function completed(string $submissionId):?array {
        if(!$this->yes)return null;
        return ['submission_id'=>$submissionId,'report_id'=>61,'event_id'=>71,'duplicate'=>false];
    }
}
final class PreTestReceipt implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $reportId,int $eventId):bool {
        return $reportId===61 && $eventId===71;
    }
}
final class PreTestOwner implements BadAround_Identity_Ownership_Grant {
    public int $count=0;
    public function grant_creator(string $identity,string $kind,int $id,int $now):bool {
        ++$this->count;
        return true;
    }
}
final class PreTestJournal implements BadAround_Identity_Recovery_Journal {
    public array $intents=[];
    public function create(array $intent):bool {$this->intents[]=$intent;return true;}
    public function load(string $id):?array{return null;}
    public function transition(string $id,int $revision,string $outcome,int $now):bool{return true;}
}
$sessionStore=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($sessionStore,str_repeat('K',32));
$identity='identity_12345678';$cookie=$sessions->issue($identity,1000);
$status=new BadAround_Identity_Active_Memory_Status();
$status->active[$identity]=true;
$store=new PreTestStore();$reader=new PreTestReader();
$writer=new PreTestOwner();
$service=new BadAround_Identity_Pre_Reserved_Intake(
    new BadAround_Identity_Native_Intake_Gate($sessions,$status),
    new BadAround_Identity_Pre_Intake_Reservation($store),
    $reader,
    new BadAround_Identity_Durable_Ownership_Handoff(
        $sessions,$status,new PreTestReceipt(),$writer,new PreTestJournal()
    )
);
$submission='123e4567-e89b-42d3-a456-426614174000';
$called=0;
$submit=static function()use(&$called,$store,$submission):array {
    ++$called;
    $store->order[]='native';
    return ['status'=>'success','submission_id'=>$submission,
        'report_id'=>61,'event_id'=>71,'duplicate'=>false,
        'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false]];
};
assertRes($service->execute(null,$submission,$submit,1010)['state']==='denied','anonymous never reaches native');
assertRes($called===0,'no unauthorized persistence');
assertRes($service->execute($cookie,$submission,$submit,1011)['state']==='owner_confirmed','verified intake confirmed');
assertRes($store->order===['reserve','native','link'],'durable reservation precedes native persistence and canonical link');
assertRes($writer->count===1,'new owner grant once');
assertRes($service->execute($cookie,$submission,$submit,1012)['state']==='conflict','replay cannot resubmit same UUID');
assertRes($called===1,'no duplicate native submission');
$other='123e4567-e89b-42d3-a456-426614174001';
assertRes($service->execute($cookie,$other,static function(){throw new RuntimeException('crash');},1013)['state']==='pending','crashed native submit remains recoverable');
assertRes(($store->rows[$other]['state']??null)==='reserved','crash leaves reservation');
$status->active[$identity]=false;
$previous=$called;
assertRes($service->execute($cookie,'123e4567-e89b-42d3-a456-426614174002',$submit,1014)['state']==='denied','revoked identity rejected');
assertRes($called===$previous,'revoked identity cannot persist');
echo 'ALL_PRE_RESERVED_NATIVE_INTAKE_TESTS_PASS'.PHP_EOL;
