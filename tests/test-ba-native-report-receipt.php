<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/native-report-receipt.php';

function expect_receipt(bool $condition,string $message):void {
    if (!$condition) { throw new RuntimeException('FAIL '.$message); }
    echo 'PASS '.$message.PHP_EOL;
}
$valid=[
    'status'=>'success','report_id'=>41,'event_id'=>91,'duplicate'=>false,
    'persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>false],
];
$receipt=BadAround_Identity_Native_Report_Receipt::from_golden_path($valid);
expect_receipt($receipt===['report_id'=>41,'event_id'=>91,'duplicate'=>false],
    'trusted newly completed native report yields candidate');
$mutations=[
    ['duplicate'=>true],['duplicate'=>1],['status'=>'error'],
    ['report_id'=>'41'],['event_id'=>'91'],['event_id'=>0],
    ['report_id'=>0],['persistence'=>[]],
    ['persistence'=>['completed'=>true,'duplicate'=>true,'recovered'=>false]],
    ['persistence'=>['completed'=>true,'duplicate'=>false,'recovered'=>true]],
    ['persistence'=>['completed'=>false,'duplicate'=>false,'recovered'=>false]],
];
foreach($mutations as $index=>$change) {
    $result=array_replace($valid,$change);
    expect_receipt(BadAround_Identity_Native_Report_Receipt::from_golden_path($result)===null,
        'reject ambiguous duplicate recovered or malformed result '.($index+1));
}
expect_receipt(BadAround_Identity_Native_Report_Receipt::from_golden_path([])===null,
    'empty browser-like payload never grants candidate');
echo 'ALL_NATIVE_RECEIPT_BRIDGE_TESTS_PASS'.PHP_EOL;
