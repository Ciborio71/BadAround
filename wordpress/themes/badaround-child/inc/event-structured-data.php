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
    $graph=['@context'=>'https://schema.org','@graph'=>[
        ['@type'=>'WebPage','@id'=>$canonical.'#webpage','url'=>$canonical,'name'=>$name,'description'=>$seo['description'],'inLanguage'=>'it-IT','breadcrumb'=>['@id'=>$canonical.'#breadcrumb'],'mainEntity'=>['@id'=>$canonical.'#report']],
        ['@type'=>'Report','@id'=>$canonical.'#report','url'=>$canonical,'headline'=>$name,'description'=>$seo['description'],'inLanguage'=>'it-IT','genre'=>'Segnalazione della community','mainEntityOfPage'=>['@id'=>$canonical.'#webpage'],'publisher'=>['@type'=>'Organization','name'=>'BadAround','url'=>home_url('/')]],
        ['@type'=>'BreadcrumbList','@id'=>$canonical.'#breadcrumb','itemListElement'=>[
            ['@type'=>'ListItem','position'=>1,'name'=>'Home','item'=>home_url('/')],
            ['@type'=>'ListItem','position'=>2,'name'=>'Segnalazioni','item'=>home_url('/segnalazioni/')],
            ['@type'=>'ListItem','position'=>3,'name'=>$name,'item'=>$canonical],
        ]],
    ]];
    $post=get_post($id);
    foreach(array('post_date_gmt'=>'datePublished','post_modified_gmt'=>'dateModified') as $field=>$property){
        $value=$post->$field??'';$date=BadAround_Event_Dates::parse_receipt($value);
        if($date)$graph['@graph'][1][$property]=$date->format('Y-m-d\\TH:i:s\\Z');
    }
    $ids=function_exists('badaround_event_gallery_ids')?badaround_event_gallery_ids($id):array();
    if($ids){
        $image=wp_get_attachment_image_src($ids[0],'large');
        if(is_array($image)&&filter_var($image[0],FILTER_VALIDATE_URL)&&str_starts_with($image[0],'https://')){
            $node=array('@type'=>'ImageObject','url'=>$image[0],'width'=>(int)$image[1],'height'=>(int)$image[2],'caption'=>$seo['image_alt']);
            $graph['@graph'][0]['primaryImageOfPage']=$node;
            $graph['@graph'][1]['image']=$node;
        }
    }
    return $graph;
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

/** Open Graph and cards derive from the same public projection as Title/H1/JSON-LD. */
function badaround_event_social_metadata($id){
    $graph=badaround_event_structured_data($id);if(!$graph)return array();
    $p=BadAround_Event_SEO::project($id);if(is_wp_error($p))return array();
    $url=$graph['@graph'][0]['url'];
    $meta=array('og:type'=>'article','og:locale'=>'it_IT','og:site_name'=>'BadAround',
        'og:url'=>$url,'og:title'=>$p['title'],'og:description'=>$p['description'],
        'twitter:card'=>'summary','twitter:title'=>$p['title'],'twitter:description'=>$p['description']);
    $image=$graph['@graph'][1]['image']??null;
    if($image){
        $meta+=array('og:image'=>$image['url'],'og:image:width'=>(string)$image['width'],
            'og:image:height'=>(string)$image['height'],'og:image:alt'=>$p['image_alt'],
            'twitter:image'=>$image['url'],'twitter:image:alt'=>$p['image_alt']);
        $meta['twitter:card']='summary_large_image';
    }
    foreach(array('datePublished'=>'article:published_time','dateModified'=>'article:modified_time') as $property=>$name){
        if(isset($graph['@graph'][1][$property]))$meta[$name]=$graph['@graph'][1][$property];
    }
    return $meta;
}
function badaround_render_event_social_metadata(){
    if(!is_singular('ba_evento'))return;
    foreach(badaround_event_social_metadata(get_queried_object_id()) as $key=>$value){
        echo '<meta '.(str_starts_with($key,'twitter:')?'name':'property').'="'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n";
    }
}
add_action('wp_head','badaround_render_event_social_metadata',5);
