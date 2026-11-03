<?php
declare(strict_types=1);
require __DIR__ . '/session-policy.php';
function ensure(bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAIL: '.$label); }
    echo "PASS: $label\n";
}
$store = new BadAround_Identity_Session_Memory_Store();
$sessions = new BadAround_Identity_Sessions($store,str_repeat('z',32));
$now = 1762156800;
$cookie = $sessions->issue('identity_12345678',$now);
ensure(strlen($cookie)===64, '64-hex session token');
ensure($sessions->identity_for($cookie,$now)==='identity_12345678','own session valid');
ensure($sessions->identity_for($cookie,$now + 86399)==='identity_12345678','session before idle expiry');
ensure($sessions->identity_for($cookie,$now + 86400)===null,'idle timeout enforced');
ensure($sessions->identity_for($cookie,$now + 604800)===null,'absolute expiration');
ensure($sessions->identity_for('invalid',$now)===null,'invalid token rejected');
ensure(!in_array($cookie,array_keys($store->rows),true),'session token not stored directly');
$options = BadAround_Identity_Sessions::cookie_options($now + 604800);
ensure($options['secure'] && $options['httponly'] && $options['samesite']==='Lax','secure cookie contract');
ensure(!array_key_exists('domain',$options),'__Host cookie omits domain');
ensure($sessions->revoke($cookie,$now+1),'revoke live session');
ensure($sessions->identity_for($cookie,$now+2)===null,'revoked token rejected');
ensure(!$sessions->revoke($cookie,$now+3),'second revocation rejected');
echo "ALL_SESSION_TESTS_PASS\n";
