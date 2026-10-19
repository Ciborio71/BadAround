<?php
/**
 * GEO-PRIV-03A isolated WordPress lifecycle regression.
 * Run: php tests/geo-priv-03a-published-save.php
 * No WordPress install or live database.
 */
define('ABSPATH', __DIR__);
class WP_Error {}
class BadAround_Event_Post_Type { const POST_TYPE='ba_evento'; const TERRITORY_TAX='ba_territorio'; }
class BadAround_Publication_Service { const STATUS_PUBLISHED='published'; }
class BadAround_Audit_Log {
    public static function record(...$args) { $GLOBALS['audit'][]=$args[2]??''; }
}
function absint($x){return abs((int)$x);}
function sanitize_key($x){return strtolower(preg_replace('/[^a-z0-9_-]/i','',(string)$x));}
function sanitize_text_field($x){return trim(strip_tags((string)$x));}
function wp_unslash($x){return $x;}
function wp_kses_post($x){return (string)$x;}
function wp_json_encode($x){return json_encode($x);}
function wp_verify_nonce($nonce,$action){return $nonce==='nonce-'.substr($action,strlen('ba_save_public_fields_'));}
function current_user_can($cap,...$args){return $GLOBALS['authorized'];}
function get_post($id){return $GLOBALS['post'][$id]??null;}
function get_post_status($id){return $GLOBALS['post'][$id]->post_status??false;}
function get_post_type($id){return $GLOBALS['post'][$id]->post_type??'';}
function get_post_meta($id,$key='',$single=true){return $key===''?($GLOBALS['meta'][$id]??[]):($GLOBALS['meta'][$id][$key]??'');}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function wp_get_post_terms($id,$tax,$args=[]){return [];}
function wp_set_object_terms($id,$terms,$tax,$append=false){return $terms;}
function term_exists($id,$tax){return true;}
function is_wp_error($x){return $x instanceof WP_Error;}
function add_filter($name,$fn,$priority=10,$args=1){$GLOBALS['hooks'][$name][$priority][]= $fn;}
function add_action($name,$fn,$priority=10,$args=1){add_filter($name,$fn,$priority,$args);}
function remove_filter($name,$fn,$priority=10){foreach(($GLOBALS['hooks'][$name][$priority]??[]) as $i=>$cb){if($cb===$fn)unset($GLOBALS['hooks'][$name][$priority][$i]);}}
function remove_action($name,$fn,$priority=10){remove_filter($name,$fn,$priority);}
function apply_filters($name,$value,...$args){$groups=$GLOBALS['hooks'][$name]??[];ksort($groups);foreach($groups as $cbs){foreach($cbs as $f){$value=$f($value,...$args);}}return $value;}
function do_action($name,...$args){$groups=$GLOBALS['hooks'][$name]??[];ksort($groups);foreach($groups as $cbs){foreach($cbs as $f){$f(...$args);}}}
function wp_update_post($values,$wp_error=false){
    $id=$values['ID'];$post=$GLOBALS['post'][$id];$old=$post->post_status;
    $next=array_merge((array)$post,$values);
    $next=apply_filters('wp_insert_post_data',$next,$values);
    foreach($next as $key=>$value){if($key!=='ID')$post->$key=$value;}
    do_action('transition_post_status',$post->post_status,$old,$post);
    do_action('save_post_ba_evento',$id,$post);
    return $id;
}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-moderation-admin.php';
function verify($condition,$label){if(!$condition){fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
function scenario($wp,$moderation,$nonce=true,$authorized=true){
    $GLOBALS['hooks']=[];$GLOBALS['audit']=[];$GLOBALS['authorized']=$authorized;
    $GLOBALS['post']=[90=>(object)['ID'=>90,'post_type'=>'ba_evento','post_status'=>$wp,'post_title'=>'Initial','post_content'=>'Initial content']];
    $GLOBALS['meta']=[90=>['_ba_moderation_status'=>$moderation,'social_visibility'=>'requested','_private_marker'=>'secret']];
    $_POST=$nonce?['ba_public_fields_nonce'=>'nonce-90','ba_public_title'=>'Safe update','ba_public_content'=>'Approved public edit']:[];
    $admin=new BadAround_Moderation_Admin();
    add_filter('wp_insert_post_data',[$admin,'prevent_b2_publish'],20,2);
    add_action('transition_post_status',[$admin,'enforce_b2_pending_status'],20,3);
    add_action('save_post_ba_evento',[$admin,'save_public_fields'],10,2);
    return $admin;
}
foreach(['new','in_review','needs_information','approved','rejected'] as $m){
    scenario('pending',$m);
    wp_update_post(['ID'=>90,'post_title'=>'Edit from admin']);
    verify(get_post_status(90)==='pending',"pending/$m remains non-public");
    verify(get_post_meta(90,'_ba_moderation_status')===$m,"pending/$m moderation unchanged");
}
scenario('publish','published');
wp_update_post(['ID'=>90,'post_title'=>'Edit from admin']);
verify(get_post_status(90)==='publish','published stays published during WordPress and meta box save');
verify(get_post_meta(90,'_ba_moderation_status')==='published','published moderation remains published');
verify(get_post_meta(90,'social_visibility')==='requested','social preference unchanged');
verify(get_post_meta(90,'_private_marker')==='secret','private metadata unchanged');
wp_update_post(['ID'=>90,'post_title'=>'Second admin edit']);
verify(get_post_status(90)==='publish','published repeat save remains published');
scenario('pending','approved');
wp_update_post(['ID'=>90,'post_status'=>'publish']);
verify(get_post_status(90)==='pending','unpublished approved cannot publish through direct WordPress update');
scenario('pending','published');
wp_update_post(['ID'=>90,'post_title'=>'Conflicting update']);
verify(get_post_status(90)!=='publish','pending/published contradictory state never promotes');
verify(in_array('moderation_public_save_status_conflict',$GLOBALS['audit'],true),'contradictory pending/published audited');
scenario('publish','approved');
wp_update_post(['ID'=>90,'post_title'=>'Conflicting update']);
verify(get_post_status(90)!=='publish','publish/approved contradictory state fails closed');
scenario('publish','published',false);
wp_update_post(['ID'=>90,'post_title'=>'No moderated edit token']);
verify(get_post_status(90)!=='publish','no nonce cannot use published-edit exception');
scenario('publish','published',true,false);
wp_update_post(['ID'=>90,'post_title'=>'No moderation capability']);
verify(get_post_status(90)!=='publish','unauthorized actor cannot use published-edit exception');
echo "GEO-PRIV-03A isolated tests complete.\n";
