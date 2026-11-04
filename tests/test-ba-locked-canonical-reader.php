<?php
/** BA-IDENTITY-WIZARD-15 — isolated lock release and fail-closed tests. */
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/locked-canonical-reader.php';

final class LockedReceiptLock implements BadAround_Report_Otp_Native_Lock {
    public bool $allowed=true;
    public int $acquired=0;
    public int $released=0;
    public function acquire(string $submissionId):bool{
        if (!$this->allowed)return false;
        ++$this->acquired;return true;
    }
    public function release(string $submissionId):void{++$this->released;}
}
final class LockedReceiptSource implements BadAround_Identity_Pre_Intake_Canonical_Reader {
    public int $reads=0;
    public bool $throws=false;
    public bool $mismatch=false;
    public function completed(string $submissionId):?array {
        ++$this->reads;
        if($this->throws)throw new RuntimeException('simulated db unavailable');
        return ['submission_id'=>$this->mismatch?'123e4567-e89b-42d3-a456-426614174099':$submissionId,
                'report_id'=>51,'event_id'=>61,'duplicate'=>false];
    }
}
function checkLock(bool $condition,string $label):void{
    if(!$condition)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$id='123e4567-e89b-42d3-a456-426614174000';
$lock=new LockedReceiptLock();$source=new LockedReceiptSource();
$reader=new BadAround_Identity_Locked_Canonical_Reader($lock,$source);
checkLock($reader->completed($id)===['submission_id'=>$id,'report_id'=>51,'event_id'=>61,'duplicate'=>false],
    'canonical receipt returned under native lock');
checkLock($lock->acquired===1 && $lock->released===1,
    'native lock released after successful read');
$lock->allowed=false;
checkLock($reader->completed($id)===null && $source->reads===1,
    'lock contention fails closed before canonical read');
checkLock($lock->released===1,'unacquired lock never released');
$lock->allowed=true;$source->mismatch=true;
checkLock($reader->completed($id)===null,'wrong submission receipt rejected');
$source->mismatch=false;$source->throws=true;
checkLock($reader->completed($id)===null,'canonical database exception fails closed');
checkLock($lock->acquired===$lock->released,'all acquired locks released');
$prior=$source->reads;
checkLock($reader->completed('invalid')===null,'bad UUID rejected');
checkLock($source->reads===$prior,'invalid UUID never queried');
echo 'ALL_LOCKED_CANONICAL_READER_TESTS_PASS'.PHP_EOL;
