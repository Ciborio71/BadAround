<?php
/** Public SEO contract only; no private report, person, precise location or Event schema. */
if (!defined('ABSPATH')) { exit; }
function badaround_event_structured_data($id) {
    $id=(int)$id;
    if(get_post_type($id)!=='ba_evento'||get_post_status($id)!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published'
        ||!class_exists('BadAround_Event_SEO')||!BadAround_Event_SEO::active($id)||post_password_required($id))return [];
    // An external SEO owner must not receive a second overlapping graph.
    if(defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION'))return [];
    $seo=BadAround_Event_SEO::project($id);if(is_wp_error($seo))return [];
    $canonical=wp_get_canonical_url($id);$origin=wp_parse_url(home_url('/'));
    $url=is_string($canonical)?wp_parse_url($canonical):false;
    if(!$url||($url['scheme']??'')!=='https'||($url['host']??'')!==($origin['host']??'')||isset($url['query'])||isset($url['fragment'])||isset($url['user'])||isset($url['pass'])||isset($url['port']))return [];
    $name=$seo['h1'];
    if(function_exists('badaround_event_heading'))$name=badaround_event_heading($name);
    return ['@context'=>'https://schema.org','@graph'=>[
        ['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$name,'description'=>$seo['description'],'inLanguage'=>'it-IT','breadcrumb'=>['@id'=>$canonical.'#breadcrumb']],
        ['@type'=>'BreadcrumbList','@id'=>$canonical.'#breadcrumb','itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>home_url('/')],
            ['@type'=>'ListItem','position'=>2,'name'=>'Segnalazioni','item'=>home_url('/segnalazioni/')],
            ['@type'=>'ListItem','position'=>3,'name'=>$name,'item'=>$canonical],
        ]],
    ]];
}
function badaround_render_event_structured_data() {
    if(!is_singular('ba_evento'))return;
    $graph=badaround_event_structured_data(get_queried_object_id());if(!$graph)return;
    $json=wp_json_encode($graph,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if(is_string($json))echo '<script type="application/ld+json" id="ba-event-structured-data">'.$json.'</script>'."\n";
}
add_action('wp_head','badaround_render_event_structured_data',30);
add_action('rest_api_init',static function(){register_rest_route('badaround-ops/v1','/event-structured-data/(?P<id>\d+)',[
    'methods'=>'GET','permission_callback'=>static function($r){return current_user_can('manage_options')&&current_user_can('edit_post',(int)$r['id']);},
    'callback'=>static function($r){$response=new WP_REST_Response(['graph'=>badaround_event_structured_data((int)$r['id']),'scope'=>'Public projection used by wp_head; not a full front-end HTML or Google indexing certification.'],200);$response->header('Cache-Control','private, no-store');return $response;},
]);});
