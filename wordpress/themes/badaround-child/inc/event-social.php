<?php
/** Public post permalinks, entered by authorized moderation after actual publication.
 * No outbound publishing, remote fetch or public reads of the source report.
 */
if(!defined('ABSPATH')){exit;}
function badaround_event_social_channels(){
    return array('facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','x'=>'X','google'=>'Google Business Profile');
}
function badaround_event_social_url($channel,$url){
    if(!is_string($url)||strlen($url)>2000||preg_match('/[\x00-\x20<>]/',$url))return '';
    $p=parse_url($url);
    if(!is_array($p)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['port']))return '';
    $host=strtolower($p['host']??'');$path=$p['path']??'';
    $domains=array('facebook'=>array('facebook.com','www.facebook.com','m.facebook.com'),
      'instagram'=>array('instagram.com','www.instagram.com'),'tiktok'=>array('tiktok.com','www.tiktok.com'),
      'x'=>array('x.com','www.x.com','twitter.com','www.twitter.com'),
      'google'=>array('search.google.com','business.google.com','g.co'));
    if(!in_array($host,$domains[$channel]??array(),true))return '';
    $patterns=array('facebook'=>'~/(?:[^/]+/)?(?:posts|permalink|reel|videos)/[^/]+|^/(?:story|permalink)\.php$~',
      'instagram'=>'~^/(?:p|reel)/[A-Za-z0-9_-]+/?$~','tiktok'=>'~^/@[^/]+/video/[0-9]+/?$~',
      'x'=>'~^/[^/]+/status/[0-9]+/?$~','google'=>'~^/(?:local/posts|posts/[^/]+|kgs/[A-Za-z0-9_-]+)~');
    if(!preg_match($patterns[$channel]??'~(?!)~',$path))return '';
    if($channel==='facebook'&&str_ends_with($path,'.php')&&!preg_match('/(?:^|&)story_fbid=[A-Za-z0-9]+(?:&|$)/',$p['query']??''))return '';
    return $url;
}
function badaround_event_social_requested($id){
    global $wpdb;
    $report=(int)get_post_meta($id,'_ba_primary_report_id',true);
    if(!$report||!is_object($wpdb))return false;
    $raw=$wpdb->get_var($wpdb->prepare("SELECT content_original FROM {$wpdb->prefix}ba_reports WHERE id=%d AND event_id=%d AND deleted_at IS NULL",$report,$id));
    $source=is_string($raw)?json_decode($raw,true):null;$c=$source['canonical']??array();
    return ($c['reporter']['social_visibility']??'')==='requested'
      &&($c['consents']['social_visibility_version']??'')==='social-preference-v1';
}
function badaround_event_social_seal($id,$links){return hash_hmac('sha256',$id.'|'.wp_json_encode($links),wp_salt('auth').'|ba-social-posts/v1');}
function badaround_event_social_links($id){
    if(get_post_status($id)!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published')return array();
    $record=get_post_meta($id,'_ba_social_published_posts',true);
    if(!is_array($record)||!is_array($record['links']??null)||!is_string($record['seal']??null)
      ||!hash_equals(badaround_event_social_seal($id,$record['links']),$record['seal']))return array();
    $out=array();foreach(badaround_event_social_channels() as $key=>$label){
      $url=badaround_event_social_url($key,$record['links'][$key]??'');if($url!=='')$out[$key]=$url;
    }return $out;
}
function badaround_event_social_can_manage($id){return get_post_type($id)==='ba_evento'&&current_user_can('edit_post',$id)&&current_user_can('ba_moderate_events')&&current_user_can('ba_view_private_reports')&&current_user_can('publish_ba_eventi');}
function badaround_event_social_save($id,$links,$published){
    if(!badaround_event_social_can_manage($id)||!is_array($links))return false;
    $clean=array();foreach(badaround_event_social_channels() as $key=>$label){
      $raw=$links[$key]??'';if($raw==='')continue;$url=badaround_event_social_url($key,$raw);if($url==='')return false;$clean[$key]=$url;
    }
    if($clean&&(!$published||get_post_status($id)!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published'||!badaround_event_social_requested($id)))return false;
    $record=array('links'=>$clean,'seal'=>badaround_event_social_seal($id,$clean));
    update_post_meta($id,'_ba_social_published_posts',$record);
    return get_post_meta($id,'_ba_social_published_posts',true)===$record;
}
function badaround_event_social_render($id){
    $links=badaround_event_social_links($id);if(!$links)return;
    $icons=badaround_event_social_icons();$channels=badaround_event_social_channels();
    echo '<section class="ba-card ba-event-section ba-event-social"><h2>Questa segnalazione sui social</h2><p>Apri i post pubblicati sui canali di BadAround.</p><ul class="ba-event-social-links">';
    foreach($links as $key=>$url)echo '<li><a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer" aria-label="'.esc_attr('Apri il post su '.$channels[$key].' (nuova scheda)').'">'.$icons[$key].'<span>'.esc_html($channels[$key]).'</span></a></li>';
    echo '</ul></section>';
}
function badaround_event_social_icons(){return array(
    'facebook'=>'<svg class="ba-native-social-icon ba-native-social-icon--facebook" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M13.8 21v-8.2h2.8l.4-3.2h-3.2V7.5c0-.9.3-1.5 1.6-1.5h1.7V3.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.6v3.2h2.8V21z"/></svg>',
    'instagram'=>'<svg class="ba-native-social-icon ba-native-social-icon--instagram" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/></svg>',
    'tiktok'=>'<svg class="ba-native-social-icon ba-native-social-icon--tiktok" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 3h3c.3 2.1 1.7 3.5 4 3.8v3a8.4 8.4 0 0 1-4-1.3V16a5.5 5.5 0 1 1-5-5.5v3.1a2.5 2.5 0 1 0 2 2.4V3z"/></svg>',
    'x'=>'<svg class="ba-native-social-icon ba-native-social-icon--x" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18.9 2H22l-6.8 7.8L23.2 22h-6.3L12 14.6 5.5 22H2.3l8.2-9.4L2.8 2h6.5l4.5 6.8L18.9 2zm-1.1 18h1.8L8.3 3.9H6.4L17.8 20z"/></svg>',
    'google'=>'<svg class="ba-native-social-icon ba-native-social-icon--google" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M21.6 12.2c0-.7-.1-1.3-.2-2H12v3.9h5.4a4.5 4.5 0 0 1-2 3v2.5h3.2c1.9-1.7 3-4.2 3-7.4z" fill="#4285f4"/><path d="M12 22c2.7 0 5-.9 6.6-2.4l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.8-1.8-5.6-4.1H3.1v2.6A10 10 0 0 0 12 22z" fill="#34a853"/><path d="M6.4 14a6 6 0 0 1 0-4V7.4H3.1a10 10 0 0 0 0 9.2L6.4 14z" fill="#fbbc05"/><path d="M12 5.9c1.5 0 2.8.5 3.8 1.5l2.8-2.8A9.6 9.6 0 0 0 12 2a10 10 0 0 0-8.9 5.4L6.4 10c.8-2.3 3-4.1 5.6-4.1z" fill="#ea4335"/></svg>',
);}
add_action('add_meta_boxes_ba_evento',static function(){
    if(!current_user_can('ba_moderate_events'))return;
    add_meta_box('ba-social-published-posts','Post social pubblicati',static function($post){
      if(!badaround_event_social_can_manage($post->ID))return;
      wp_nonce_field('ba-social-posts-'.$post->ID,'ba_social_posts_nonce');
      $requested=badaround_event_social_requested($post->ID);
      echo '<p>'.($requested?'Condivisione richiesta dall’utente. Inserisci solo link ai post effettivamente pubblicati.':'Condivisione non richiesta dall’utente: nessun nuovo link può essere pubblicato.').'</p>';
      $links=badaround_event_social_links($post->ID);
      foreach(badaround_event_social_channels() as $key=>$label)echo '<p><label>'.esc_html($label).'<input class="widefat" type="url" name="ba_social_posts['.esc_attr($key).']" value="'.esc_attr($links[$key]??'').'" placeholder="https://…"></label></p>';
      echo '<label><input type="checkbox" name="ba_social_posts_confirmed" value="1"> Confermo che i link corrispondono a post pubblicati da BadAround per questa segnalazione.</label><p>Il salvataggio richiede evento pubblicato, richiesta dell’utente e conferma. Per rimuovere i link, svuota i campi.</p>';
    },'ba_evento','side','default');
});
add_action('save_post_ba_evento',static function($id){
    if(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)return;
    $nonce=$_POST['ba_social_posts_nonce']??null;
    if(!is_string($nonce)||!wp_verify_nonce($nonce,'ba-social-posts-'.$id)||!badaround_event_social_can_manage($id))return;
    $links=isset($_POST['ba_social_posts'])?wp_unslash($_POST['ba_social_posts']):null;
    if(!badaround_event_social_save($id,$links,($_POST['ba_social_posts_confirmed']??'')==='1')){
      set_transient('ba_social_posts_error_'.get_current_user_id(),true,60);
    }
});
add_action('admin_notices',static function(){
    $key='ba_social_posts_error_'.get_current_user_id();if(!get_transient($key))return;delete_transient($key);
    echo '<div class="notice notice-error"><p>Link social non aggiornati. Verifica i permalink, la richiesta di condivisione e la conferma di avvenuta pubblicazione.</p></div>';
});
