<?php
/**
 * Plugin Name: BadAround Identity administrative diagnostics
 * Description: Administrative observations only. Never enables QA or sends OTP.
 */
if (!defined('ABSPATH')) { exit; }
function badaround_identity_ops_facts() {
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
                'x_real_ip_observed_not_trusted'=>filter_var($_SERVER['HTTP_X_REAL_IP']??'',FILTER_VALIDATE_IP)?:null,
                'x_forwarded_for_observed_not_trusted'=>array_values(array_filter(array_slice(array_map('trim',explode(',',(string)($_SERVER['HTTP_X_FORWARDED_FOR']??''))),0,5),static fn($v)=>filter_var($v,FILTER_VALIDATE_IP)!==false)),
                'configured_peer_matches_request'=>is_array($ips)&&in_array($_SERVER['REMOTE_ADDR']??null,$ips,true),
                'ip_option_is_single_list'=>is_array($ips)&&array_is_list($ips)&&count($ips)===1,
                'mail_option_is_single_list'=>is_array($emails)&&array_is_list($emails)&&count($emails)===1,
                'notice_integrity_valid'=>$notice,'flags_for_this_request'=>$flags,
                'root_key_present'=>defined('BADAROUND_IDENTITY_ROOT_SECRET'),
                'mail_credentials_present'=>$mail,'missing_innodb_tables'=>$missing,
                'bootstrap_classes'=>array_map(static fn($v)=>class_exists($v,false),['BadAround_Identity_WordPress_Private_Bootstrap','BadAround_Identity_WordPress_Registration_Composition','BadAround_Identity_WordPress_Login_Composition','BadAround_Identity_WordPress_QA_Report_Composition','BadAround_Identity_QA_Report_REST']),
                'portal_class_loaded'=>class_exists('BadAround_Identity_WordPress_Portal',false),
                'portal_shortcode_registered'=>shortcode_exists('badaround_identity_portal'),
                'recent_successful_tester_page_requests'=>array_slice($recent,-5),
            ];

            return $facts;

}
add_action('template_redirect',static function(){
    if (is_page(389) && current_user_can('manage_options')) {
        $facts=badaround_identity_ops_facts();
        $facts['captured_at_gmt']=gmdate('c');
        set_transient('badaround_identity_last_admin_qa_view',$facts,600);
    }
},1);
add_action('init',static function(){
    $nonce=$_SERVER['HTTP_X_BADAROUND_OPS_PROBE']??null;
    $expected=get_transient('badaround_identity_ops_probe_hash');
    if(!is_string($nonce)||!preg_match('/^[a-f0-9]{32}$/D',$nonce)
        ||!is_string($expected)||!hash_equals($expected,hash('sha256',$nonce)))return;
    $facts=badaround_identity_ops_facts();
    $facts['captured_at_gmt']=gmdate('c');
    set_transient('badaround_identity_ops_probe_result',$facts,30);
},1);
add_action('rest_api_init',static function(){
    $admin=static function(){return current_user_can('manage_options');};
    register_rest_route('badaround-ops/v1','/identity-readiness',[
        'methods'=>'GET','permission_callback'=>$admin,'callback'=>static function(){
            $facts=badaround_identity_ops_facts();
            $facts['last_administrator_qa_page_view']=get_transient('badaround_identity_last_admin_qa_view')?:null;
            $response=new WP_REST_Response($facts,200);
            $response->header('Cache-Control','private, no-store, max-age=0');return $response;
        },
    ]);
    register_rest_route('badaround-ops/v1','/identity-proxy-probe',[
        'methods'=>'POST','permission_callback'=>$admin,'callback'=>static function(){
            $nonce=bin2hex(random_bytes(16));
            delete_transient('badaround_identity_ops_probe_result');
            set_transient('badaround_identity_ops_probe_hash',hash('sha256',$nonce),30);
            $result=wp_remote_get(home_url('/?ba_ops_probe=1&ba_ops_run='.time()),[
                'timeout'=>10,'redirection'=>0,'headers'=>[
                    'X-Badaround-Ops-Probe'=>$nonce,'X-Real-IP'=>'198.51.100.77',
                    'X-Forwarded-For'=>'198.51.100.88','Cache-Control'=>'no-cache',
                ],
            ]);
            $capture=get_transient('badaround_identity_ops_probe_result')?:null;
            delete_transient('badaround_identity_ops_probe_hash');
            delete_transient('badaround_identity_ops_probe_result');
            $response=new WP_REST_Response([
                'probe_http_status'=>is_wp_error($result)?null:wp_remote_retrieve_response_code($result),
                'capture'=>$capture,'note'=>'Observation only; forwarding headers never authorize this probe.',
            ],200);
            $response->header('Cache-Control','private, no-store, max-age=0');return $response;
        },
    ]);
});

/** Administrative media observations; no configuration, upload or admission writes. */
add_action('rest_api_init',static function(){
 register_rest_route('badaround-ops/v1','/media-readiness',[
  'methods'=>'GET','permission_callback'=>static function(){return current_user_can('manage_options');},
  'callback'=>static function(){
   global $wpdb;
   $facts=['config_defined'=>defined('BADAROUND_NATIVE_MEDIA_CONFIG'),'classes_loaded'=>class_exists('BadAround_Native_Media_Config')];
   if($facts['classes_loaded']){
    $c=new BadAround_Native_Media_Config();
    $s=new BadAround_Native_Media_Storage($c);
    $facts['enabled']=$c->enabled();
    foreach(['enabled','hosting_verified','temporary_budget','archive_budget'] as $k)$facts['settings'][$k]=$c->get($k);
    $facts['active_key_valid']=(bool)$c->key($c->get('active_key_id'));
    $facts['private_root_valid']=(bool)$s->root();
    $legacy=defined('BADAROUND_PRIVATE_MEDIA_PATH')?BADAROUND_PRIVATE_MEDIA_PATH:dirname(rtrim(ABSPATH,'/')).'/badaround-private-media';
    $legacy=apply_filters('badaround_private_media_path',$legacy);
    $facts['legacy_archive_matches']=is_string($legacy)&&$s->root()&&realpath($legacy)===$s->root();
    $facts['upload_limits']=['upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size'),'max_file_uploads'=>ini_get('max_file_uploads')];
    $facts['process_functions']=['proc_open'=>function_exists('proc_open'),'proc_terminate'=>function_exists('proc_terminate')];
    foreach(['decoder_php_binary','decoder_gd_extension','decoder_posix_extension'] as $k){$p=$c->get($k);$facts['decoder_files'][$k]=is_string($p)&&is_file($p)&&((fileperms($p)&0022)===0);}
    $facts['runtime_paths']=['php_binary'=>PHP_BINARY,'extension_dir'=>ini_get('extension_dir'),'open_basedir'=>ini_get('open_basedir')];
    foreach(['/opt/plesk/php/8.3/bin/php','/opt/plesk/php/8.3/lib/php/modules/gd.so','/opt/plesk/php/8.3/lib/php/modules/posix.so','/usr/bin/php','/usr/bin/php8.3'] as $p)$facts['candidate_paths'][$p]=is_file($p);
    $facts['supported_codecs']=(new BadAround_Native_Media_Validator($c))->codecs();
    $facts['ready']=(new BadAround_Native_Media_Service($c))->ready();
   }
   $r=new WP_REST_Response($facts,200);$r->header('Cache-Control','private, no-store, max-age=0');return $r;
  }
 ]);
});
