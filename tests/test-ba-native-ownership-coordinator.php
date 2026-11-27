<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/native-ownership-coordinator.php';

final class ReceiptProof implements BadAround_Identity_Intake_Receipt {
    public bool $valid = true;
    public function is_new_canonical_report(int $reportId, int $eventId): bool {
        return $this->valid && $reportId === 15 && $eventId === 35;
    }
}
final class OwnershipWriter implements BadAround_Identity_Ownership_Grant {
    public array $writes = [];
    public bool $accept = true;
    public function grant_creator(string $identityId,string $kind,int $resourceId,int $now):bool {
        $this->writes[] = [$identityId,$kind,$resourceId,$now];
        return $this->accept;
    }
}
function assert_ownership(bool $ok,string $label):void {
    if (!$ok) throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
$store=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($store,str_repeat('K',32));
$identity='identity_test_123';
$secret=$sessions->issue($identity,1000);
$status=new BadAround_Identity_Active_Memory_Status();
$status->active[$identity]=true;
$proof=new ReceiptProof();
$writer=new OwnershipWriter();
$coordinator=new BadAround_Identity_Native_Ownership_Coordinator(
    new BadAround_Identity_Post_Intake_Binder($proof,$writer,$sessions,$status)
);
$result=[
    'status'=>'success','report_id'=>15,'event_id'=>35,
    'duplicate'=>false,'persistence'=>[
        'completed'=>true,'duplicate'=>false,'recovered'=>false
    ]
];
assert_ownership($coordinator->handle($secret,$result,1100)==='confirmed',
    'verified session and canonical new receipt confirms creator');
assert_ownership(count($writer->writes)===1 &&
    $writer->writes[0]===[$identity,'report',15,1100],
    'creator write receives only server-resolved identity and report id');
$before=count($writer->writes);
foreach ([
    ['duplicate'=>true],
    ['persistence'=>['completed'=>true,'duplicate'=>true,'recovered'=>false]],
    ['persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>true]],
    ['event_id'=>'35'],
    ['status'=>'error'],
] as $change) {
    assert_ownership($coordinator->handle($secret,array_replace($result,$change),1101)==='ineligible',
        'invalid, duplicate or recovered Golden Path result rejected');
}
assert_ownership(count($writer->writes)===$before,
    'ineligible receipts never initiate ownership writes');
assert_ownership($coordinator->handle(null,$result,1101)==='unconfirmed',
    'missing session cannot confirm ownership');
assert_ownership($coordinator->handle('invalid',$result,1101)==='unconfirmed',
    'malformed session cannot confirm ownership');
$status->active[$identity]=false;
assert_ownership($coordinator->handle($secret,$result,1101)==='unconfirmed',
    'revoked identity cannot confirm ownership');
$status->active[$identity]=true;
$proof->valid=false;
assert_ownership($coordinator->handle($secret,$result,1101)==='unconfirmed',
    'missing trusted canonical receipt prevents binding');
$proof->valid=true;
$writer->accept=false;
assert_ownership($coordinator->handle($secret,$result,1101)==='unconfirmed',
    'failed persistent grant cannot be represented as confirmed');
assert_ownership($coordinator->handle($secret,$result,-5)==='ineligible',
    'invalid time is rejected');
echo 'ALL_NATIVE_OWNERSHIP_COORDINATOR_TESTS_PASS'.PHP_EOL;
