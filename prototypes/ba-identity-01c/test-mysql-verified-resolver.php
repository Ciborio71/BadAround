<?php
/** Disposable MariaDB/MySQL verified Identity login resolver regression. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/mysql-verified-resolver.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Safety stop: disposable ba_identity_ci only');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_verified_ci_';
$db->exec(BadAround_Identity_Schema_Candidate::statements($prefix)['ba_identities']);
$table=$prefix.'ba_identities';
$db->exec("DELETE FROM `{$table}`");
$key=str_repeat('K',32);
$lookup=BadAround_Identity_Challenge_Primitives::digest(
    'email','lookup','anna@example.com',$key
);
$revoked=str_repeat('b',64);
$stmt=$db->prepare("INSERT INTO `{$table}`
    (identity_id,email_lookup,protected_profile,verified_at,revoked_at)
    VALUES (:id,:lookup,:profile,:verified,:revoked)");
$stmt->execute([':id'=>'identity_12345678',':lookup'=>$lookup,
    ':profile'=>'encrypted-private-fixture',':verified'=>1762156800,':revoked'=>null]);
$stmt->execute([':id'=>'identity_87654321',':lookup'=>$revoked,
    ':profile'=>'encrypted-private-fixture',':verified'=>1762156800,':revoked'=>1762156810]);
function verified_check(bool $ok,string $label):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
$resolver=new BadAround_Identity_Mysql_Verified_Resolver($db,$table);
verified_check($resolver->find_verified_identity($lookup)==='identity_12345678',
    'verified active identity resolves by opaque email digest');
verified_check($resolver->find_verified_identity($revoked)===null,
    'revoked identity cannot resolve');
verified_check($resolver->find_verified_identity(str_repeat('a',64))===null,
    'unknown email lookup yields no identity');
verified_check($resolver->find_verified_identity('anna@example.com')===null,
    'raw email cannot bypass opaque HMAC lookup');
$db->exec("UPDATE `{$table}` SET revoked_at=1762156815
    WHERE identity_id='identity_12345678'");
verified_check($resolver->find_verified_identity($lookup)===null,
    'revocation immediately prevents login');
$missing=new BadAround_Identity_Mysql_Verified_Resolver($db,$prefix.'missing');
verified_check($missing->find_verified_identity($lookup)===null,
    'database error fails closed');
echo "ALL_MYSQL_VERIFIED_RESOLVER_TESTS_PASS\n";
