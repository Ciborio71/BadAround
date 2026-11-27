<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/ownership-recovery-reconciler.php';

function checkRecovery(bool $pass,string $label):void {
    if(!$pass)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
final class RecoveryTestJournal implements BadAround_Identity_Recovery_Journal {
    public array $rows=[]; public bool $cas=true;
    public function create(array $intent):bool{return false;}
    public function load(string $id):?array{return $this->rows[$id]??null;}
    public function transition(string $id,int $revision,string $outcome,int $now):bool{
        if(!$this->cas || !isset($this->rows[$id])
            || $this->rows[$id]['revision']!==$revision)return false;
        $row=&$this->rows[$id];$row['revision']++;$row['attempts']++;
        $row['state']=$outcome==='granted'?'bound':
            ($outcome==='conflict'||$row['attempts']>=5?'needs_review':'pending');
        if($row['state']!=='pending')$row['resolved_at']=$now;
        return true;
    }
}
final class RecoveryTestReceipt implements BadAround_Identity_Intake_Receipt {
    public bool $yes=true;
    public function is_new_canonical_report(int $report,int $event):bool{
        return $this->yes && $report===31 && $event===41;
    }
}
final class RecoveryTestStatus implements BadAround_Identity_Active_Status {
    public bool $yes=true;
    public function is_active_verified(string $identity):bool{return $this->yes;}
}
final class RecoveryTestOwners implements BadAround_Identity_Recovery_Owner_Reader {
    public ?array $owner=null;
    public function find_for_report(int $id):?array{return $this->owner;}
}
final class RecoveryTestGrant implements BadAround_Identity_Ownership_Grant {
    public int $writes=0;public bool $yes=true;
    public function __construct(private RecoveryTestOwners $owners){}
    public function grant_creator(string $identity,string $kind,int $report,int $now):bool{
        ++$this->writes;
        if(!$this->yes)return false;
        $this->owners->owner=['identity_id'=>$identity,'resource_kind'=>$kind,
            'resource_id'=>$report,'relation'=>'creator','revoked_at'=>null];
        return true;
    }
}
$id=str_repeat('a',32);$identity='identity_12345678';
$j=new RecoveryTestJournal();$c=new RecoveryTestReceipt();
$a=new RecoveryTestStatus();$o=new RecoveryTestOwners();
$g=new RecoveryTestGrant($o);
$recon=new BadAround_Identity_Ownership_Recovery_Reconciler($j,$c,$a,$o,$g);
$new=static fn()=>['operation_id'=>$id,'identity_id'=>$identity,'report_id'=>31,
    'event_id'=>41,'state'=>'pending','attempts'=>0,'created_at'=>100,
    'revision'=>0,'resolved_at'=>null];
$j->rows[$id]=$new();
checkRecovery($recon->reconcile($id,101)==='confirmed','pending grant confirmed');
checkRecovery($j->rows[$id]['state']==='bound'&&$g->writes===1,'bound CAS recorded');
checkRecovery($recon->reconcile($id,102)==='confirmed'&&$g->writes===1,
    'bound replay never grants twice');
$j->rows[$id]=$new();$o->owner=null;$g->yes=false;
checkRecovery($recon->reconcile($id,103)==='pending','failed grant stays pending');
checkRecovery($j->rows[$id]['attempts']===1,'failed grant counts retry');
$j->rows[$id]=$new();$g->yes=true;$j->cas=false;$o->owner=null;
checkRecovery($recon->reconcile($id,104)==='unavailable','failed CAS not confirmed');
$j->cas=true;$j->rows[$id]=$new();
$o->owner=['identity_id'=>'different_999','resource_kind'=>'report',
    'resource_id'=>31,'relation'=>'creator','revoked_at'=>null];
$prior=$g->writes;
checkRecovery($recon->reconcile($id,105)==='needs_review','foreign owner conflict');
checkRecovery($g->writes===$prior,'foreign owner never overwritten');
$j->rows[$id]=$new();$o->owner=null;$a->yes=false;
checkRecovery($recon->reconcile($id,106)==='needs_review','revocation quarantined');
$a->yes=true;$c->yes=false;$j->rows[$id]=$new();
checkRecovery($recon->reconcile($id,107)==='needs_review','invalid receipt quarantined');
$c->yes=true;$j->rows[$id]=$new();$j->rows[$id]['attempts']=4;$g->yes=false;
checkRecovery($recon->reconcile($id,108)==='needs_review','retry ceiling sends to review');
checkRecovery($recon->reconcile('invalid',109)==='unavailable','invalid id denied');
checkRecovery($recon->reconcile($id,0)==='unavailable','invalid time denied');
echo 'ALL_OWNERSHIP_RECOVERY_RECONCILER_TESTS_PASS'.PHP_EOL;
