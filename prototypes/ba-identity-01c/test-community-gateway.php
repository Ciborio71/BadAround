<?php
declare(strict_types=1);
require __DIR__.'/community-gateway.php';
final class GatewayAuthorityFixture implements BadAround_Identity_Community_Authority {
    public bool $active=true;
    public bool $published=true;
    public bool $isModerator=false;
    public bool $fail=false;
    public array $owner=[];
    public string $validToken='';
    public function active_identity(?string $sessionToken,int $now): ?string {
        if ($this->fail) { throw new RuntimeException('Backend offline'); }
        return $this->active && $sessionToken===$this->validToken
            ?'identity_12345678':null;
    }
    public function canonical_event_is_published(int $eventId): bool {
        return $this->published && $eventId===801;
    }
    public function verified_owner_record(int $reportId): ?array {
        return $reportId===701?$this->owner:null;
    }
    public function may_moderate(string $moderatorIdentityId): bool {
        return $this->isModerator;
    }
}
final class GatewayInboxFixture implements BadAround_Identity_Community_Inbox_Writer {
    public array $sent=[];
    public array $decisions=[];
    public bool $fail=false;
    public function submit(string $id,string $kind,string $identity,int $resource,array $intent,int $now): bool {
        if ($this->fail) { throw new RuntimeException('Storage offline'); }
        $this->sent[]=['id'=>$id,'kind'=>$kind,'intent'=>$intent];
        return true;
    }
    public function decide(string $id,int $version,string $decision,string $moderator,int $now): bool {
        if ($this->fail) { throw new RuntimeException('Storage offline'); }
        $this->decisions[]=[$id,$version,$decision,$moderator];
        return true;
    }
}
function gateway_check(bool $pass,string $title): void {
    if (!$pass) { throw new RuntimeException('FAIL '.$title); }
    echo 'PASS '.$title.PHP_EOL;
}
$now=1762156800;
$key=str_repeat('K',32);
$store=new BadAround_Identity_Session_Memory_Store();
$sessions=new BadAround_Identity_Sessions($store,$key);
$token=$sessions->issue('identity_12345678',$now);
$csrf=BadAround_Identity_Csrf_Policy::issue($token,$key);
$origin='https://badaround.it';
$status=new BadAround_Identity_Active_Memory_Status();
$status->active['identity_12345678']=true;
$guard=new BadAround_Identity_Edit_Guard($sessions,$key,$origin,$status);
$auth=new GatewayAuthorityFixture();
$auth->validToken=$token;
$auth->owner=['identity_id'=>'identity_12345678','resource_kind'=>'report',
 'resource_id'=>701,'relation'=>'creator','verified_binding_at'=>'2026-11-03T09:00:00Z',
 'revoked_at'=>null];
$inbox=new GatewayInboxFixture();
$gateway=new BadAround_Identity_Community_Gateway($auth,$inbox,$guard,$key,$origin);
gateway_check($gateway->submit_comment($token,$csrf,$origin,801,
 'Ho visto qualcosa vicino alla stazione','anonymous',null,$now),
 'verified user can submit private comment');
gateway_check($inbox->sent[0]['intent']['moderation_status']==='pending',
 'comment is pending not public');
gateway_check(!$gateway->submit_comment($token,$csrf,'https://evil.example',801,
 'Ho visto qualcosa vicino alla stazione','anonymous',null,$now),
 'cross-origin comment denied');
gateway_check(!$gateway->submit_comment($token,'invalid',$origin,801,
 'Ho visto qualcosa vicino alla stazione','anonymous',null,$now),
 'invalid CSRF comment denied');
$auth->published=false;
gateway_check(!$gateway->submit_comment($token,$csrf,$origin,801,
 'Ho visto qualcosa vicino alla stazione','anonymous',null,$now),
 'unpublished event comment denied');
$auth->published=true;
gateway_check($gateway->submit_revision($token,$csrf,$origin,701,
 'Nuova descrizione della segnalazione','Dettagli aggiornati',
 hash('sha256','original server snapshot'),$now),
 'verified creator can submit private revision');
gateway_check($inbox->sent[1]['kind']==='revision'
 && $inbox->sent[1]['intent']['public_mutation']===false,
 'revision cannot publish directly');
$auth->owner['identity_id']='identity_87654321';
gateway_check(!$gateway->submit_revision($token,$csrf,$origin,701,
 'Nuova descrizione della segnalazione','Dettagli aggiornati',
 hash('sha256','original server snapshot'),$now),
 'other owner cannot edit');
$auth->owner['identity_id']='identity_12345678';
gateway_check(!$gateway->moderate($token,$csrf,$origin,str_repeat('a',32),0,
 'approved',$now),'ordinary user cannot moderate');
$auth->isModerator=true;
gateway_check($gateway->moderate($token,$csrf,$origin,str_repeat('a',32),0,
 'approved',$now),'authorized moderator can decide');
$inbox->fail=true;
gateway_check(!$gateway->moderate($token,$csrf,$origin,str_repeat('b',32),0,
 'approved',$now),'storage outage fails closed');
$inbox->fail=false;
$auth->fail=true;
gateway_check(!$gateway->submit_comment($token,$csrf,$origin,801,
 'Ho visto qualcosa vicino alla stazione','anonymous',null,$now),
 'identity infrastructure failure denied');
echo "ALL_COMMUNITY_GATEWAY_TESTS_PASS\n";
