<?php
/** Production-family MariaDB/MySQL login: persistent OTP -> active session. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/wordpress-login-composition.php';
$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Only disposable CI database allowed');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_login_ci_';
$statements=BadAround_Identity_Schema_Candidate::statements($prefix);
foreach(['ba_identities','ba_identity_challenges','ba_identity_sessions'] as $table) {
    $db->exec($statements[$table]);
    $db->exec('DELETE FROM `'.$prefix.$table.'`');
}
$root=str_repeat('X',48);$now=1762156800;
$email='anna@example.com';$identity='identity_12345678';
$lookup=BadAround_Identity_Challenge_Primitives::digest('email','lookup',$email,$root);
$stmt=$db->prepare('INSERT INTO `'.$prefix.'ba_identities`
    (identity_id,email_lookup,protected_profile,verified_at,revoked_at)
    VALUES (:id,:lookup,:profile,:verified,NULL)');
$stmt->execute([':id'=>$identity,':lookup'=>$lookup,':profile'=>'encrypted-ci-fixture',':verified'=>$now-20]);
$otp=BadAround_Identity_Purpose_Keys::new_challenge_for_purpose(
    $email,'login',$root,$now
);
$challengeStore=new BadAround_Identity_Mysql_Challenge_Store($db,$prefix.'ba_identity_challenges');
$challengeStore->insert($otp['record']);
$login=BadAround_Identity_WordPress_Login_Composition::assemble($db,$prefix,$root);
function login_assert(bool $ok,string $name):void {
    if(!$ok) { throw new RuntimeException('FAIL '.$name); }
    echo 'PASS '.$name.PHP_EOL;
}
login_assert($login->verify_and_issue($otp['record']['challenge_id'],'0000000x',$now+1)===null,
    'wrong OTP cannot issue session');
$session=$login->verify_and_issue(
    $otp['record']['challenge_id'],$otp['delivery_code'],$now+2
);
login_assert(is_string($session) && preg_match('/^[a-f0-9]{64}$/D',$session)===1,
    'valid purpose-bound OTP issues opaque session bearer');
login_assert($login->verify_and_issue(
    $otp['record']['challenge_id'],$otp['delivery_code'],$now+3
)===null,'replayed OTP cannot issue second session');
$sessions=new BadAround_Identity_Sessions(
    new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),
    hash_hmac('sha256','BA-ID-01H:sessions',$root,true)
);
login_assert($sessions->identity_for($session,$now+3)===$identity,
    'same derived key resolves persisted session');
$rows=$db->query('SELECT session_digest FROM `'.$prefix.'ba_identity_sessions`')
    ->fetchAll(PDO::FETCH_COLUMN);
login_assert(count($rows)===1 && !in_array($session,$rows,true),
    'database only stores session digest');
$other=BadAround_Identity_Purpose_Keys::new_challenge_for_purpose(
    $email,'enrollment',$root,$now
);
$challengeStore->insert($other['record']);
login_assert($login->verify_and_issue(
    $other['record']['challenge_id'],$other['delivery_code'],$now+4
)===null,'enrollment OTP cannot cross-authenticate as login OTP');
echo "ALL_WORDPRESS_LOGIN_COMPOSITION_TESTS_PASS\n";
