<?php
/** Isolated WordPress privacy contract checks. */
declare(strict_types=1);
require __DIR__.'/wordpress-privacy-notice.php';
$GLOBALS['privacy_fixture']=(object)[
    'ID'=>3,'post_type'=>'page','post_status'=>'publish',
    'post_name'=>'privacy-policy',
    'post_modified_gmt'=>'2026-10-24 16:26:03',
    'post_content'=>'Informativa privacy BadAround'
];
function get_post($id){return $id===3 ? $GLOBALS['privacy_fixture']:null;}
function privacy_check(bool $result,string $name):void{
    if (!$result)throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
privacy_check(
    BadAround_Identity_WordPress_Privacy_Notice::verified_version()==='privacy-20261024T162603Z',
    'live privacy revision can be bound to identity registration'
);
$GLOBALS['privacy_fixture']->post_modified_gmt='2026-11-03 21:00:00';
privacy_check(
    BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null,
    'changed privacy notice blocks stale consent version'
);
$GLOBALS['privacy_fixture']->post_modified_gmt='2026-10-24 16:26:03';
$GLOBALS['privacy_fixture']->post_status='draft';
privacy_check(
    BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null,
    'unpublished privacy notice blocks registration'
);
$GLOBALS['privacy_fixture']->post_status='publish';
$GLOBALS['privacy_fixture']->post_content='<strong>BOZZA PRE-PUBBLICAZIONE — da sottoporre a revisione.</strong>';
privacy_check(
    BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null,
    'published legal draft is rejected even with matching metadata'
);
$GLOBALS['privacy_fixture']->post_content='';
privacy_check(
    BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null,
    'empty policy cannot be acknowledged'
);
echo "ALL_WORDPRESS_PRIVACY_NOTICE_TESTS_PASS\n";
