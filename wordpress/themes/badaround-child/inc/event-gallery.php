<?php
/** Read-only gallery of already public attachments. Never materializes private media. */
if(!defined('ABSPATH')){exit;}
function badaround_event_gallery_ids($id){
    global $wpdb;
    if(get_post_status($id)!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published')return [];
    $rows=$wpdb->get_results($wpdb->prepare("SELECT public_attachment_id,media_type,review_status,sensitivity,redaction_required,redaction_status,deleted_at FROM {$wpdb->prefix}ba_report_media WHERE event_id=%d ORDER BY id ASC",$id));
    if(!is_array($rows)||!empty($wpdb->last_error))return [];
    $allowed=[];$blocked=[];
    foreach($rows as $row){
        $attachment=(int)$row->public_attachment_id;if(!$attachment)continue;
        if($row->deleted_at===null&&$row->media_type==='image'&&$row->sensitivity==='private'
            &&in_array($row->review_status,['approved_public','published'],true)
            &&(!$row->redaction_required||$row->redaction_status==='completed'))$allowed[]=$attachment;
        else $blocked[]=$attachment;
    }
    // A manually chosen legacy public cover remains supported. Revoked/blocked links override it.
    $cover=(int)get_post_thumbnail_id($id);$ids=$cover?array_merge([$cover],$allowed):$allowed;$result=[];
    foreach(array_unique($ids) as $attachment){
        if(in_array($attachment,$blocked,true)||!wp_attachment_is_image($attachment))continue;
        if(!in_array(get_post_mime_type($attachment),['image/jpeg','image/png','image/webp'],true))continue;
        if(wp_get_attachment_image_src($attachment,'large'))$result[]=$attachment;
        if(count($result)>=5)break;
    }
    return $result;
}
function badaround_render_event_gallery($ids,$title,$heading='Foto della segnalazione'){
    if(!$ids){
        echo '<section class="ba-card ba-event-section ba-event-photo-panel"><h2>'.esc_html($heading).'</h2><div class="ba-event-no-photo"><svg viewBox="0 0 24 24" width="40" height="40" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="15" rx="3"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 3 3 3-4 5 6"/></svg><div><strong>Nessuna foto disponibile</strong><span>Consulta i dettagli della segnalazione qui sotto.</span></div></div></section>';
        return;
    }
    $count=count($ids);$full=wp_get_attachment_image_url($ids[0],'full');
    echo '<section class="ba-card ba-event-section ba-event-gallery" data-ba-event-gallery aria-label="Foto della segnalazione"><h2>'.esc_html($heading).'</h2><div class="ba-event-gallery__stage" data-ba-gallery-stage>';
    echo '<a data-ba-gallery-full href="'.esc_url($full).'" target="_blank" rel="noopener" aria-label="Apri la foto a dimensione intera (nuova scheda)">';
    echo wp_get_attachment_image($ids[0],'large',false,['class'=>'ba-event-gallery__main','data-ba-gallery-main'=>'','alt'=>$title.' — foto 1','loading'=>'eager','fetchpriority'=>'high','decoding'=>'async','sizes'=>'(max-width: 720px) calc(100vw - 64px), (max-width: 1279px) min(760px, calc(100vw - 80px)), 580px']);
    echo '</a><div class="ba-event-gallery__arrows"><button type="button" data-ba-gallery-prev aria-label="Foto precedente" hidden>‹</button><button type="button" data-ba-gallery-next aria-label="Foto successiva" hidden>›</button></div></div>';
    echo '<p class="ba-event-gallery__counter" data-ba-gallery-count aria-live="polite">Foto 1 di '.(int)$count.' · Apri la foto per ingrandirla</p>';
    if($count>1){
        echo '<nav class="ba-event-gallery__thumbnails" aria-label="Seleziona una foto">';
        foreach($ids as $index=>$id){
            $source=wp_get_attachment_image_src($id,'large');$srcset=wp_get_attachment_image_srcset($id,'large');$url=wp_get_attachment_image_url($id,'full');
            echo '<a href="'.esc_url($url).'" data-ba-gallery-thumb data-src="'.esc_url($source[0]).'" data-srcset="'.esc_attr($srcset?:'').'" data-width="'.(int)$source[1].'" data-height="'.(int)$source[2].'" data-alt="'.esc_attr($title.' — foto '.($index+1)).'" aria-label="Mostra foto '.($index+1).' di '.$count.'"'.($index===0?' aria-current="true"':'').'>';
            echo wp_get_attachment_image($id,'thumbnail',false,['alt'=>'','loading'=>'lazy','decoding'=>'async','sizes'=>'72px']);echo '</a>';
        }
        echo '</nav>';
    }
    echo '</section>';
}
