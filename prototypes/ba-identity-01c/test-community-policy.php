<?php
declare(strict_types=1);
require __DIR__ . '/community-policy.php';
function expect(bool $ok,string $label): void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo "PASS ".$label."\n";
}
function must_throw(callable $callback,string $label): void {
    try { $callback(); } catch (Throwable $e) { echo "PASS ".$label."\n"; return; }
    throw new RuntimeException('FAIL '.$label);
}
$identity='identity_12345678';
expect(BadAround_Identity_Community_Policy::public_identity('Anna','Rossi','anonymous') === 'Anonimo','anonymous mode');
expect(BadAround_Identity_Community_Policy::public_identity('Anna','Rossi','first_name') === 'Anna','first name mode');
expect(BadAround_Identity_Community_Policy::public_identity('Anna','Rossi','first_name_initial') === 'Anna R.','initial mode');
expect(BadAround_Identity_Community_Policy::public_identity('Anna','Rossi','pseudonym','Luna') === 'Luna','pseudonym mode');
must_throw(fn()=>BadAround_Identity_Community_Policy::public_identity('Anna','Rossi','pseudonym',''), 'empty alias refused');
$comment=BadAround_Identity_Community_Policy::comment_intent($identity,501,'Ho nuove informazioni.','anonymous');
expect($comment['moderation_status']==='pending' && $comment['parent_id'] === null,'comment never public by default');
$parent=['comment_id'=>44,'parent_id'=>null,'event_id'=>501,'moderation_status'=>'approved'];
$reply=BadAround_Identity_Community_Policy::comment_intent($identity,501,'Grazie della segnalazione','first_name',$parent);
expect($reply['parent_id']===44 && $reply['moderation_status']==='pending','one-level reply pending');
must_throw(fn()=>BadAround_Identity_Community_Policy::comment_intent($identity,501,'Grazie','anonymous',array_merge($parent,['parent_id'=>43])),'nested replies rejected');
must_throw(fn()=>BadAround_Identity_Community_Policy::comment_intent($identity,502,'Grazie','anonymous',$parent),'cross-event replies rejected');
must_throw(fn()=>BadAround_Identity_Community_Policy::comment_intent($identity,501,'Grazie','anonymous',array_merge($parent,['moderation_status'=>'pending'])),'pending parent replies rejected');
must_throw(fn()=>BadAround_Identity_Community_Policy::revision_intent($identity,701,'Descrizione corretta delle circostanze','',false),'unverified owner revision denied');
$revision=BadAround_Identity_Community_Policy::revision_intent($identity,701,'Descrizione corretta delle circostanze','Data aggiornata',true);
expect($revision['moderation_status']==='pending' && $revision['public_mutation'] === false,'revision awaits moderator and does not publish');
echo "ALL_COMMUNITY_POLICY_TESTS_PASS\n";
