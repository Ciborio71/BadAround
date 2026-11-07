<?php
declare(strict_types=1);
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-identity-portal.php';
$options=[];$pages=[];
function get_option($name,$default=null){return $GLOBALS['options'][$name]??$default;}
function get_post($id){return $GLOBALS['pages'][$id]??null;}
function home_url($path='/'){return 'https://badaround.it'.$path;}
function qa_notice_check(bool $ok,string $label):void {
    if(!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
qa_notice_check(BadAround_Identity_WordPress_Privacy_Notice::runtime_version()===null,
    'no default activation or accepted public notice');
define('BADAROUND_IDENTITY_QA_NOTICE_ENABLED',true);
qa_notice_check(BadAround_Identity_WordPress_Privacy_Notice::runtime_version()===null,
    'QA notice alone cannot enable runtime');
define('BADAROUND_IDENTITY_RUNTIME_INTEGRATION_ENABLED',true);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);
define('BADAROUND_IDENTITY_PORTAL_ENABLED',true);
$_SERVER['REMOTE_ADDR']='127.0.0.1';
qa_notice_check(!BadAround_Identity_WordPress_Portal::enabled(),'missing owner adoption denies portal');
$text='<h2>Informativa del collaudo</h2><p>Dati di prova.</p>';
$hash=hash('sha256',$text);$version='qa-privacy-'.substr($hash,0,16);
$record=['page_id'=>388,'page_slug'=>'identity-qa-informativa','sha256'=>$hash,
    'version'=>$version,'adopted_at_gmt'=>'2026-11-05T22:33:18','scope'=>'private_identity_qa',
    'state'=>'owner_adopted','legal_review_verified'=>false,
    'approval_source'=>'explicit_user_approval_in_conversation'];
$options['badaround_identity_qa_notice_adoption']=$record;
$pages[388]=(object)['ID'=>388,'post_type'=>'page','post_status'=>'private',
    'post_name'=>'identity-qa-informativa','post_content'=>$text];
qa_notice_check(BadAround_Identity_WordPress_Privacy_Notice::runtime_version()===$version,
    'adopted content hash permits private QA scope');
qa_notice_check(BadAround_Identity_WordPress_Privacy_Notice::verified_version()===null,
    'QA adoption never approves the public notice');
qa_notice_check(BadAround_Identity_WordPress_Privacy_Notice::qa_record()['legal_review_verified']===false,
    'owner adoption never fabricates legal review');
qa_notice_check(BadAround_Identity_WordPress_Portal::enabled(),'private portal uses the adopted QA scope');
require_once __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-qa-report-rest.php';
define('BADAROUND_IDENTITY_QA_REPORTS_ENABLED',true);
qa_notice_check(BadAround_Identity_QA_Report_REST::enabled(),
    'private report endpoint uses the same adopted QA notice without approving the public policy');
foreach(['scope'=>'public','state'=>'draft','approval_source'=>'request_input','version'=>'foreign-version',
    'page_id'=>'388','sha256'=>str_repeat('0',64),'legal_review_verified'=>'true'] as $field=>$bad) {
    $options['badaround_identity_qa_notice_adoption']=array_replace($record,[$field=>$bad]);
    qa_notice_check(!BadAround_Identity_WordPress_Portal::enabled(),'invalid adoption '.$field.' denies');
}
$options['badaround_identity_qa_notice_adoption']=$record;
$pages[388]->post_content.=' changed';
qa_notice_check(!BadAround_Identity_WordPress_Portal::enabled(),'content change requires new adoption');
qa_notice_check(!BadAround_Identity_QA_Report_REST::enabled(),'changed QA notice also disables report endpoint');
$pages[388]->post_content=$text;$pages[388]->post_status='publish';
qa_notice_check(!BadAround_Identity_WordPress_Portal::enabled(),'public page cannot be used as private QA adoption');
$pages[388]->post_status='private';$_SERVER['REMOTE_ADDR']='203.0.113.8';
qa_notice_check(!BadAround_Identity_WordPress_Portal::enabled(),'non-allowlisted peer denied');
$_SERVER['REMOTE_ADDR']='127.0.0.1';
function plugins_url($path,$root){return 'https://badaround.it/wp-content/plugins/badaround-core/'.$path;}
function wp_enqueue_script(...$args):void{}
function esc_url($url){return htmlspecialchars($url,ENT_QUOTES,'UTF-8');}
function wp_kses_post($html){return strip_tags($html,'<h2><p>');}
define('BADAROUND_CORE_FILE','/code/badaround-core.php');
$html=BadAround_Identity_WordPress_Portal::render();
qa_notice_check(str_contains($html,'id="ba-identity-qa-notice"')
    && str_contains($html,$text) && str_contains($html,'href="#ba-identity-qa-notice"'),
    'tester sees the same adopted text before registration');
echo "ALL_PRIVATE_QA_NOTICE_TESTS_PASS\n";
