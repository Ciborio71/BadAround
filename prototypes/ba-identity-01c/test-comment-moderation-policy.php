<?php
declare(strict_types=1);
require __DIR__.'/comment-moderation-policy.php';
function cm_ok(bool $v,string $what):void {
    if (!$v) { throw new RuntimeException('FAIL '.$what); }
    echo 'PASS '.$what.PHP_EOL;
}
function cm_reject(callable $fn,string $what):void {
    try { $fn(); } catch (InvalidArgumentException|DomainException $e) {
        echo 'PASS '.$what.PHP_EOL; return;
    }
    throw new RuntimeException('FAIL '.$what);
}
$now=1762156800;
$pending=BadAround_Identity_Comment_Moderation_Policy::propose(
    'identity_12345678',801,'Ho visto il veicolo in zona','anonymous',null,$now
);
cm_ok($pending['moderation_status']==='pending'
    && BadAround_Identity_Comment_Moderation_Policy::public_view($pending)===null,
    'pending comment private');
cm_reject(fn()=>BadAround_Identity_Comment_Moderation_Policy::propose(
    'identity_12345678',801,'ok','anonymous',null,$now),'short comment denied');
$reply=BadAround_Identity_Comment_Moderation_Policy::propose(
    'identity_12345678',801,'Confermo quanto segnalato','pseudonym',
    ['event_id'=>801,'moderation_status'=>'approved','comment_id'=>7,'parent_id'=>null],$now
);
cm_ok($reply['parent_id']===7,'one-level reply accepted');
cm_reject(fn()=>BadAround_Identity_Comment_Moderation_Policy::propose(
    'identity_12345678',801,'Una risposta annidata','anonymous',
    ['event_id'=>801,'moderation_status'=>'approved','comment_id'=>8,'parent_id'=>7],$now),
    'nested reply refused');
$approved=BadAround_Identity_Comment_Moderation_Policy::decide(
    $pending,'approved','moderator_12345',$now+1
);
$view=BadAround_Identity_Comment_Moderation_Policy::public_view($approved);
cm_ok($view!==null && $view['body']==='Ho visto il veicolo in zona'
    && !array_key_exists('identity_id',$view) && !array_key_exists('moderator_id',$view),
    'approved public view excludes private identity');
cm_reject(fn()=>BadAround_Identity_Comment_Moderation_Policy::decide(
    $approved,'approved','moderator_12345',$now+2),'double moderation denied');
$rejected=BadAround_Identity_Comment_Moderation_Policy::decide(
    $reply,'rejected','moderator_12345',$now+1
);
cm_ok(BadAround_Identity_Comment_Moderation_Policy::public_view($rejected)===null,
    'rejected comment never public');
echo "ALL_COMMENT_MODERATION_TESTS_PASS\n";
