<?php
/**
 * BA-IDENTITY-01R: gated production-loader seam for WordPress.
 * Does nothing until an explicit private QA flag and root secret exist.
 * No public endpoints are registered by default.
 */
declare(strict_types=1);

final class BadAround_Identity_WordPress_Runtime_Loader {
    public static function boot():bool {
        if (!defined('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED')
            || BADAROUND_IDENTITY_PRIVATE_QA_ENABLED!==true
            || !defined('BADAROUND_IDENTITY_ROOT_SECRET')
            || !is_string(BADAROUND_IDENTITY_ROOT_SECRET)
            || strlen(BADAROUND_IDENTITY_ROOT_SECRET)<48) {
            return false;
        }
        if (!defined('ABSPATH') || !defined('DB_NAME')
            || !defined('DB_USER') || !defined('DB_PASSWORD')
            || !defined('DB_HOST')) { return false; }
        global $wpdb;
        $prefix=$wpdb->prefix??null;
        if (!is_string($prefix)
            || !preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix)) {
            return false;
        }
        try {
            $host=(string)DB_HOST;
            if (!preg_match('/^[A-Za-z0-9._-]+(?::[0-9]{1,5})?$/D',$host)) {
                // Refuse unusual socket, IPv6 or complex DB_HOST descriptors
                // until explicitly tested instead of guessing credentials.
                return false;
            }
            $parts=explode(':',$host,2);
            $dsn='mysql:host='.$parts[0];
            if (isset($parts[1])) {
                $port=(int)$parts[1];
                if ($port<1 || $port>65535) {return false;}
                $dsn.=';port='.$port;
            }
            $dsn.=';dbname='.DB_NAME.';charset=utf8mb4';
            $pdo=new PDO($dsn,DB_USER,DB_PASSWORD,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_TIMEOUT=>4,
            ]);
            require_once __DIR__.'/wordpress-private-bootstrap.php';
            return BadAround_Identity_WordPress_Private_Bootstrap::install(
                $pdo,$prefix
            );
        } catch(Throwable $error) {
            // Never display secrets, connection details or PII.
            return false;
        }
    }
}
