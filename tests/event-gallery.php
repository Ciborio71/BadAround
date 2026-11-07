<?php
define('ABSPATH',__DIR__);$GLOBALS['status']='publish';$GLOBALS['cover']=11;
function get_post_status($id){return $GLOBALS['status'];}function get_post_meta($id,$key,$single=true){return 'published';}
function get_post_thumbnail_id($id){return $GLOBALS['cover'];}function wp_attachment_is_image($id){return true;}function get_post_mime_type($id){return $id===99?'image/svg+xml':'image/jpeg';}
function wp_get_attachment_image_src($id,$size){return ['https://example.test/public/'.$id.'.jpg',1024,768];}
function wp_get_attachment_image_url($id,$size){return 'https://example.test/public/'.$id.'.jpg';}
function wp_get_attachment_image_srcset($id,$size){return 'https://example.test/public/'.$id.'-480.jpg 480w, https://example.test/public/'.$id.'.jpg 1024w';}
function esc_url($s){return htmlspecialchars($s,ENT_QUOTES);}function esc_attr($s){return htmlspecialchars($s,ENT_QUOTES);}
function wp_get_attachment_image($id,$size,$icon,$attrs){$a='';foreach($attrs as $k=>$v)$a.=' '.esc_attr($k).'="'.esc_attr($v).'"';return '<img width="1024" height="768" src="https://example.test/public/'.$id.'.jpg"'.$a.'>';}
class GalleryDB{public $prefix='wp_',$last_error='',$rows=[],$query='';function prepare($s,$id){return str_replace('%d',(int)$id,$s);}function get_results($s){$this->query=$s;return $this->rows;}}
$wpdb=new GalleryDB();require __DIR__.'/../wordpress/themes/badaround-child/inc/event-gallery.php';
function row($id,$override=[]){return (object)array_merge(['public_attachment_id'=>$id,'review_status'=>'published','media_type'=>'image','sensitivity'=>'private','redaction_required'=>0,'redaction_status'=>'not_required','deleted_at'=>null],$override);}
function check($ok,$name){if(!$ok)throw new RuntimeException($name);echo 'PASS '.$name."\n";}
$wpdb->rows=[row(11),row(22),row(11)];check(badaround_event_gallery_ids(381)===[11,22],'approved public copies deduplicated, cover first');
$wpdb->rows=[row(11,['review_status'=>'pending']),row(22)];check(badaround_event_gallery_ids(381)===[22],'pending image cannot leak through old cover');
$wpdb->rows=[row(11,['redaction_required'=>1]),row(22,['deleted_at'=>'2026-11-06'])];check(badaround_event_gallery_ids(381)===[],'redaction and deletion revoke gallery access');
$wpdb->rows=[row(11,['review_status'=>'native_published'])];check(badaround_event_gallery_ids(381)===[],'native publication fence not bypassed');
$wpdb->last_error='unavailable';check(badaround_event_gallery_ids(381)===[],'database failure hides unverified cover');$wpdb->last_error='';
$GLOBALS['status']='pending';check(badaround_event_gallery_ids(381)===[],'unpublished event has no public gallery');$GLOBALS['status']='publish';
$GLOBALS['cover']=99;$wpdb->rows=[];check(badaround_event_gallery_ids(381)===[],'unsupported image excluded');
check(!str_contains($wpdb->query,'original_storage_key')&&!str_contains($wpdb->query,'original_filename'),'read query never retrieves private originals');
ob_start();badaround_render_event_gallery([],'Test');$html=ob_get_clean();check(str_contains($html,'ba-card ba-event-section')&&str_contains($html,'<h2>Foto della segnalazione</h2>'),'empty gallery has a titled card');check(str_contains($html,'Nessuna foto disponibile')&&!str_contains($html,'<img'),'compact empty state avoids placeholder download');
ob_start();badaround_render_event_gallery([11],'Test');$html=ob_get_clean();check(str_contains($html,'fetchpriority="high"')&&str_contains($html,'width="1024"')&&!str_contains($html,'data-ba-gallery-thumb'),'single image has dimensions and priority, no false thumbnails');
ob_start();badaround_render_event_gallery([11,22],'Test');$html=ob_get_clean();check(substr_count($html,'data-ba-gallery-thumb')===2&&str_contains($html,'data-srcset=')&&str_contains($html,'Foto 1 di 2'),'multiple public images include responsive navigation');
echo "EVENT_GALLERY_SUITE_PASS\n";

ob_start();badaround_render_event_gallery([],'Honda SH','Foto della segnalazione di furto Honda SH');$html=ob_get_clean();check(str_contains($html,'<h2>Foto della segnalazione di furto Honda SH</h2>'),'empty photo card accepts a descriptive heading');
