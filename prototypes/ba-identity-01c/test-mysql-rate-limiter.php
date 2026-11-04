<?php
/** Disposable MySQL only; test separate email/IP budgets and new windows. */
declare(strict_types=1);
require __DIR__ . '/otp-adapters.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if (!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Refusing non-CI database');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER') ?: 'ba_ci',getenv('BA_TEST_DB_PASS') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);
$db->exec('CREATE TABLE IF NOT EXISTS ba_identity_rate_ci (
  purpose VARCHAR(24) NOT NULL,
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  window_start BIGINT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  expires_at BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (purpose,bucket,window_start), KEY idx_expires (expires_at)
) ENGINE=InnoDB');
$db->exec('DELETE FROM ba_identity_rate_ci');
$limiter=new BadAround_Identity_Mysql_Rate_Limiter($db,'ba_identity_rate_ci');
$key=str_repeat('R',32);
$bucket=BadAround_Identity_Mysql_Rate_Limiter::bucket('login','email','a@example.com',$key);
$other=BadAround_Identity_Mysql_Rate_Limiter::bucket('login','email','b@example.com',$key);
$ip=BadAround_Identity_Mysql_Rate_Limiter::bucket('login','ip','127.0.0.1',$key);
$now=1762156800;
foreach ([1,2,3] as $i) {
    if (!$limiter->consume('login',$bucket,$now,3,900)) { throw new RuntimeException('Expected allowance '.$i); }
}
if ($limiter->consume('login',$bucket,$now,3,900)) { throw new RuntimeException('Over-limit request allowed'); }
if (!$limiter->consume('login',$other,$now,3,900)) { throw new RuntimeException('Other email unfairly denied'); }
if (!$limiter->consume('login',$ip,$now,3,900)) { throw new RuntimeException('Independent IP budget denied'); }
if (!$limiter->consume('login',$bucket,$now+900,3,900)) { throw new RuntimeException('Window not reset'); }
if (!$limiter->consume('enrollment',$bucket,$now,3,900)) { throw new RuntimeException('Purpose budget not independent'); }
$issuanceQuota = new BadAround_Identity_Issuance_Quota_Adapter($limiter,$key);
$lookup = BadAround_Identity_Challenge_Primitives::digest('email','lookup','quota@example.com',$key);
for ($i=0; $i<3; ++$i) {
    if (!$issuanceQuota->allow('enrollment',$lookup,'192.0.2.10',$now)) {
        throw new RuntimeException('Combined email/IP quota unexpectedly denied');
    }
}
if ($issuanceQuota->allow('enrollment',$lookup,'192.0.2.10',$now)) {
    throw new RuntimeException('Combined quota allowed fourth email OTP');
}
if ($issuanceQuota->allow('enrollment','malformed','192.0.2.10',$now)) {
    throw new RuntimeException('Invalid email lookup accepted');
}
echo "ALL_MYSQL_RATE_LIMIT_TESTS_PASS\n";
