<?php
/** BA-REPORT-IDENTITY-01 regression: account-free report OTP boundary. */
declare(strict_types=1);
require __DIR__.'/report-submission-verification-policy.php';
function chk(bool $ok,string $name):void {
    if (!$ok)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
$p=BadAround_Report_Verification_Policy::private_profile(' Giulia ',' Rossi ','Giulia@Example.test');
chk($p['first_name']==='Giulia' && $p['last_name']==='Rossi'
    && $p['email']==='giulia@example.test','mandatory private identity normalized');
foreach([['','Rossi','a@example.test'],['Giulia','','a@example.test'],
    ['Giulia','Rossi','wrong']] as $fields) {
    $rejected=false;
    try {BadAround_Report_Verification_Policy::private_profile(...$fields);}
    catch(InvalidArgumentException $e) {$rejected=true;}
    chk($rejected,'missing or malformed required field denied');
}
$lookup=str_repeat('a',64);
$submission='12345678-1234-4234-8234-123456789abc';
$now=1762272000;
$receipt=[
    'purpose'=>'report_submission','email_lookup'=>$lookup,
    'submission_id'=>$submission,'verified_at'=>$now-20,
    'consumed_at'=>$now-10,'expires_at'=>$now+200,'duplicate'=>false
];
chk(BadAround_Report_Verification_Policy::verified_receipt(
    $receipt,$lookup,$submission,$now),'trusted single-use receipt accepted');
foreach([
    ['purpose','login'],
    ['purpose','enrollment'],
    ['email_lookup',str_repeat('b',64)],
    ['submission_id','87654321-4321-4234-8234-123456789abc'],
    ['consumed_at',null],
    ['verified_at',null],
    ['expires_at',$now],
    ['duplicate',true]
] as [$key,$value]) {
    $test=$receipt;$test[$key]=$value;
    chk(!BadAround_Report_Verification_Policy::verified_receipt(
        $test,$lookup,$submission,$now),'invalid binding rejected: '.$key);
}
chk(!BadAround_Report_Verification_Policy::can_claim_existing_report_by_email_only(),
    'previous reports cannot be claimed from matching email');
echo "ALL_ACCOUNT_FREE_REPORT_OTP_POLICY_TESTS_PASS\n";
