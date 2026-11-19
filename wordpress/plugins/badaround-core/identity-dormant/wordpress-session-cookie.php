<?php
/**
 * BA-IDENTITY-01O: dormant secure WordPress session-cookie boundary.
 * The controller MUST authenticate an OTP and verify same-origin HTTPS
 * BEFORE calling this adapter. No routes or hooks are registered here.
 */
declare(strict_types=1);
require_once __DIR__.'/session-policy.php';

interface BadAround_Identity_Cookie_Writer {
    public function write(string $name,string $value,array $options):bool;
}
final class BadAround_Identity_Native_Cookie_Writer implements BadAround_Identity_Cookie_Writer {
    public function write(string $name,string $value,array $options):bool {
        if (headers_sent()) { return false; }
        return setcookie($name,$value,$options);
    }
}
final class BadAround_Identity_WordPress_Session_Cookie {
    public const NAME='__Host-ba_identity';
    public function __construct(private BadAround_Identity_Cookie_Writer $writer) {}

    public function issue(string $secret,int $now,bool $verifiedHttps):bool {
        if (!$verifiedHttps || $now<=0
            || preg_match('/^[a-f0-9]{64}$/D',$secret)!==1) {
            return false;
        }
        $options=BadAround_Identity_Sessions::cookie_options(
            $now+BadAround_Identity_Sessions::LIFETIME
        );
        // __Host- cookies must never contain a Domain attribute.
        if (array_key_exists('domain',$options) || $options['path']!=='/'
            || $options['secure']!==true || $options['httponly']!==true
            || $options['samesite']!=='Lax') {
            return false;
        }
        try {
            return $this->writer->write(self::NAME,$secret,$options)===true;
        } catch(Throwable $error) { return false; }
    }

    public function clear(int $now,bool $verifiedHttps):bool {
        if (!$verifiedHttps || $now<=0) { return false; }
        $options=BadAround_Identity_Sessions::cookie_options($now-3600);
        try { return $this->writer->write(self::NAME,'',$options)===true; }
        catch(Throwable $error) { return false; }
    }
}
