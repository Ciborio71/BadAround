<?php
/** Disposable MariaDB/MySQL: consent -> encrypted profile -> OTP activation. */
declare(strict_types=1);
require __DIR__.'/schema-candidate.php';
require __DIR__.'/registration-service.php';
require __DIR__.'/mysql-enrollment-store.php';
require __DIR__.'/mysql-challenge-store.php';
require __DIR__.'/purpose-keys.php';

$dsn=(string)getenv('BA_TEST_DB_DSN');
if(!preg_match('/(?:^|;)dbname=ba_identity_ci(?:;|$)/',$dsn)) {
    throw new RuntimeException('Only disposable ba_identity_ci allowed');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'ba_ci',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$prefix='ba_registration_ci_';
$ddl=BadAround_Identity_Schema_Candidate::statements($prefix);
foreach(['ba_identity_challenges','ba_identity_pending','ba_identities'] as $name) {
    $db->exec($ddl[$name]);
    $db->exec('DELETE FROM `'.$prefix.$name.'`');
}
function reg_check(bool $condition,string $name):void {
    if (!$condition) throw new RuntimeException('FAIL '.$name);
    echo 'PASS '.$name.PHP_EOL;
}
final class RegQuota implements BadAround_Identity_Issue_Quota {
    public bool $allow=true;
    public function allow(string $purpose,string $emailLookup,string $ip,int $now):bool {
        return $this->allow && $purpose==='enrollment';
    }
}
final class RegMail implements BadAround_Identity_Otp_Transport {
    public bool $allow=true;
    public array $sent=[];
    public function send_code(string $email,string $code,string $purpose):bool {
        $this->sent[]=[$email,$code,$purpose];return $this->allow;
    }
}
$root=str_repeat('K',48);$now=1762156800;
$store=new BadAround_Identity_Mysql_Challenge_Store($db,$prefix.'ba_identity_challenges');
$pending=new BadAround_Identity_Mysql_Enrollment_Store(
    $db,$prefix.'ba_identity_pending',$prefix.'ba_identities',
    str_repeat('E',32),$prefix.'ba_identity_challenges'
);
$verifier=new BadAround_Identity_Challenge_Verifier(
    $store,BadAround_Identity_Purpose_Keys::for_purpose('enrollment',$root)
);
$enrollment=new BadAround_Identity_Enrollment(
    $pending,$store,$verifier,$root,
    BadAround_Identity_Purpose_Keys::for_purpose('enrollment',$root)
);
$quota=new RegQuota();$mail=new RegMail();
$service=new BadAround_Identity_Registration_Service(
    $enrollment,$quota,$store,$mail,$root,'privacy-2026-11'
);
reg_check($service->start('Anna','Rossi','anna@example.com',false,'127.0.0.1',$now)===null,
    'no checkbox, no registration');
reg_check((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'ba_identity_challenges`')->fetchColumn()===0,
    'no OTP persisted without consent');
reg_check($service->start('','Rossi','anna@example.com',true,'127.0.0.1',$now)===null,
    'first name mandatory');
reg_check($service->start('Anna','','anna@example.com',true,'127.0.0.1',$now)===null,
    'last name mandatory');
reg_check($service->start('Anna','Rossi','not-an-email',true,'127.0.0.1',$now)===null,
    'valid email mandatory');
$id=$service->start('Anna','Rossi','anna@example.com',true,'127.0.0.1',$now);
reg_check(is_string($id) && preg_match('/^[a-f0-9]{32}$/D',$id)===1
    && count($mail->sent)===1,'explicit consent creates private pending OTP');
$details=$pending->find_by_challenge($id);
reg_check($details!==null
    && $details['privacy_consent']['accepted']===true
    && $details['privacy_consent']['accepted_at']===$now
    && $details['privacy_consent']['notice_version']==='privacy-2026-11'
    && $details['privacy_consent']['method']==='explicit_checkbox',
    'immutable consent evidence present in encrypted pending profile');
$q=$db->prepare('SELECT protected_profile FROM `'.$prefix.'ba_identity_pending` WHERE challenge_id=?');
$q->execute([$id]);$cipher=(string)$q->fetchColumn();
reg_check(!str_contains($cipher,'Anna') && !str_contains($cipher,'privacy-2026-11')
    && !str_contains($cipher,'anna@example.com'),
    'private identity and consent evidence encrypted at rest');
reg_check($service->verify($id,'0000000x',$now+1)===null,
    'invalid OTP cannot create identity');
$verified=$service->verify($id,$mail->sent[0][1],$now+2);
reg_check(is_string($verified) && str_starts_with($verified,'identity_'),
    'valid enrollment OTP activates verified profile');
reg_check($service->verify($id,$mail->sent[0][1],$now+3)===null,
    'OTP cannot be replayed');
$stmt=$db->prepare('SELECT protected_profile FROM `'.$prefix.'ba_identities` WHERE identity_id=?');
$stmt->execute([$verified]);$stored=(string)$stmt->fetchColumn();
reg_check($stored===$cipher && !str_contains($stored,'privacy-2026-11'),
    'activated identity retains encrypted consent evidence');
$quota->allow=false;
reg_check($service->start('Mario','Bianchi','mario@example.com',true,'127.0.0.1',$now)===null,
    'quota denial blocks registration');
$quota->allow=true;$mail->allow=false;
$blocked=$service->start('Mario','Bianchi','mario@example.com',true,'127.0.0.1',$now);
reg_check($blocked===null,'mail transport failure not treated as success');
reg_check((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'ba_identities`')->fetchColumn()===1,
    'no verified identity without delivered OTP');
echo "ALL_CONSENT_REGISTRATION_MYSQL_TESTS_PASS\n";
