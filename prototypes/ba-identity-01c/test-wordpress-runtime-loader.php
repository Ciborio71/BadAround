<?php
/** Runtime loader is completely inert unless explicitly authorized. */
declare(strict_types=1);
require __DIR__.'/wordpress-runtime-loader.php';
function assert_loader(bool $x,string $label):void{
    if (!$x)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
assert_loader(BadAround_Identity_WordPress_Runtime_Loader::boot()===false,
    'no private QA flag: loader has no side effects');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
assert_loader(BadAround_Identity_WordPress_Runtime_Loader::boot()===false,
    'private QA flag without dedicated root key still refuses');
echo "ALL_WORDPRESS_RUNTIME_LOADER_TESTS_PASS\n";
