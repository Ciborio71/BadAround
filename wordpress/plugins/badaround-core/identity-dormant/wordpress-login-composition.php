<?php
/**
 * BA-IDENTITY-01N — dormant PDO composition for verified login OTP.
 * No routes, no mail, no cookie emission, no WP Core modifications.
 * A fully reviewed server secret and mail/controller flow are required.
 */
declare(strict_types=1);
require_once __DIR__.'/mysql-verified-resolver.php';
require_once __DIR__.'/mysql-challenge-store.php';
require_once __DIR__.'/mysql-session-store.php';
require_once __DIR__.'/purpose-keys.php';

final class BadAround_Identity_WordPress_Login_Composition {
    public static function assemble(
        PDO $db,string $prefix,string $rootKey
    ): BadAround_Identity_Verification_Coordinator {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix) || strlen($rootKey)<32) {
            throw new InvalidArgumentException('Invalid trusted login configuration');
        }
        $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $challenges=new BadAround_Identity_Mysql_Challenge_Store(
            $db,$prefix.'ba_identity_challenges'
        );
        $verifier=new BadAround_Identity_Challenge_Verifier(
            $challenges,BadAround_Identity_Purpose_Keys::for_purpose('login',$rootKey)
        );
        $resolver=new BadAround_Identity_Mysql_Verified_Resolver(
            $db,$prefix.'ba_identities'
        );
        $sessionKey=hash_hmac('sha256','BA-ID-01H:sessions',$rootKey,true);
        $sessions=new BadAround_Identity_Sessions(
            new BadAround_Identity_Mysql_Session_Store(
                $db,$prefix.'ba_identity_sessions'
            ),$sessionKey
        );
        return new BadAround_Identity_Verification_Coordinator(
            $challenges,$verifier,$resolver,$sessions
        );
    }
}
