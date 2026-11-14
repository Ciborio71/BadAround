<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/durable-ownership-handoff.php';
final class DurableReceiptProof implements BadAround_Identity_Intake_Receipt {
    public bool $yes=true;
    public function is_new_canonical_report(int $reportId,int $eventId):bool {
        return $this->yes && $reportId===17 && $eventId===37;
    }
}
final class DurableGrant implements BadAround_Identity_Ownership_Grant {
    public int $writes=0;
    public bool $yes=true;
    public function grant_creator(string $identityId,string $kind,int $resourceId,int $now):bool {
        ++$this->writes;
        return $this->yes;
    }
}
final class DurableJournal implements BadAround_Identity_Recovery_Journal {
    public array $rows=[];
    public bool $allowCreate=true;
    public bool $allowTransition=true;
    public array $actions=[];
    public function create(array $intent):bool {
        $this->actions[]='journal';
        if (!$this->allowCreate || isset($this->rows[$intent['operation_id']]))return false;
        $this->rows[$intent['operation_id']]=$intent;
        return true;
    }
    public function load(string $operationId):?array {return $this->rows[$operationId]??null;}
    public function transition(string $operationId,int $revision,string $outcome,int $now):bool {
        $this->actions[]='transition';
        if (!$this->allowTransition || !isset($this->rows[$operationId]))return false;
        $row=$this->rows[$operationId];
        if(($row['revision']??0)!==$revision)return false;
        $next=BadAround_Identity_Ownership_Recovery_Policy::transition($row,$outcome,$now);
        $next['revision']=$revision+1;
        $this->rows[$operationId]=$next;
        return true;
    }
}
function checkHandoff(bool $value,string $label):void {
    if (!$value)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$store=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($store,str_repeat('k',32));
$identity='identity_12345678';
$token=$sessions->issue($identity,1000);
$active=new BadAround_Identity_Active_Memory_Status();
$active->active[$identity]=true;
$proof=new DurableReceiptProof();
$writer=new DurableGrant();
$journal=new DurableJournal();
$handoff=new BadAround_Identity_Durable_Ownership_Handoff($sessions,$active,$proof,$writer,$journal);
$result=['status'=>'success','report_id'=>17,'event_id'=>37,'duplicate'=>false,'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false]];
$id=str_repeat('a',32);
checkHandoff($handoff->handoff($token,$result,$id,1050)==='confirmed','first new report confirmed');
checkHandoff($writer->writes===1 && $journal->load($id)['state']==='bound','durable journal marked bound');
checkHandoff($journal->actions===['journal','transition'],'journal creation precedes grant confirmation');
checkHandoff($handoff->handoff($token,$result,$id,1051)==='pending','replay never confirms');
checkHandoff($writer->writes===1,'replay never grants twice');
$journal->allowCreate=false;
checkHandoff($handoff->handoff($token,$result,str_repeat('b',32),1052)==='pending','failed journal write refuses grant');
checkHandoff($writer->writes===1,'failed journal write performs no grant');
$journal->allowCreate=true;
$writer->yes=false;
checkHandoff($handoff->handoff($token,$result,str_repeat('c',32),1053)==='pending','failed grant remains pending');
checkHandoff($journal->load(str_repeat('c',32))['state']==='pending','pending intent survives failed grant');
$writer->yes=true;
$journal->allowTransition=false;
checkHandoff($handoff->handoff($token,$result,str_repeat('d',32),1054)==='pending','failed post-grant CAS is not confirmed');
checkHandoff($journal->load(str_repeat('d',32))['state']==='pending','ambiguous post-grant operation remains pending');
$journal->allowTransition=true;
$before=$writer->writes;
checkHandoff($handoff->handoff(null,$result,str_repeat('e',32),1055)==='ineligible','missing session denied');
checkHandoff($handoff->handoff($token,array_replace($result,['duplicate'=>true]),str_repeat('f',32),1056)==='ineligible','duplicate denied');
$active->active[$identity]=false;
checkHandoff($handoff->handoff($token,$result,str_repeat('1',32),1057)==='ineligible','revoked identity denied');
checkHandoff($writer->writes===$before,'invalid cases never grant');
echo 'ALL_DURABLE_OWNERSHIP_HANDOFF_TESTS_PASS'.PHP_EOL;
