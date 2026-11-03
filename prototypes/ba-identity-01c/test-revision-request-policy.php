<?php
declare(strict_types=1);
require __DIR__.'/revision-request-policy.php';
function rev_check(bool $value,string $label): void {
    if (!$value) { throw new RuntimeException('FAIL: '.$label); }
    echo 'PASS: '.$label.PHP_EOL;
}
function rev_deny(callable $call,string $label): void {
    try { $call(); } catch (InvalidArgumentException|DomainException $e) {
        echo 'PASS: '.$label.PHP_EOL; return;
    }
    throw new RuntimeException('FAIL: '.$label);
}
$now=1762156800;
$digest=hash('sha256','server-loaded immutable report snapshot');
$proposal=BadAround_Identity_Revision_Request_Policy::propose(
    'identity_12345678',701,'Descrizione aggiornata della segnalazione',
    'Nuove informazioni',true,$digest,$now
);
rev_check($proposal['moderation_status']==='pending' && $proposal['public_mutation']===false,
    'revision stays private awaiting moderation');
rev_deny(fn()=>BadAround_Identity_Revision_Request_Policy::propose(
    'identity_12345678',701,'Descrizione aggiornata della segnalazione',
    '',false,$digest,$now),'unverified ownership denied');
rev_deny(fn()=>BadAround_Identity_Revision_Request_Policy::decide(
    $proposal,'approved','moderator_12345',str_repeat('a',64),$now+1),'stale original snapshot denied');
$approved=BadAround_Identity_Revision_Request_Policy::decide(
    $proposal,'approved','moderator_12345',$digest,$now+1
);
rev_check($approved['moderation_status']==='approved' && $approved['public_mutation']===false,
    'editor approval never mutates public report directly');
rev_deny(fn()=>BadAround_Identity_Revision_Request_Policy::decide(
    $approved,'approved','moderator_12345',$digest,$now+2),'double decision denied');
$rejected=BadAround_Identity_Revision_Request_Policy::decide(
    $proposal,'rejected','moderator_12345',$digest,$now+1
);
rev_check($rejected['moderation_status']==='rejected' && $rejected['moderated_at']===$now+1,
    'rejection tracked separately');
echo "ALL_REVISION_REQUEST_POLICY_TESTS_PASS\n";
