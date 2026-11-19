<?php
/** Public rendering fixtures, including fail-closed geometry and unchanged photographs. */
define('ABSPATH',__DIR__);
require __DIR__.'/../wordpress/themes/badaround-child/inc/pins.php';
class BadAround_Public_Geo_Projection{
 public static function public_view($id){return $id===1?array('mode'=>'approximate_area','radius_meters'=>100):($id===2?array('mode'=>'exact_point','radius_meters'=>0):null);}
}
function __($s,$domain=''){return $s;}
function wp_parse_args($a,$b){return array_merge($b,$a);}
function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function esc_attr($s){return esc_html($s);}
function esc_url($s){return esc_html($s);}
function sanitize_html_class($s){return preg_replace('/[^a-z0-9_-]/i','',$s);}
function get_stylesheet_directory_uri(){return 'https://badaround.it/wp-content/themes/badaround-child';}
function badaround_child_event_category_slug($id){return 'animali';}
// Exercise the actual URL helper without loading unrelated WordPress hooks.
$source=file_get_contents(__DIR__.'/../wordpress/themes/badaround-child/functions.php');
preg_match('/function badaround_child_placeholder_url\([^}]+\}/',$source,$match);
eval($match[0]);
function area_card($id,$image=''){
 $args=array('event_id'=>$id,'title'=>'Cane smarrito','url'=>'/eventi/test/','image'=>$image);
 ob_start();include __DIR__.'/../wordpress/themes/badaround-child/template-parts/components/event-card.php';return ob_get_clean();
}
function area_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$catalog=badaround_pin_catalog();
foreach($catalog as $key=>$entry){
 $svg=badaround_pin_svg($key,'approximate_area');area_check(str_contains($svg,$entry['color'])&&str_contains($svg,$entry['glyph']),'category ring and original symbol '.$key);
 area_check(!str_contains($svg,'M24 58C')&&str_contains($svg,'viewBox="0 0 48 48"'),'circle, not pinpoint '.$key);
 if($key==='resolved')continue;
 $url=badaround_child_placeholder_url($key==='neutral'?'':$key,'approximate_area');
 $file=__DIR__.'/../wordpress/themes/badaround-child/assets/'.basename(parse_url($url,PHP_URL_PATH));
 area_check(is_file($file),'real approximate placeholder asset '.$key);
 $asset=file_get_contents($file);area_check(!str_contains($asset,'M24 58C')&&str_contains($asset,$entry['glyph']),'matching area placeholder '.$key);
 area_check(strlen($asset)<3600&&!preg_match('/<script|<image|<foreignObject/i',$asset),'small passive SVG '.$key);
}
$area=area_card(1);area_check(str_contains($area,'event-placeholder-animali-area.svg')&&!str_contains($area,'ba-event-card__media-category')&&str_contains($area,'raggio 100 m'),'area placeholder carries its own symbol without a badge');
$photo='https://badaround.it/uploads/dog.jpg';$with_photo=area_card(1,$photo);area_check(str_contains($with_photo,$photo)&&!str_contains($with_photo,'event-placeholder-animali-area.svg')&&str_contains($with_photo,'ba-pin-svg--area'),'uploaded photograph retained');
$exact=area_card(2);area_check(!str_contains($exact,'ba-pin-svg--area')&&!str_contains($exact,'raggio 100 m')&&str_contains($exact,'event-placeholder-animali.svg')&&!str_contains($exact,'ba-event-card__media-category'),'exact remains a pinpoint');
$explicit_placeholder=area_card(1,badaround_child_placeholder_url('animali'));area_check(!str_contains($explicit_placeholder,'ba-event-card__media-category')&&str_contains($explicit_placeholder,'animali-area.svg'),'explicit placeholder URL is not a user photograph');
$exact_photo=area_card(2,$photo);area_check(str_contains($exact_photo,'ba-event-card__media-category')&&str_contains($exact_photo,'M24 58C'),'exact photo retains one category pinpoint');
$unknown=area_card(3);area_check(!str_contains($unknown,'ba-pin-svg--area')&&!str_contains($unknown,'Area approssimata'),'unverified geometry never classified as area');
if(in_array('--html',$argv,true)){echo $area.$with_photo.$exact.$unknown;exit;}
echo "EVENT_AREA_ARTWORK_PHP_PASS\n";
