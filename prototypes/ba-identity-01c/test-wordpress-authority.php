<?php
/** CI-only WordPress authority seam test; no WordPress runtime or live DB. */
declare(strict_types=1);
require __DIR__.'/wordpress-authority.php';
$dsn=getenv('BA_TEST_DB_DSN');
if (!$dsn || !str_contains($dsn,'dbname=ba_identity_ci')) {
    throw new RuntimeException('Safety stop: disposable ba_identity_ci required');
}
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS ba_identity_wp_authority_ci (
 identity_id VARCHAR(128) NOT NULL,
 resource_kind VARCHAR(32) NOT NULL,
 resource_id BIGINT UNSIGNED NOT NULL,
 relation VARCHAR(32) NOT NULL,
 verified_binding_at BIGINT UNSIGNED NOT NULL,
 revoked_at BIGINT UNSIGNED NULL,
 UNIQUE KEY resource_unique (resource_kind,resource_id)
) ENGINE=InnoDB");
$db->exec('DELETE FROM ba_identity_wp_authority_ci');
$ownerStore=new BadAround_Identity_Mysql_Ownership_Store($db,'ba_identity_wp_authority_ci');
$now=1762156800;
$ownerStore->grant_creator('identity_12345678','report',701,$now);
$GLOBALS['wp_authority_mock']=[
    'post'=>['post_type'=>'ba_evento','post_status'=>'publish'],
    'moderation'=>'published', 'logged_in'=>false,'cap'=>false
];
function get_post($id) {
    return $id===801 ? (object)$GLOBALS['wp_authority_mock']['post'] : null;
}
function get_post_meta($id,$key,$single) {
    return $id===801 && $key==='_ba_moderation_status'
        ?$GLOBALS['wp_authority_mock']['moderation']:'';
}
function is_user_logged_in() { return $GLOBALS['wp_authority_mock']['logged_in']; }
function current_user_can($cap) {
    return $cap==='ba_moderate_contributions' && $GLOBALS['wp_authority_mock']['cap'];
}
function check_wp_authority(bool $ok,string $label):void {
    if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    echo 'PASS '.$label.PHP_EOL;
}
$key=str_repeat('W',32);
$sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Session_Memory_Store(),$key);
$session=$sessions->issue('identity_12345678',$now);
$status=new BadAround_Identity_Active_Memory_Status();
$status->active['identity_12345678']=true;
$authority=new BadAround_Identity_WordPress_Authority($sessions,$status,$ownerStore);
check_wp_authority($authority->active_identity($session,$now)==='identity_12345678',
    'session verified against active identity');
check_wp_authority($authority->active_identity('spoofed-identity',$now)===null,
    'claimed identity is not a session');
check_wp_authority($authority->canonical_event_is_published(801),
    'canonical published event accepted');
$GLOBALS['wp_authority_mock']['moderation']='approved';
check_wp_authority(!$authority->canonical_event_is_published(801),
    'approved but not published event denied');
$GLOBALS['wp_authority_mock']['moderation']='published';
$GLOBALS['wp_authority_mock']['post']['post_status']='pending';
check_wp_authority(!$authority->canonical_event_is_published(801),
    'pending WordPress post denied');
$GLOBALS['wp_authority_mock']['post']['post_status']='publish';
check_wp_authority($authority->verified_owner_record(701)!==null,
    'server-loaded verified owner accepted');
check_wp_authority($authority->verified_owner_record(702)===null,
    'unknown report cannot infer ownership');
check_wp_authority(!$authority->may_moderate('identity_12345678'),
    'community identity alone cannot moderate');
$GLOBALS['wp_authority_mock']['logged_in']=true;
check_wp_authority(!$authority->may_moderate('identity_12345678'),
    'logged in WordPress user without capability denied');
$GLOBALS['wp_authority_mock']['cap']=true;
check_wp_authority($authority->may_moderate('identity_12345678'),
    'WP capability plus verified identity required');
$status->active['identity_12345678']=false;
check_wp_authority($authority->active_identity($session,$now)===null,
    'revocation invalidates active session');
check_wp_authority($authority->verified_owner_record(701)===null,
    'revoked owner binding not trusted');
check_wp_authority(!$authority->may_moderate('identity_12345678'),
    'revoked identity cannot moderate with WP capability');
echo "ALL_WORDPRESS_AUTHORITY_TESTS_PASS\n";
