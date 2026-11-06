<?php
declare(strict_types=1);
define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-community-rest-adapter.php';
function rest_ensure_response($value){return $value;}
class RevisionRequest {
 public function __construct(private array $data,private string $csrf,private string $origin='https://badaround.it'){}
 public function get_header($name){return ['content-type'=>'application/json','origin'=>$this->origin,'x-badaround-csrf'=>$this->csrf][$name]??null;}
 public function get_body(){return json_encode($this->data);}
 public function get_json_params(){return $this->data;}
}
class RevisionAuthority implements BadAround_Identity_Community_Authority {
 public string $token='';public string $owner='identity_12345678';
 public function active_identity(?string $token,int $now):?string{return $token===$this->token?'identity_12345678':null;}
 public function canonical_event_is_published(int $id):bool{return $id===801;}
 public function verified_owner_record(int $id):?array{return $id===701?['identity_id'=>$this->owner,'resource_kind'=>'report','resource_id'=>701,'relation'=>'creator','verified_binding_at'=>time()-10,'revoked_at'=>null]:null;}
 public function may_moderate(string $id):bool{return false;}
}
class RevisionInbox implements BadAround_Identity_Community_Inbox_Writer {
 public array $entries=[];
 public function submit(string $id,string $kind,string $actor,int $resource,array $intent,int $now):bool{$this->entries[]=$intent;return true;}
 public function decide(string $id,int $version,string $decision,string $actor,int $now):bool{throw new RuntimeException('No publication or moderation permitted in submit');}
}
class RevisionBudget implements BadAround_Identity_Community_Rest_Budget {
 public bool $allow=true;
 public function consume(string $token,string $kind,int $now):bool{return $this->allow;}
}
function verify(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$key=str_repeat('r',32);$now=time();$sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Session_Memory_Store(),$key);
$token=$sessions->issue('identity_12345678',$now);$csrf=BadAround_Identity_Csrf_Policy::issue($token,$key);
$status=new BadAround_Identity_Active_Memory_Status();$status->active['identity_12345678']=true;
$authority=new RevisionAuthority();$authority->token=$token;$inbox=new RevisionInbox();$quota=new RevisionBudget();
$guard=new BadAround_Identity_Edit_Guard($sessions,$key,'https://badaround.it',$status);
$gateway=new BadAround_Identity_Community_Gateway($authority,$inbox,$guard,$key,'https://badaround.it');
$reader=static fn(int $event):?array=>$event===801?['report_id'=>701,'digest'=>str_repeat('a',64)]:null;
$rest=new BadAround_Identity_Community_Rest_Adapter($gateway,$quota,$reader);
$_COOKIE['__Host-ba_identity']=$token;
$payload=['event_id'=>801,'description'=>'Descrizione aggiornata di prova','reason'=>'Correzione di prova'];
$result=$rest->submit(new RevisionRequest($payload,$csrf),'revision');
verify(($result['status']??null)==='pending_moderation'&&count($inbox->entries)===1,'verified owner can propose a private revision');
verify($inbox->entries[0]['original_snapshot_digest']===str_repeat('a',64)&&$inbox->entries[0]['public_mutation']===false,'only server snapshot is persisted and public mutation remains false');
foreach(['snapshot_digest'=>str_repeat('b',64),'report_id'=>999,'identity_id'=>'forged_identity'] as $field=>$value){
 $result=$rest->submit(new RevisionRequest($payload+[$field=>$value],$csrf),'revision');
 verify(($result['status']??null)===400,'client authority field rejected: '.$field);
}
$authority->owner='different_identity';
$result=$rest->submit(new RevisionRequest($payload,$csrf),'revision');verify(($result['status']??null)===403,'other creator cannot be edited');$authority->owner='identity_12345678';
foreach([new RevisionRequest($payload,str_repeat('0',64)),new RevisionRequest($payload,$csrf,'https://example.invalid'),new RevisionRequest(array_replace($payload,['event_id'=>802]),$csrf)] as $request){
 $result=$rest->submit($request,'revision');verify(($result['status']??null)===403,'invalid CSRF, origin or canonical event is denied');
}
$quota->allow=false;$result=$rest->submit(new RevisionRequest($payload,$csrf),'revision');verify(($result['status']??null)===429,'quota remains enforced');$quota->allow=true;
$blocked=new BadAround_Identity_Community_Rest_Adapter($gateway,$quota);
$result=$blocked->submit(new RevisionRequest($payload,$csrf),'revision');verify(($result['status']??null)===503,'unwired snapshot adapter remains fail closed');
verify(count($inbox->entries)===1,'rejected requests add no moderation intent');
$parentId=str_repeat('b',32);
$wired=new BadAround_Identity_Community_Rest_Adapter($gateway,$quota,$reader,
    static fn(string $id,int $event):?array=>$id===str_repeat('b',32)&&$event===801
        ?['event_id'=>801,'comment_id'=>$id,'moderation_status'=>'approved','parent_id'=>null]:null);
$reply=['event_id'=>801,'body'=>'Risposta di prova','display_mode'=>'pseudonym','alias'=>'Sentinella QA','parent_id'=>$parentId];
$result=$wired->submit(new RevisionRequest($reply,$csrf),'comment');
verify(($result['status']??null)==='pending_moderation'&&$inbox->entries[1]['parent_id']===$parentId
    &&$inbox->entries[1]['public_alias']==='Sentinella QA','reply parent is resolved server-side and explicit alias stays in private intent');
$result=$wired->submit(new RevisionRequest(array_replace($reply,['parent_id'=>str_repeat('c',32)]),$csrf),'comment');
verify(($result['status']??null)===403&&count($inbox->entries)===2,'unapproved or foreign reply parent is rejected');
echo "ALL_REVISION_REST_TESTS_PASS\n";
