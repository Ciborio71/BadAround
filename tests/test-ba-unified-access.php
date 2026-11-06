<?php
declare(strict_types=1);
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/unified-access.php';
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-access-rest.php';
function access_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label."\n";}
$dsn=getenv('BA_TEST_DB_DSN')?:'sqlite::memory:';
if($dsn!=='sqlite::memory:'&&!str_contains($dsn,'dbname=ba_identity_ci;'))throw new RuntimeException('Disposable DB only');
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$prefix='ci_access_'.bin2hex(random_bytes(4)).'_';$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';$tables=[];
foreach([
 'ba_identity_challenges'=>'challenge_id VARCHAR(32) PRIMARY KEY,email_lookup VARCHAR(64),code_digest VARCHAR(64),created_at BIGINT,expires_at BIGINT,attempts INT,consumed_at BIGINT NULL,version INT',
 'ba_identity_pending'=>'challenge_id VARCHAR(32) PRIMARY KEY,email_lookup VARCHAR(64),protected_profile TEXT,created_at BIGINT,verified_at BIGINT NULL',
 'ba_identities'=>'identity_id VARCHAR(128) PRIMARY KEY,email_lookup VARCHAR(64) UNIQUE,protected_profile TEXT,verified_at BIGINT,revoked_at BIGINT NULL',
 'ba_identity_sessions'=>'session_digest VARCHAR(64) PRIMARY KEY,identity_id VARCHAR(128),created_at BIGINT,last_seen_at BIGINT,expires_at BIGINT,revoked_at BIGINT NULL',
] as $table=>$columns){$tables[]=$prefix.$table;$db->exec('CREATE TABLE `'.$prefix.$table.'` ('.$columns.')'.($mysql?' ENGINE=InnoDB':''));}
class AccessQuota implements BadAround_Identity_Issue_Quota {public bool $allowed=true;public function allow(string $p,string $e,string $i,int $n):bool{return $this->allowed;}}
class AccessMail implements BadAround_Identity_Otp_Transport {public bool $accepted=true;public array $codes=[];public function send_code(string $e,string $c,string $p):bool {$this->codes[]=$c;return $this->accepted;}}
$root=str_repeat('r',64);$quota=new AccessQuota();$mail=new AccessMail();
$sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),hash_hmac('sha256','BA-ID-01H:sessions',$root,true));
$svc=new BadAround_Identity_Unified_Access($db,$prefix,$root,'qa-fixture',$quota,$mail,$sessions);$now=time();
$count=fn($t)=>(int)$db->query('SELECT COUNT(*) FROM `'.$prefix.$t.'`')->fetchColumn();
try {
 access_check($svc->start('test@example.test','','',false,'203.0.113.4',$now)===null&&count($mail->codes)===0,'explicit privacy acknowledgement required before delivery');
 $id=$svc->start('first@example.test','','',true,'203.0.113.4',$now);$code=end($mail->codes);
 access_check(is_string($id)&&preg_match('/^[0-9]{6}$/D',$code)===1,'new code is six numeric digits');
 access_check($count('ba_identities')===0&&$count('ba_identity_sessions')===0,'no identity or session before proof');
 access_check($svc->verify($id,'xxxxxx','','',$now+1)===null,'non numeric code rejected');
 $r=$svc->verify($id,$code,'','',$now+2);
 access_check($r===['state'=>'profile_required']&&$count('ba_identity_sessions')===0,'missing names requested only after correct email proof');
 access_check($svc->verify($id,$code,'<bad>','Name',$now+3)===null,'invalid mandatory name cannot create identity');
 $r=$svc->verify($id,$code,'First','Tester',$now+4);
 access_check(($r['state']??null)==='verified'&&$count('ba_identities')===1&&$count('ba_identity_sessions')===1,'one code creates verified profile and session atomically');
 $secret=$r['secret'];$identity=$sessions->identity_for($secret,$now+5);
 access_check($identity!==null&&$svc->matches($secret,'FIRST@example.test',$now+5),'verified mailbox matches active session');
 access_check(!$svc->matches($secret,'other@example.test',$now+5),'different email cannot use current session');
 access_check($svc->verify($id,$code,'First','Tester',$now+5)===null,'successful OTP cannot be replayed');
 $profile=$db->query('SELECT protected_profile FROM `'.$prefix.'ba_identities`')->fetchColumn();
 access_check(!str_contains($profile,'First')&&!str_contains($profile,'example.test'),'private profile encrypted');
 $id2=$svc->start('first@example.test','Other','Person',true,'203.0.113.4',$now+10);$r2=$svc->verify($id2,end($mail->codes),'','',$now+11);
 access_check(($r2['state']??null)==='verified'&&$count('ba_identities')===1,'returning user receives session without duplicate identity');
 access_check($db->query('SELECT protected_profile FROM `'.$prefix.'ba_identities`')->fetchColumn()===$profile,'returning access never overwrites verified name or consent');
 $id3=$svc->start('wizard@example.test','Wizard','Tester',true,'203.0.113.4',$now+20);$r3=$svc->verify($id3,end($mail->codes),'','',$now+21);
 access_check(($r3['state']??null)==='verified','wizard names reused without a second code');
 $id4=$svc->start('locked@example.test','Lock','Tester',true,'203.0.113.4',$now+30);$correct=end($mail->codes);$wrong=$correct==='000000'?'111111':'000000';
 for($i=0;$i<5;$i++)access_check($svc->verify($id4,$wrong,'','',$now+31+$i)===null,'wrong attempt '.($i+1));
 access_check($svc->verify($id4,$correct,'','',$now+40)===null,'five wrong attempts lock challenge');
 $id5=$svc->start('expired@example.test','Time','Tester',true,'203.0.113.4',$now+50);
 access_check($svc->verify($id5,end($mail->codes),'','',$now+650)===null,'code expires at ten minutes');
 $mail->accepted=false;$before=$count('ba_identities');
 access_check($svc->start('failure@example.test','Mail','Tester',true,'203.0.113.4',$now+60)===null&&$count('ba_identities')===$before,'provider failure is not successful delivery');$mail->accepted=true;
 $quota->allowed=false;$before=count($mail->codes);
 access_check($svc->start('quota@example.test','Quota','Tester',true,'203.0.113.4',$now+70)===null&&count($mail->codes)===$before,'quota refusal sends no mail');$quota->allowed=true;
 $db->exec('UPDATE `'.$prefix.'ba_identities` SET revoked_at='.($now+80).' WHERE identity_id='.$db->quote($identity));
 access_check(!$svc->matches($secret,'first@example.test',$now+81),'revoked identity cannot submit');
 $id6=$svc->start('first@example.test','','',true,'203.0.113.4',$now+82);
 access_check($svc->verify($id6,end($mail->codes),'','',$now+83)===null,'correct code cannot reactivate revoked identity');
 // Purpose separation: the former registration verifier must never activate an access-only empty profile.
 $store=new BadAround_Identity_Mysql_Challenge_Store($db,$prefix.'ba_identity_challenges');
 $id7=$svc->start('purpose@example.test','','',true,'203.0.113.4',$now+90);
 $c=$store->load($id7);$proof=BadAround_Identity_Challenge_Primitives::verify($c,end($mail->codes),BadAround_Identity_Purpose_Keys::for_purpose('enrollment',$root),$now+91);
 access_check(!$proof['ok'],'access code cannot be used by legacy enrollment controller');
 // Cookie failure revokes the newly created session, without returning its secret.
 class BadAround_Identity_WordPress_Portal {public static bool $allowed=true;public static function enabled(){return self::$allowed;}}
 define('BADAROUND_IDENTITY_LOGIN_ENABLED',true);define('BADAROUND_IDENTITY_REGISTRATION_ENABLED',true);
 function is_ssl(){return true;}
 final class AccessRequest {function __construct(public array $data,public string $origin='https://badaround.it'){}function get_header($h){return ['origin'=>$this->origin,'content-type'=>'application/json','sec-fetch-site'=>'same-origin'][$h]??'';}function get_body(){return json_encode($this->data);}function get_json_params(){return $this->data;}}
 final class FailingCookie implements BadAround_Identity_Cookie_Writer {function write(string $n,string $v,array $o):bool{return false;}}
 $rest=new BadAround_Identity_WordPress_Access_REST($svc,$sessions,new BadAround_Identity_WordPress_Session_Cookie(new FailingCookie()),'https://badaround.it');
 access_check($rest->start(new AccessRequest(['email'=>'x@example.test','privacy_acknowledged'=>true],'https://evil.example'))['status']===403,'cross-origin request denied');
 $id8=$svc->start('cookie@example.test','Cookie','Tester',true,'203.0.113.4',$now);
 $response=$rest->verify(new AccessRequest(['challenge_id'=>$id8,'code'=>end($mail->codes)]));
 access_check($response['status']===503&&!str_contains(json_encode($response),'secret'),'cookie failure has generic response without token');
 access_check((int)$db->query('SELECT COUNT(*) FROM `'.$prefix.'ba_identity_sessions` WHERE revoked_at IS NOT NULL')->fetchColumn()>=1,'cookie failure revokes stored session');
 BadAround_Identity_WordPress_Portal::$allowed=false;
 access_check($rest->start(new AccessRequest(['email'=>'x@example.test','privacy_acknowledged'=>true]))['status']===403,'private authorization rechecked on direct invocation');
 echo "UNIFIED_ACCESS_PASS\n";
} finally {foreach(array_reverse($tables) as $t)$db->exec('DROP TABLE `'.$t.'`');}
