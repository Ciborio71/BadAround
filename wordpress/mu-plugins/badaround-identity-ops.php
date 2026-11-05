<?php
/**
 * Plugin Name: BadAround Identity administrative diagnostics
 * Description: Read-only Identity prerequisites for administrators. No activation or email.
 */
if (!defined('ABSPATH')) { exit; }
add_action('rest_api_init', static function() {
    register_rest_route('badaround-ops/v1','/identity-readiness',[
        'methods'=>'GET',
        'permission_callback'=>static function(){return current_user_can('manage_options');},
        'callback'=>static function() {
            global $wpdb;
            $root=dirname(rtrim(ABSPATH,'/\\'));
            $private=$root.'/ba-identity-private-qa.php';
            $ips=get_option('badaround_identity_qa_ip_allowlist',null);
            $emails=get_option('badaround_identity_qa_email_allowlist',null);
            $adoption=get_option('badaround_identity_qa_notice_adoption',null);
            $page=is_array($adoption)&&is_int($adoption['page_id']??null)?get_post($adoption['page_id']):null;
            $notice=is_object($page)&&$page->post_type==='page'&&$page->post_status==='private'
                &&is_string($adoption['sha256']??null)
                &&hash_equals($adoption['sha256'],hash('sha256',$page->post_content));
            $flags=[];
            foreach(['RUNTIME_INTEGRATION_ENABLED','PRIVATE_QA_ENABLED','QA_NOTICE_ENABLED',
                'REGISTRATION_ENABLED','LOGIN_ENABLED','PORTAL_ENABLED','COMMUNITY_ENABLED','QA_REPORTS_ENABLED'] as $name) {
                $constant='BADAROUND_IDENTITY_'.$name;
                $flags[$name]=defined($constant)?constant($constant)===true:false;
            }
            $mail=[];
            foreach(['BADAROUND_TURBOSMTP_CONSUMER_KEY','BADAROUND_TURBOSMTP_CONSUMER_SECRET',
                'BADAROUND_TURBOSMTP_FROM_EMAIL'] as $constant)
                $mail[$constant]=defined($constant)&&is_string(constant($constant))&&constant($constant)!=='';
            $missing=[];
            foreach(['ba_identities','ba_identity_pending','ba_identity_sessions','ba_identity_challenges',
                'ba_identity_rate_limits','ba_identity_ownership','ba_identity_recovery',
                'ba_identity_moderation','ba_identity_wp_actors','ba_identity_community_budget','ba_identity_pre_intake'] as $table) {
                $engine=$wpdb->get_var($wpdb->prepare(
                    'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',
                    $wpdb->prefix.$table
                ));
                if($engine!=='InnoDB')$missing[]=$table;
            }
            $recent=[];
            foreach(['access_ssl_log','proxy_access_ssl_log'] as $filename) {
                $path=$root.'/logs/'.$filename;
                if(!@is_readable($path))continue;
                $file=@fopen($path,'rb');
                if(!$file)continue;
                $size=@filesize($path);
                if(is_int($size)&&$size>131072)fseek($file,$size-131072);
                $tail=stream_get_contents($file,131072);fclose($file);
                if(!is_string($tail))continue;
                foreach(explode("\n",$tail) as $line) {
                    if(preg_match('~^([0-9A-Fa-f:.]+)\\s+.*?\\[([^\\]]+)\\].*?"GET /identity-qa/(?:\\?[^ ]*)? HTTP/[^"]+" 200\\s~',$line,$match))
                        $recent[]=['peer'=>$match[1],'time'=>$match[2],'log'=>$filename];
                }
            }
            $facts=[
                'php_extensions'=>['pdo_mysql'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),'openssl'=>extension_loaded('openssl')],
                'private_file_exists'=>@is_file($private),'private_file_readable'=>@is_readable($private),
                'private_file_permissions'=>@is_file($private)?substr(sprintf('%o',@fileperms($private)),-4):null,
                'private_owner_matches_php'=>function_exists('posix_geteuid')&&@is_file($private)?@fileowner($private)===posix_geteuid():null,
                'request_peer'=>$_SERVER['REMOTE_ADDR']??null,
                'configured_peer_matches_request'=>is_array($ips)&&in_array($_SERVER['REMOTE_ADDR']??null,$ips,true),
                'ip_option_is_single_list'=>is_array($ips)&&array_is_list($ips)&&count($ips)===1,
                'mail_option_is_single_list'=>is_array($emails)&&array_is_list($emails)&&count($emails)===1,
                'notice_integrity_valid'=>$notice,'flags_for_this_request'=>$flags,
                'root_key_present'=>defined('BADAROUND_IDENTITY_ROOT_SECRET'),
                'mail_credentials_present'=>$mail,'missing_innodb_tables'=>$missing,
                'portal_class_loaded'=>class_exists('BadAround_Identity_WordPress_Portal',false),
                'portal_shortcode_registered'=>shortcode_exists('badaround_identity_portal'),
                'recent_successful_tester_page_requests'=>array_slice($recent,-5),
            ];
            $response=new WP_REST_Response($facts,200);
            $response->header('Cache-Control','private, no-store, max-age=0');
            return $response;
        },
    ]);
});
