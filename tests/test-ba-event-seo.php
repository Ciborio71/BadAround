<?php
// Reuse the real publication/GEO harness; its existing assertions run first.
require __DIR__.'/geo-public-05-publication.php';
$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='vehicle_stolen';
g5seed(9000,'exact_point','exact_point');
$GLOBALS['meta'][9000]['_ba_vehicle_make']='fiat';
$GLOBALS['meta'][9000]['_ba_vehicle_model']='panda';
$p=BadAround_Event_SEO::project(9000);
check($p['slug']==='veicolo-rubato-pomezia-fiat-panda','SEO subtype, canonical municipality and public model');
check(str_contains($p['h1'],'Fiat Panda')&&!str_contains($p['h1'],'Torvaianica'),'SEO municipality boundary, no private locality or street');
check(!str_contains(implode(' ',$p),'Private Address'),'SEO does not read intake');
check($svc->publish(9000)===true,'SEO integrated into real controlled B3');
$slug=get_post_meta(9000,BadAround_Event_SEO::SLUG,true);
check($slug===$p['slug']&&BadAround_Event_SEO::active(9000),'SEO frozen URL and contract persisted');
check(preg_match('/^[a-f0-9]{32}$/D',get_post_meta(9000,BadAround_Event_SEO::URL_ID,true))===1,'opaque public URL identity');
$data=BadAround_Event_SEO::preserve_slug(['post_type'=>'ba_evento','post_name'=>'changed'],['ID'=>9000]);
check($data['post_name']===$slug,'later editor changes cannot rewrite frozen URL');
$GLOBALS['meta'][9000]['_ba_event_status']='resolved';
check(str_contains(BadAround_Event_SEO::project(9000)['title'],'Risolto')&&get_post_meta(9000,BadAround_Event_SEO::SLUG,true)===$slug,'status updates metadata without URL change');
g5seed(9001,'exact_point','exact_point');
$GLOBALS['meta'][9001]['_ba_vehicle_make']='fiat';$GLOBALS['meta'][9001]['_ba_vehicle_model']='panda';
check($svc->publish(9001)===true,'second identical event can publish');
$other=get_post_meta(9001,BadAround_Event_SEO::SLUG,true);
check($other!==$slug&&str_starts_with($other,$slug.'-')&&preg_match('/-[a-f0-9]{12}$/D',$other),'collision uses stable public suffix, not ordinal');
$before=serialize([$GLOBALS['meta'][9000],$GLOBALS['posts'][9000]]);
check(BadAround_Event_SEO::prepare(9000)===true&&serialize([$GLOBALS['meta'][9000],$GLOBALS['posts'][9000]])===$before,'published events never rewritten by SEO preparation');
g5seed(9002,'exact_point','exact_point');
$GLOBALS['meta'][9002]['_ba_vehicle_model']='persona@example.test';
check(!str_contains(implode(' ',BadAround_Event_SEO::project(9002)),'example'),'email rejected in structured attribute');
$GLOBALS['meta'][9002]['_ba_vehicle_model']='AB123CD';
check(!str_contains(implode(' ',BadAround_Event_SEO::project(9002)),'AB123CD'),'full plate rejected in structured attribute');
$GLOBALS['meta'][9002]['_ba_vehicle_model']='Private Address 99';
check(is_wp_error($svc->publish(9002))&&get_post_status(9002)==='pending','private source value in generated SEO blocks publication and rolls back');
// Missing dates and generic object subtypes must not invent facts.
$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='keys';
g5seed(9003,'exact_point','exact_point');$GLOBALS['meta'][9003]['_ba_occurred_date']='';
$p=BadAround_Event_SEO::project(9003);
check(!str_contains($p['h1'],'smarrit')&&!str_contains($p['intro'],'Data'),'no inferred lost/found state or date');
$map=BadAround_Event_SEO::templates();
check(count($map)===33,'all current canonical subtypes covered');
foreach($map as $key=>$entry){$GLOBALS['term_meta'][11]['_ba_canonical_event_key']=$key;$p=BadAround_Event_SEO::project(9003);check(!is_wp_error($p)&&$p['heading']!==''&&str_ends_with($p['title'],' | BadAround'),'valid template '.$key);}
echo "EVENT_SEO_SUITE_PASS\n";

function register_rest_route($namespace,$route,$args){$GLOBALS['seo_route']=$args;}
function get_post_type($id){return get_post($id)->post_type??null;}
function get_permalink($id){return 'https://example.test/eventi/'.(get_post($id)->post_name??'draft').'/';}
class WP_REST_Response {public $data,$headers=[];function __construct($data){$this->data=$data;}function header($k,$v){$this->headers[$k]=$v;}}
BadAround_Event_SEO::register_preview();
$route=$GLOBALS['seo_route'];
check($route['methods']==='GET'&&$route['permission_callback'](['id'=>9000]),'SEO preview is read-only and available to authorized moderator');
$GLOBALS['denied']=['ba_moderate_events'];
check(!$route['permission_callback'](['id'=>9000]),'SEO preview denied without moderation capability');
$GLOBALS['denied']=['edit_post'];
check(!$route['permission_callback'](['id'=>9000]),'SEO preview denied without event edit permission');
$GLOBALS['denied']=[];
check(!$route['permission_callback'](['id'=>99999]),'SEO preview rejects missing event');
$response=$route['callback'](['id'=>9000]);
check($response->headers['Cache-Control']==='private, no-store'&&!str_contains(json_encode($response->data),'Private Address'),'SEO preview avoids caches and private report values');
