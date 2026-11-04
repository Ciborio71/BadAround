<?php
/** Isolated cookie-transport regression; does not emit actual HTTP headers. */
declare(strict_types=1);
require __DIR__.'/wordpress-session-cookie.php';
final class CookieCapture implements BadAround_Identity_Cookie_Writer {
    public array $writes=[];
    public bool $success=true;
    public bool $throw=false;
    public function write(string $name,string $value,array $options):bool {
        if ($this->throw) { throw new RuntimeException('Transport unavailable'); }
        $this->writes[]=[$name,$value,$options];
        return $this->success;
    }
}
function cookie_assert(bool $ok,string $label):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
$writer=new CookieCapture();
$cookie=new BadAround_Identity_WordPress_Session_Cookie($writer);
$now=1762156800;
$secret=str_repeat('a',64);
cookie_assert(!$cookie->issue($secret,$now,false) && count($writer->writes)===0,
    'HTTP or unverifiable HTTPS cannot set auth cookie');
cookie_assert(!$cookie->issue('invalid',$now,true) && count($writer->writes)===0,
    'invalid bearer cannot become cookie');
cookie_assert($cookie->issue($secret,$now,true) && count($writer->writes)===1,
    'valid cookie emitted only with verified TLS');
[$name,$value,$options]=$writer->writes[0];
cookie_assert($name==='__Host-ba_identity' && $value===$secret
    && ($options['path']??null)==='/'
    && ($options['secure']??null)===true
    && ($options['httponly']??null)===true
    && ($options['samesite']??null)==='Lax'
    && !isset($options['domain'])
    && ($options['expires']??null)===$now+BadAround_Identity_Sessions::LIFETIME,
    'host-prefixed, HttpOnly, Secure, Lax, host-only, 7-day cookie');
cookie_assert($cookie->clear($now,true) && count($writer->writes)===2
    && $writer->writes[1][1]===''
    && $writer->writes[1][2]['expires']<$now,
    'logout expires cookie immediately with same attributes');
$writer->success=false;
cookie_assert(!$cookie->issue($secret,$now,true),
    'cookie emission failure does not report success');
$writer->throw=true;
cookie_assert(!$cookie->issue($secret,$now,true),
    'cookie emitter exception fails closed');
echo "ALL_WORDPRESS_SESSION_COOKIE_TESTS_PASS\n";
