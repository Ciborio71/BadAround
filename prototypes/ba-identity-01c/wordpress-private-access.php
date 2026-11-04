<?php
/**
 * BA-IDENTITY-02A: private QA network fence, independent of runtime flags.
 * Uses the peer address supplied by the web server, not spoofable forwarding
 * headers. Reverse-proxy installations need an independently reviewed gateway.
 */
declare(strict_types=1);
final class BadAround_Identity_Private_Access {
    public static function authorized():bool {
        if (!defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            || BADAROUND_IDENTITY_PRIVATE_QA_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_QA_IP_ALLOWLIST')
            || !is_array(BADAROUND_IDENTITY_QA_IP_ALLOWLIST)) {
            return false;
        }
        $list=BADAROUND_IDENTITY_QA_IP_ALLOWLIST;
        if ($list===[] || count($list)>16) {return false;}
        $peer=$_SERVER['REMOTE_ADDR']??null;
        if (!is_string($peer) || filter_var($peer,FILTER_VALIDATE_IP)===false) {
            return false;
        }
        foreach ($list as $value) {
            if (!is_string($value) || filter_var($value,FILTER_VALIDATE_IP)===false) {
                // An invalid or wildcard entry renders the whole policy invalid.
                return false;
            }
        }
        return in_array($peer,$list,true);
    }
}
