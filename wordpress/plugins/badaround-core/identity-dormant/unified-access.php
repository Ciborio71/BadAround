<?php
/** One email proof for first access and returning users. No hooks or mail on include. */
declare(strict_types=1);
require_once __DIR__.'/otp-adapters.php';
require_once __DIR__.'/mysql-enrollment-store.php';
require_once __DIR__.'/mysql-challenge-store.php';
require_once __DIR__.'/mysql-session-store.php';
final class BadAround_Identity_Unified_Access {
    private BadAround_Identity_Mysql_Challenge_Store $challenges;
    private BadAround_Identity_Mysql_Enrollment_Store $pending;
    private string $profileKey;
    public function __construct(private PDO $db,private string $prefix,private string $rootKey,
        private string $notice,private BadAround_Identity_Issue_Quota $quota,
        private BadAround_Identity_Otp_Transport $transport,private BadAround_Identity_Sessions $sessions) {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)||strlen($rootKey)<48
            ||!preg_match('/^[A-Za-z0-9._-]{1,64}$/D',$notice))throw new InvalidArgumentException('Access configuration');
        $this->profileKey=hash_hmac('sha256','BA-ID-01Q:enrollment-encryption',$rootKey,true);
        $this->challenges=new BadAround_Identity_Mysql_Challenge_Store($db,$prefix.'ba_identity_challenges');
        $this->pending=new BadAround_Identity_Mysql_Enrollment_Store($db,$prefix.'ba_identity_pending',$prefix.'ba_identities',$this->profileKey,$prefix.'ba_identity_challenges');
    }
    public function start(string $email,string $first,string $last,bool $ack,string $ip,int $now):?string {
        $id=null;
        try {
            if (!$ack||$now<=0||!filter_var($ip,FILTER_VALIDATE_IP))return null;
            $email=BadAround_Identity_Challenge_Primitives::normalize_email($email);
            $first=trim($first);$last=trim($last);
            if ($first!==''||$last!=='')BadAround_Identity_Enrollment::validate_profile($first,$last,$email);
            $lookup=BadAround_Identity_Challenge_Primitives::digest('email','lookup',$email,$this->rootKey);
            if (!$this->quota->allow('access',$lookup,$ip,$now))return null;
            $issued=BadAround_Identity_Purpose_Keys::new_challenge_for_purpose($email,'access',$this->rootKey,$now);
            $id=$issued['record']['challenge_id'];$this->challenges->insert($issued['record']);
            $this->pending->create_pending(['challenge_id'=>$id,'email_lookup'=>$lookup,'first_name'=>$first,'last_name'=>$last,'email'=>$email,'created_at'=>$now,
                'privacy_consent'=>['accepted'=>true,'notice_version'=>$this->notice,'accepted_at'=>$now,'method'=>'explicit_checkbox']]);
            if ($this->transport->send_code($email,$issued['delivery_code'],'access')!==true) {
                $this->challenges->invalidate($id,$now);return null;
            }
            return $id;
        } catch(Throwable $e) {if($id!==null){try{$this->challenges->invalidate($id,$now);}catch(Throwable $ignored){}}return null;}
    }
    private function protect(array $profile):string {
        $nonce=random_bytes(12);$tag='';
        $cipher=openssl_encrypt(json_encode($profile,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->profileKey,OPENSSL_RAW_DATA,$nonce,$tag,'BA-ID-01C-ENROLL');
        if($cipher===false||strlen($tag)!==16)throw new RuntimeException('Profile protection');
        return base64_encode($nonce.$tag.$cipher);
    }
    /** Correct OTP may ask for missing names, but is consumed only by complete authentication. */
    public function verify(string $id,string $code,string $first,string $last,int $now):?array {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[0-9]{6}$/D',$code)||$now<=0||$this->db->inTransaction())return null;
        $this->db->beginTransaction();
        try {
            $lock=$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
            $q=$this->db->prepare("SELECT * FROM `{$this->prefix}ba_identity_challenges` WHERE challenge_id=?".$lock);$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
            if(!$row){$this->db->rollBack();return null;}
            $row['consumed_at']=$row['consumed_at']===null?null:(int)$row['consumed_at'];
            $r=BadAround_Identity_Challenge_Primitives::verify($row,$code,BadAround_Identity_Purpose_Keys::for_purpose('access',$this->rootKey),$now);
            if(in_array($r['reason'],['expired','consumed','locked'],true)){$this->db->rollBack();return null;}
            $state=null;$identity=null;$secret=null;
            if($r['ok']) {
                $q=$this->db->prepare("SELECT verified_at FROM `{$this->prefix}ba_identity_pending` WHERE challenge_id=?".$lock);$q->execute([$id]);$pendingRow=$q->fetch(PDO::FETCH_ASSOC);
                $profile=$this->pending->find_by_challenge($id);
                if(!$pendingRow||$pendingRow['verified_at']!==null||!$profile||!hash_equals($row['email_lookup'],$profile['email_lookup'])
                    ||($profile['privacy_consent']['accepted']??null)!==true||($profile['privacy_consent']['notice_version']??null)!==$this->notice) {
                    $this->db->rollBack();return null;
                }
                $q=$this->db->prepare("SELECT identity_id,verified_at,revoked_at FROM `{$this->prefix}ba_identities` WHERE email_lookup=?".$lock);$q->execute([$row['email_lookup']]);$existing=$q->fetch(PDO::FETCH_ASSOC);
                if($existing && ($existing['revoked_at']!==null||(int)$existing['verified_at']<=0)){$this->db->rollBack();return null;}
                if($existing){$identity=$existing['identity_id'];}
                else {
                    $first=trim($first)!==''?trim($first):$profile['first_name'];$last=trim($last)!==''?trim($last):$profile['last_name'];
                    if($first===''||$last===''){$state='profile_required';$r['record']['consumed_at']=null;}
                    else {
                        $validated=BadAround_Identity_Enrollment::validate_profile($first,$last,$profile['email']);
                        $validated['privacy_consent']=$profile['privacy_consent'];
                        $identity='identity_'.bin2hex(random_bytes(16));
                        $q=$this->db->prepare("INSERT INTO `{$this->prefix}ba_identities` (identity_id,email_lookup,protected_profile,verified_at,revoked_at) VALUES (?,?,?,?,NULL)");
                        $q->execute([$identity,$row['email_lookup'],$this->protect($validated),$now]);
                    }
                }
                if($identity!==null){
                    $q=$this->db->prepare("UPDATE `{$this->prefix}ba_identity_pending` SET verified_at=? WHERE challenge_id=? AND verified_at IS NULL");$q->execute([$now,$id]);
                    if($q->rowCount()!==1)throw new RuntimeException('Pending race');
                    $secret=$this->sessions->issue($identity,$now);$state='verified';
                }
            }
            $q=$this->db->prepare("UPDATE `{$this->prefix}ba_identity_challenges` SET attempts=?,consumed_at=?,version=version+1 WHERE challenge_id=? AND version=? AND consumed_at IS NULL");
            $q->execute([$r['record']['attempts'],$r['record']['consumed_at'],$id,$row['version']]);
            if($q->rowCount()!==1)throw new RuntimeException('Challenge race');
            $this->db->commit();
            return $state==='verified'?['state'=>$state,'secret'=>$secret]:($state==='profile_required'?['state'=>$state]:null);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return null;}
    }
    public function matches(?string $secret,string $email,int $now):bool {
        try {
            if(!$secret)return false;$identity=$this->sessions->identity_for($secret,$now);if(!$identity)return false;
            $email=BadAround_Identity_Challenge_Primitives::normalize_email($email);
            $lookup=BadAround_Identity_Challenge_Primitives::digest('email','lookup',$email,$this->rootKey);
            $q=$this->db->prepare("SELECT identity_id FROM `{$this->prefix}ba_identities` WHERE identity_id=? AND email_lookup=? AND verified_at>0 AND revoked_at IS NULL");$q->execute([$identity,$lookup]);
            return $q->fetchColumn()===$identity;
        }catch(Throwable $e){return false;}
    }
}
