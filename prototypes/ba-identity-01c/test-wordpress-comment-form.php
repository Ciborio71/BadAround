<?php
/** BA-IDENTITY-02F: private unpublished/restricted comment form tests. */
declare(strict_types=1);
require __DIR__.'/wordpress-comment-form.php';
function qa_comment_check(bool $ok,string $label):void {
    if (!$ok)throw new RuntimeException('FAIL '.$label);
    echo 'PASS '.$label.PHP_EOL;
}
qa_comment_check(!BadAround_Identity_WordPress_Comment_Form::enabled(),
    'anonymous visitor cannot enable form');
define('BADAROUND_IDENTITY_PORTAL_ENABLED',true);
define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['127.0.0.1']);
define('BADAROUND_IDENTITY_PRIVACY_APPROVED_VERSION','privacy-20261024T162603Z');
define('BADAROUND_CORE_FILE','/some/isolated/badaround-core.php');
$GLOBALS['qa_event']=(object)['post_type'=>'ba_evento','post_status'=>'publish'];
$GLOBALS['qa_status']='published';
$GLOBALS['qa_privacy']=(object)[
    'ID'=>3,'post_type'=>'page','post_status'=>'publish','post_name'=>'privacy-policy',
    'post_modified_gmt'=>'2026-10-24 16:26:03',
    'post_content'=>'Informativa revisionata per il test.'
];
function get_post($id) {
    return $id===3?$GLOBALS['qa_privacy']:($id===123?$GLOBALS['qa_event']:null);
}
function get_queried_object_id(){return 123;}
function get_post_meta($id,$name,$single){return $GLOBALS['qa_status'];}
function plugins_url($path,$file){return 'https://badaround.it/wp-content/plugins/badaround-core/'.$path;}
function wp_enqueue_script($h,$u,$deps,$ver,$foot){$GLOBALS['qa_script']=$u;}
function add_shortcode($name,$callback){$GLOBALS['qa_shortcode']=$name;}
$_SERVER['REMOTE_ADDR']='127.0.0.1';
qa_comment_check(BadAround_Identity_WordPress_Comment_Form::register_hooks()
    && $GLOBALS['qa_shortcode']==='badaround_identity_comment_form',
    'comment shortcode registered only for authorized QA');
$html=BadAround_Identity_WordPress_Comment_Form::render();
qa_comment_check(str_contains($html,'data-event-id="123"')
    && str_contains($html,'maxlength="2500"')
    && str_contains($html,'prima della pubblicazione')
    && str_contains($GLOBALS['qa_script'],'identity-comment-form.js'),
    'canonical published event form queues moderated text-only comments');
$GLOBALS['qa_status']='pending';
qa_comment_check(BadAround_Identity_WordPress_Comment_Form::render()==='',
    'pending/unpublished moderator state cannot offer comment form');
$GLOBALS['qa_status']='published';
$GLOBALS['qa_event']->post_status='draft';
qa_comment_check(BadAround_Identity_WordPress_Comment_Form::render()==='',
    'draft event cannot offer comment form');
$GLOBALS['qa_event']->post_status='publish';
$GLOBALS['qa_privacy']->post_content='BOZZA PRE-PUBBLICAZIONE';
qa_comment_check(BadAround_Identity_WordPress_Comment_Form::render()==='',
    'unapproved legal draft disables comment form');
echo "ALL_WORDPRESS_PRIVATE_COMMENT_FORM_TESTS_PASS\n";
