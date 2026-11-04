<?php
/** Fail-closed bootstrap and privacy revision tests, no WordPress runtime. */
declare(strict_types=1);
require __DIR__.'/wordpress-private-bootstrap.php';
function assert_private(bool $value,string $message):void {
    if (!$value) { throw new RuntimeException('FAIL '.$message); }
    echo 'PASS '.$message.PHP_EOL;
}
assert_private(!BadAround_Identity_WordPress_Private_Bootstrap::enabled(),
    'private identity activation OFF by default');
$missingDb=new PDO('sqlite::memory:');
assert_private(
    BadAround_Identity_WordPress_Private_Bootstrap::install($missingDb,'ba_')===false,
    'dormant bootstrap does not touch database or register routes'
);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
assert_private(BadAround_Identity_WordPress_Private_Bootstrap::enabled(),
    'private QA can be requested explicitly');
assert_private(
    BadAround_Identity_WordPress_Private_Bootstrap::install($missingDb,'ba_')===false,
    'no secret or WordPress context fails closed'
);
echo "ALL_WORDPRESS_PRIVATE_BOOTSTRAP_TESTS_PASS\n";
