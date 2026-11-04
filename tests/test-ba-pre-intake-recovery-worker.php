<?php
/** Isolated recovery worker tests. No WP or production database. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/pre-intake-recovery-worker.php';
function verify_worker(bool $v,string $s):void {if(!$v)throw new RuntimeException('FAIL '.$s);echo 'PASS '.$s.PHP_EOL;}
final class WorkerLock implements BadAround_Report_Otp_Native_Lock {
    public bool $allowed=true;public int $acquired=0;public int $released=0;
    public function acquire(string $id):bool {
        if (!$this->allowed)return false;
        ++$this->acquired;return true;
    }
    public function release(string $id):void {++$this->released;}
}
final class WorkerPre implements BadAround_Identity_Pre_Intake_Recovery_Reader {
    public array $rows=[];public bool $allowLink=true;public int $links=0;
    public function find_by_submission(string $id):?array{return $this->rows[$id]??null;}
    public function link(string $op,string $sub,int $report,int $event,int $now):bool{
        if(!$this->allowLink || ($this->rows[$sub]['operation_id']??null)!==$op
            || ($this->rows[$sub]['state']??null)!=='reserved')return false;
        $this->rows[$sub]['state']='linked';$this->rows[$sub]['report_id']=$report;
        $this->rows[$sub]['event_id']=$event;++$this->links;return true;
    }
}
final class WorkerCanonical implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public bool $exists=true;
    public function completed(string $submissionId):?array {
        return $this->exists?['submission_id'=>$submissionId,'report_id'=>71,'event_id'=>81,'duplicate'=>false]:null;
    }
}
final class WorkerStatus implements BadAround_Identity_Active_Status {
    public bool $ok=true;
    public function is_active_verified(string $id):bool{return $this->ok;}
}
final class WorkerReceipt implements BadAround_Identity_Intake_Receipt {
    public function is_new_canonical_report(int $r,int $e):bool{return $r===71 && $e===81;}
}
final class WorkerOwners implements BadAround_Identity_Recovery_Owner_Reader {
    public ?array $value=null;
    public function find_for_report(int $r):?array{return $this->value;}
}
final class WorkerGrants implements BadAround_Identity_Ownership_Grant {
    public int $attempts=0;public bool $ok=true;
    public function __construct(private WorkerOwners $owners){}
    public function grant_creator(string $id,string $kind,int $resource,int $now):bool {
        ++$this->attempts;
        if(!$this->ok)return false;
        $this->owners->value=['identity_id'=>$id,'resource_kind'=>$kind,'resource_id'=>$resource,
            'relation'=>'creator','revoked_at'=>null];return true;
    }
}
final class WorkerJournal implements BadAround_Identity_Recovery_Journal {
    public array $rows=[]; public bool $cas=true;
    public function create(array $intent):bool{
        if(isset($this->rows[$intent['operation_id']]))return false;
        $intent['revision']=0;$this->rows[$intent['operation_id']]=$intent;return true;
    }
    public function load(string $id):?array{return $this->rows[$id]??null;}
    public function transition(string $id,int $revision,string $outcome,int $now):bool{
        if(!$this->cas || !isset($this->rows[$id])||$this->rows[$id]['revision']!==$revision)return false;
        $r=&$this->rows[$id];++$r['revision'];++$r['attempts'];
        $r['state']=$outcome==='granted'?'bound':
            ($outcome==='conflict'||$r['attempts']>=5?'needs_review':'pending');
        return true;
    }
}
$submission='123e4567-e89b-42d3-a456-426614174000';
$op=str_repeat('a',32);
$identity='identity_12345678';
$lock=new WorkerLock();$p=new WorkerPre();$canonical=new WorkerCanonical();$active=new WorkerStatus();
$owners=new WorkerOwners();$grants=new WorkerGrants($owners);$journal=new WorkerJournal();
$worker=new BadAround_Identity_Pre_Intake_Recovery_Worker(
    $lock,$p,$canonical,$active,$owners,$journal,$grants,new WorkerReceipt()
);
$base=['submission_id'=>$submission,'operation_id'=>$op,'identity_id'=>$identity,
    'state'=>'reserved','created_at'=>100,'report_id'=>null,'event_id'=>null];
$p->rows[$submission]=$base;
$canonical->exists=false;
verify_worker($worker->recover($submission,110)==='pending','missing canonical event remains pending');
verify_worker($p->links===0 && $grants->attempts===0,'no grant without canonical event');
$canonical->exists=true;
verify_worker($worker->recover($submission,111)==='linked','reserved crash recovered and owned');
verify_worker($p->links===1 && $grants->attempts===1,'single pre-intake link and owner grant');
verify_worker($worker->recover($submission,112)==='linked','replay reads bound owner without duplicate grant');
verify_worker($grants->attempts===1,'replay cannot create duplicate grant');
$p->rows[$submission]=$base;$journal->rows=[];$owners->value=null;$active->ok=false;
verify_worker($worker->recover($submission,113)==='needs_review','revoked identity refused');
verify_worker($grants->attempts===1,'revoked identity never granted');
$active->ok=true;$owners->value=[
    'identity_id'=>'someone_else','resource_kind'=>'report','resource_id'=>71,
    'relation'=>'creator','revoked_at'=>null
];$p->rows[$submission]=$base;
verify_worker($worker->recover($submission,114)==='needs_review','foreign owner not overridden');
verify_worker($grants->attempts===1,'owner conflict never grants');
$owners->value=null;$p->rows[$submission]=$base;$journal->rows=[];
$grants->ok=false;
verify_worker($worker->recover($submission,115)==='pending','grant failure is pending');
verify_worker($journal->rows[$op]['state']==='pending','grant failure journal persisted');
$grants->ok=true;
verify_worker($worker->recover($submission,116)==='linked','retry safely resolves original pending intent');
verify_worker($worker->recover('not-a-uuid',117)==='unavailable','malformed UUID rejected');
verify_worker($lock->acquired===$lock->released,'all acquired locks released');
$lock->allowed=false;
verify_worker($worker->recover($submission,118)==='pending','unavailable native lock prevents recovery');
verify_worker($lock->acquired===$lock->released,'failed lock acquisition never releases another worker lock');
echo 'ALL_PRE_INTAKE_RECOVERY_WORKER_TESTS_PASS'.PHP_EOL;
