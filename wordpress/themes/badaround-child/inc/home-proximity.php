<?php
/** Home-only public discovery. No location/profile persistence or intake writes. */
if (!defined('ABSPATH')) { exit; }

function badaround_home_area_name($name) {
    $name=preg_replace('/^(città metropolitana di |provincia di |libero consorzio comunale di )/iu','',trim((string)$name));
    if (strcasecmp($name,'Roma Capitale')===0) $name='Roma';
    return sanitize_title($name);
}
function badaround_home_area_path($names) {
    $parent=0; $path=[];
    foreach (['region','province','municipality'] as $level) {
        $value=$names[$level]??'';
        if (!is_string($value)||strlen($value)>150||$value==='') break;
        $terms=get_terms(['taxonomy'=>'ba_territorio','hide_empty'=>false,'parent'=>$parent]);
        if (is_wp_error($terms)) break;
        $matches=array_values(array_filter($terms,static function($t)use($value){return badaround_home_area_name($t->name)===badaround_home_area_name($value);}));
        // Never guess a territory on an ambiguous or incomplete canonical path.
        if(count($matches)!==1) break;
        $term=$matches[0];$path[$level]=['id'=>(int)$term->term_id,'name'=>$term->name];$parent=(int)$term->term_id;
    }
    return $path;
}
function badaround_home_distance($a,$b) {
    $lat=deg2rad($b['lat']-$a['lat']);$lng=deg2rad($b['lng']-$a['lng']);
    $h=sin($lat/2)**2+cos(deg2rad($a['lat']))*cos(deg2rad($b['lat']))*sin($lng/2)**2;
    return 6371000*2*asin(sqrt(min(1,max(0,$h))));
}
function badaround_home_item_scope($item,$point,$areas,$ancestors) {
    $geo=$item['public_geo']??null;
    if(is_array($geo)&&isset($geo['lat'],$geo['lng'])&&is_numeric($geo['lat'])&&is_numeric($geo['lng'])) {
        // A privacy area is nearby only when its entire published circle fits.
        $radius=max(0,(float)($geo['radius_m']??0));
        if(badaround_home_distance($point,$geo)+$radius<=5000) return 'nearby';
    }
    foreach(['municipality','province','region'] as $scope) {
        if(isset($areas[$scope])&&in_array($areas[$scope]['id'],$ancestors,true)) return $scope;
    }
    return null;
}
add_action('rest_api_init',static function(){
    register_rest_route('badaround/v1','/discovery/home',[
        'methods'=>'POST','permission_callback'=>'__return_true',
        'callback'=>static function($request){
            if(strlen($request->get_body())>2048) return new WP_Error('ba_home_request_invalid','Richiesta non valida.',['status'=>400]);
            $body=$request->get_json_params();$point=$body['position']??null;
            if(!is_array($point)||!isset($point['lat'],$point['lng'])||!is_numeric($point['lat'])||!is_numeric($point['lng'])
                ||!is_finite((float)$point['lat'])||!is_finite((float)$point['lng'])||abs((float)$point['lat'])>90||abs((float)$point['lng'])>180)
                return new WP_Error('ba_home_position_invalid','Posizione non valida.',['status'=>400]);
            $point=['lat'=>(float)$point['lat'],'lng'=>(float)$point['lng']];
            $areas=badaround_home_area_path(is_array($body['areas']??null)?$body['areas']:[]);
            $buckets=['nearby'=>[],'municipality'=>[],'province'=>[],'region'=>[]];
            $query=new BadAround_Discovery_Query();$page=1;$seen=[];
            // Paginate the canonical public query, rather than ranking only the first 100 national events.
            do {
                $result=$query->discover(['page'=>$page,'per_page'=>100,'home_stable_order'=>true]);
                foreach($result['items'] as $item){
                    $id=(int)$item['id'];if(isset($seen[$id]))continue;$seen[$id]=true;
                    $term=(int)($item['territory']['id']??0);
                    $ancestors=$term?array_merge([$term],array_map('intval',get_ancestors($term,'ba_territorio','taxonomy'))):[];
                    $scope=badaround_home_item_scope($item,$point,$areas,$ancestors);
                    if($scope&&count($buckets[$scope])<9){$item['home_scope']=$scope;$buckets[$scope][]=$item;}
                }
                if(count($buckets['nearby'])>=9)break;
                ++$page;
            }while($page<=(int)$result['pages']);
            $items=array_slice(array_merge(...array_values($buckets)),0,9);
            $labels=['nearby'=>'Entro 5 km · posizione pubblica','municipality'=>'Comune: '.($areas['municipality']['name']??''),'province'=>'Provincia: '.($areas['province']['name']??''),'region'=>'Regione: '.($areas['region']['name']??'')];
            ob_start();
            foreach($items as $item){
                $category='';$term=get_term((int)($item['event_type']['id']??0),'ba_tipo_evento');
                if($term instanceof WP_Term){while($term->parent){$p=get_term($term->parent,'ba_tipo_evento');if(!$p instanceof WP_Term)break;$term=$p;}$category=$term->slug;}
                get_template_part('template-parts/components/event-card',null,[
                    'event_id'=>$item['id']??0,'title'=>$item['title'],'url'=>$item['permalink'],'image'=>$item['thumbnail'],'excerpt'=>$item['excerpt'],
                    'status_label'=>$labels[$item['home_scope']],'category'=>$category,'class'=>'ba-event-card--home',
                    'meta'=>implode(' · ',array_filter([$item['occurred_date'],$item['occurred_time'],$item['public_place_name']?:($item['territory']['name']??'')]))
                ]);
            }
            $html=ob_get_clean();
            $response=new WP_REST_Response(['html'=>$html,'count'=>count($items),'scopes'=>array_values(array_unique(array_column($items,'home_scope'))),'areas'=>$areas],200);
            $response->header('Cache-Control','private, no-store, max-age=0');$response->header('X-Content-Type-Options','nosniff');return $response;
        }
    ]);
});
