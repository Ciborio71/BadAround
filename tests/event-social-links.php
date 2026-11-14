<?php
define('ABSPATH',__DIR__);
$GLOBALS['meta']=[1=>['_ba_moderation_status'=>'published']];$GLOBALS['authorized']=true;$GLOBALS['published']='publish';
function add_action(){}function get_post_type($id){return 'ba_evento';}function current_user_can(){return $GLOBALS['authorized'];}
function get_post_status($id){return $GLOBALS['published'];}function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$v){$GLOBALS['meta'][$id][$key]=$v;}
function wp_json_encode($v){return json_encode($v);}function wp_salt($context){return 'synthetic-test-key';}
function esc_url($v){return htmlspecialchars($v,ENT_QUOTES);}function esc_html($v){return htmlspecialchars($v,ENT_QUOTES);}function esc_attr($v){return htmlspecialchars($v,ENT_QUOTES);}
class SocialDB{public $prefix='wp_';public $choice='requested';public $version='social-preference-v1';function prepare($q,...$args){return $q;}function get_var($q){return json_encode(['canonical'=>['reporter'=>['social_visibility'=>$this->choice],'consents'=>['social_visibility_version'=>$this->version]]]);}}
$wpdb=new SocialDB();$GLOBALS['meta'][1]['_ba_primary_report_id']=5;
require __DIR__.'/../wordpress/themes/badaround-child/inc/event-social.php';
function social_check($v,$label){if(!$v)throw new RuntimeException($label);echo "PASS $label\n";}
$links=['facebook'=>'https://www.facebook.com/BadAround/posts/123','instagram'=>'https://www.instagram.com/p/Test123/','tiktok'=>'https://www.tiktok.com/@badaround/video/123','x'=>'https://x.com/badaround/status/123','google'=>'https://search.google.com/local/posts?q=test'];
foreach($links as $key=>$url)social_check(badaround_event_social_url($key,$url)===$url,'published permalink '.$key);
foreach(['javascript:alert(1)','http://x.com/badaround/status/123','https://x.com.evil.test/badaround/status/123','https://x.com@evil.test/badaround/status/123','https://x.com/badaround','https://x.com:8443/badaround/status/123','https://x.com/intent/tweet?url=x'] as $url)social_check(badaround_event_social_url('x',$url)==='','unsafe/profile URL rejected');
social_check(badaround_event_social_links(1)===[],'no requested preference falsely presented as published');
$GLOBALS['authorized']=false;social_check(!badaround_event_social_save(1,$links,true),'capabilities required');$GLOBALS['authorized']=true;
social_check(!badaround_event_social_save(1,$links,false),'publication confirmation required');
$wpdb->choice='declined';social_check(!badaround_event_social_save(1,$links,true),'declined request cannot create links');$wpdb->choice='requested';
$wpdb->version='unrecognized';social_check(!badaround_event_social_save(1,$links,true),'request version required');$wpdb->version='social-preference-v1';
$GLOBALS['published']='pending';social_check(!badaround_event_social_save(1,$links,true),'pending event denied');$GLOBALS['published']='publish';
social_check(badaround_event_social_save(1,$links,true),'authorized published links stored');social_check(badaround_event_social_links(1)===$links,'sealed public read model');
ob_start();badaround_event_social_render(1);$html=ob_get_clean();social_check(substr_count($html,'<a ')===5&&substr_count($html,'<svg ')===5,'five public platform links with local icons');
$record=$GLOBALS['meta'][1]['_ba_social_published_posts'];$GLOBALS['meta'][1]['_ba_social_published_posts']['links']['x']='https://x.com/attacker/status/987';social_check(badaround_event_social_links(1)===[],'tampered links fail closed');$GLOBALS['meta'][1]['_ba_social_published_posts']=$record;
social_check(badaround_event_social_save(1,[],false)&&badaround_event_social_links(1)===[],'moderation can remove links');
echo "EVENT_SOCIAL_LINKS_PASS\n";
