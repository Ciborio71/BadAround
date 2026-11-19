<?php
require __DIR__.'/test-ba-event-seo.php';
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/event-heading.php';
function wp_parse_url($s){return parse_url($s);}
function home_url($s='/'){return 'https://example.test'.$s;}
function wp_get_canonical_url($id){return $GLOBALS['canonical_override']??get_permalink($id);}
function post_password_required($id){return $GLOBALS['password_required']??false;}
function is_singular($type){return true;}
function get_queried_object_id(){return 9000;}
function badaround_event_gallery_ids($id){return $GLOBALS['schema_gallery_ids']??[];}
function wp_get_attachment_image_src($id,$size){return $GLOBALS['schema_image']??false;}
require dirname(__DIR__).'/wordpress/themes/badaround-child/inc/event-structured-data.php';
$snapshot=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']]);
$g=badaround_event_structured_data(9000);$nodes=$g['@graph']??[];
check(count($nodes)===3&&$nodes[0]['@type']==='WebPage'&&$nodes[1]['@type']==='Report'&&$nodes[2]['@type']==='BreadcrumbList','page and breadcrumb graph generated for approved public contract');
check($nodes[0]['url']===get_permalink(9000)&&$nodes[2]['itemListElement'][2]['item']===get_permalink(9000),'stable existing canonical matches page and last breadcrumb');
check(array_column($nodes[2]['itemListElement'],'position')===[1,2,3],'visible breadcrumb hierarchy uses consecutive positions');
check(!str_contains(json_encode($g),'Private Address')&&!isset($nodes[0]['author'])&&!isset($nodes[0]['geo'])&&!isset($nodes[0]['datePublished']),'graph excludes private source, identity, geography and ambiguous dates');
check($snapshot===serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']]),'structured projection is read-only');
$old=$GLOBALS['posts'][9000]->post_status;$GLOBALS['posts'][9000]->post_status='draft';check(badaround_event_structured_data(9000)===[],'draft has no graph');$GLOBALS['posts'][9000]->post_status=$old;
$GLOBALS['password_required']=true;check(badaround_event_structured_data(9000)===[],'password-protected event excluded');$GLOBALS['password_required']=false;
foreach(['https://other.test/event/','http://example.test/event/','https://example.test/event/?token=private','https://example.test/event/#private','https://user:secret@example.test/event/'] as $bad){$GLOBALS['canonical_override']=$bad;check(badaround_event_structured_data(9000)===[],'unsafe canonical rejected');}unset($GLOBALS['canonical_override']);
check(badaround_event_structured_data(9003)===[],'legacy unapproved contract is not rewritten');
$social=badaround_event_social_metadata(9000);$seo=BadAround_Event_SEO::project(9000);
check($social['og:title']===$seo['title']&&$social['twitter:description']===$seo['description']&&$social['og:url']===get_permalink(9000),'social metadata shares canonical and editorial projection');
check(!isset($social['og:image'])&&$social['twitter:card']==='summary','no approved photo means no misleading social image');
$GLOBALS['schema_gallery_ids']=[100];$GLOBALS['schema_image']=['https://example.test/public-photo.webp',1200,800];
$g=badaround_event_structured_data(9000);$social=badaround_event_social_metadata(9000);
check($g['@graph'][0]['primaryImageOfPage']['url']===$social['og:image']&&$g['@graph'][1]['image']['width']===1200,'approved photo consistently used by page report and social preview');
check($social['og:image:alt']===$seo['image_alt']&&$social['twitter:image:alt']===$seo['image_alt'],'contextual attachment alt shared without invented visual details');
check($social['twitter:card']==='summary_large_image','approved photo enables large social preview');
$GLOBALS['schema_image']=['http://example.test/insecure.webp',1200,800];
check(!isset(badaround_event_social_metadata(9000)['og:image']),'insecure image URL omitted');
$GLOBALS['schema_gallery_ids']=[];
$GLOBALS['posts'][9000]->post_date_gmt='2026-10-20 10:00:00';$GLOBALS['posts'][9000]->post_modified_gmt='2026-10-21 12:00:00';
$g=badaround_event_structured_data(9000);$social=badaround_event_social_metadata(9000);
check($g['@graph'][1]['datePublished']==='2026-10-20T10:00:00Z'&&$social['article:modified_time']==='2026-10-21T12:00:00Z','publication and modification dates are ISO UTC, separate from occurrence');
define('WPSEO_VERSION','fixture');check(badaround_event_structured_data(9000)===[],'external SEO owner prevents duplicate graph');
echo "EVENT_STRUCTURED_DATA_SUITE_PASS\n";
