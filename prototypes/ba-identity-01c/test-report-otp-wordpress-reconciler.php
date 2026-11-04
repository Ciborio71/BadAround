<?php
/** BA-REPORT-IDENTITY-06: no public routes, isolated reconciliation tests. */
declare(strict_types=1);
require __DIR__.'/report-otp-wordpress-reconciler.php';
function assertion(bool $ok,string $name):void{
 if(!$ok)throw new RuntimeException('FAIL '.$name);
 echo 'PASS '.$name.PHP_EOL;
}
class LockFake implements BadAround_Report_Otp_Native_Lock {
 public bool $available=true;
 public int $acquired=0;
 public int $released=0;
 public function acquire(string $submissionId):bool {
  if(!$this->available)return false;
  $this->acquired++;return true;
 }
 public function release(string $submissionId):void{$this->released++;}
}
class OutboxFake implements BadAround_Report_Otp_Reconcile_Outbox {
 public array $row;
 public int $linked=0;
 public function __construct(string $uuid){
  $this->row=['submission_id'=>$uuid,'state'=>'reserved'];
 }
 public function read(string $operationId):?array{return $this->row;}
 public function link(string $operationId,array $canonical,int $now):bool {
  if($this->row['state']!=='reserved')return false;
  $this->row['state']='linked';$this->linked++;return true;
 }
 public function confirmed(string $operationId):bool {
  return $this->row['state']==='linked';
 }
}
class ReaderFake implements BadAround_Report_Otp_Reconcile_Reader {
 public ?array $receipt=null;
 public function completed(string $submissionId):?array{return $this->receipt;}
}
$uuid='12345678-1234-4234-8234-123456789abc';$op=str_repeat('a',32);
$lock=new LockFake();$outbox=new OutboxFake($uuid);$reader=new ReaderFake();
$subject=new BadAround_Report_Otp_WordPress_Reconciler($lock,$outbox,$reader);
$now=1762308000;
assertion($subject->reconcile($op,$uuid,$now)==='pending','no report means no confirmation');
assertion($lock->acquired===1&&$lock->released===1,'lock always released');
$lock->available=false;
assertion($subject->reconcile($op,$uuid,$now+1)==='pending','contention fails closed');
$lock->available=true;
$reader->receipt=['submission_id'=>$uuid,'report_id'=>11,'event_id'=>22,'duplicate'=>false];
assertion($subject->reconcile($op,$uuid,$now+2)==='linked','canonical report confirms');
assertion($outbox->linked===1,'transition occurs exactly once');
assertion($subject->reconcile($op,$uuid,$now+3)==='linked','linked retry idempotent');
assertion($outbox->linked===1,'no duplicate link');
assertion($subject->reconcile($op,'87654321-4321-4234-8234-123456789abc',$now+4)==='unavailable',
 'wrong submission denied');
$second=new OutboxFake($uuid);$other=new BadAround_Report_Otp_WordPress_Reconciler($lock,$second,$reader);
$reader->receipt['duplicate']=true;
assertion($other->reconcile($op,$uuid,$now+5)==='unavailable','duplicate canonical denied');
assertion($second->linked===0,'bad receipt cannot link');
$reader->receipt['duplicate']=false;
$reader->receipt['submission_id']='87654321-4321-4234-8234-123456789abc';
assertion($other->reconcile($op,$uuid,$now+6)==='unavailable','wrong receipt UUID denied');
$second->row['state']='needs_review';
assertion($other->reconcile($op,$uuid,$now+7)==='pending','quarantined intent not auto-linked');
echo "ALL_REPORT_WORDPRESS_RECONCILER_TESTS_PASS\n";
