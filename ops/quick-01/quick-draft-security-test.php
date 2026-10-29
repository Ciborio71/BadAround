<?php
declare(strict_types=1);
require __DIR__.'/quick-draft-security.php';
function verifyDraft(bool $x,string $why):void{if(!$x)throw new RuntimeException($why);}
$token=BadAround_Quick_Draft_Security::newToken();
verifyDraft(strlen($token)===64&&ctype_xdigit($token),'random token');
$now=new DateTimeImmutable('2026-10-29T10:00:00+01:00');
$record=['state'=>'private_draft','revoked'=>false,'token_hash'=>BadAround_Quick_Draft_Security::digest($token),'expires_at'=>$now->getTimestamp()+3600,'email'=>'private@example.invalid','private_geo'=>['lat'=>1],'original_photo'=>'secret'];
verifyDraft(BadAround_Quick_Draft_Security::authorize($token,$record,$now),'good token');
verifyDraft(!BadAround_Quick_Draft_Security::authorize(str_repeat('0',64),$record,$now),'invalid token');
verifyDraft(!BadAround_Quick_Draft_Security::authorize($token,array_merge($record,['revoked'=>true]),$now),'revoked');
verifyDraft(!BadAround_Quick_Draft_Security::authorize($token,array_merge($record,['expires_at'=>$now->getTimestamp()]),$now),'expired');
verifyDraft(!BadAround_Quick_Draft_Security::authorize($token,array_merge($record,['state'=>'submitted_for_moderation']),$now),'submitted');
$view=BadAround_Quick_Draft_Security::safePublicView($record);
verifyDraft(!array_key_exists('email',$view)&&!array_key_exists('private_geo',$view)&&!array_key_exists('token_hash',$view),'no private field leak');
echo "QUICK-02A private-draft token lifecycle PASS\n";
