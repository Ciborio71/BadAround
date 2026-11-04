<?php
/** Isolated QA transport tests; no WordPress runtime, email or SQL. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-report-transport.php';
function qaAssert(bool $yes,string $name):void {
    if (!$yes)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
final class QaPreStore implements BadAround_Identity_Pre_Intake_Store {
    public array $rows=[];
    public function reserve(string $op,string $submission,string $identity,int $now):bool {
        if(isset($this->rows[$submission]))return false;
        $this->rows[$submission]=['operation_id'=>$op,'submission_id'=>$submission,'identity_id'=>$identity,'state'=>'reserved'];
        return true;
    }
    public function find_by_submission(string $submission):?array{return $this->rows[$submission]??null;}
    public function link(string $op,string $submission,int $report,int $event,int $now):bool {
        if(($this->rows[$submission]['operation_id']??null)!==$op)return false;
        $this->rows[$submission]['state']='linked';return true;
    }
}
final class QaCanonical implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public function completed(string $submission):?array {
        return ['submission_id'=>$submission,'report_id'=>21,'event_id'=>31,'duplicate'=>false];
    }
}
final class QaProof implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $r,int $e):bool{return $r===21&&$e===31;}
}
final class QaGrant implements BadAround_Identity_Ownership_Grant {
    public function grant_creator(string $id,string $kind,int $report,int $now):bool{return $kind==='report'&&$report===21;}
}
final class QaJournal implements BadAround_Identity_Recovery_Journal {
    public array $rows=[];
    public function create(array $row):bool {$this->rows[$row['operation_id']]=$row;return true;}
    public function load(string $op):?array{return $this->rows[$op]??null;}
    public function transition(string $op,int $revision,string $state,int $now):bool{return isset($this->rows[$op]);}
}
$sessionStore=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($sessionStore,str_repeat('K',32));
$identity='identity_12345678';$token=$sessions->issue($identity,100);
$status=new BadAround_Identity_Active_Memory_Status();$status->active[$identity]=true;
$transport=new BadAround_Identity_QA_Report_Transport(new BadAround_Identity_Pre_Reserved_Intake(
    new BadAround_Identity_Native_Intake_Gate($sessions,$status),
    new BadAround_Identity_Pre_Intake_Reservation(new QaPreStore()),
    new QaCanonical(),
    new BadAround_Identity_Durable_Ownership_Handoff($sessions,$status,new QaProof(),new QaGrant(),new QaJournal())
));
$uuid='123e4567-e89b-42d3-a456-426614174000';
$canonical=['submission_id'=>$uuid];
$calls=0;
$submit=static function()use(&$calls,$uuid):array {
    ++$calls;
    return ['status'=>'success','submission_id'=>$uuid,
        'report_id'=>21,'event_id'=>31,'duplicate'=>false,
        'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false]];
};
$cookie=[BadAround_Identity_WordPress_Session_Cookie::NAME=>$token];
$_SERVER['REMOTE_ADDR']='127.0.0.1';
qaAssert($transport->process($canonical,$submit,$cookie,101)['state']==='unavailable',
    'without flags QA transport refuses intake');
qaAssert($calls===0,'disabled transport cannot call native');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_QA_REPORTS_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);
$_SERVER['REMOTE_ADDR']='192.0.2.44';
qaAssert($transport->process($canonical,$submit,$cookie,102)['state']==='unavailable',
    'non-allowlisted peer denied');
qaAssert($calls===0,'peer denial cannot call native');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
qaAssert($transport->process($canonical,$submit,[],103)['state']==='denied',
    'missing secure cookie denied');
qaAssert($transport->process(['submission_id'=>'invalid'],$submit,$cookie,103)['state']==='denied',
    'malformed UUID denied');
qaAssert($calls===0,'invalid input cannot call native');
qaAssert($transport->process($canonical,$submit,$cookie,104)===['state'=>'owner_confirmed'],
    'valid QA peer with verified session confirms private ownership');
qaAssert($calls===1,'exactly one native submission');
qaAssert($transport->process($canonical,$submit,$cookie,105)['state']==='conflict',
    'replay is rejected without native call');
qaAssert($calls===1,'replay never duplicates native report');
echo 'ALL_WORDPRESS_QA_REPORT_TRANSPORT_TESTS_PASS'.PHP_EOL;
