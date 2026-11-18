<?php
/** Public-only, cached aggregation for the Radar demo. No report repository access. */
if (!defined('ABSPATH')) exit;
class BadAround_Radar_Observatory {
    const VERSION = 'radar-a1';
    const MIN_CELL = 3;
    const GEO_SOURCE = 'https://raw.githubusercontent.com/guglielmo/geojson-italy/ba3347df3a53352891109f8198c597a0273684c0/geojson/';
    public static function boot() {
        add_action('rest_api_init', function () {
            register_rest_route('badaround/v1','/radar',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'rest')));
            register_rest_route('badaround/v1','/radar-boundaries',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'boundaries')));
            register_rest_route('badaround/v1','/radar-territories',array('methods'=>'GET','permission_callback'=>'__return_true','callback'=>array(__CLASS__,'search')));
        });
        foreach (array('save_post_ba_evento','before_delete_post','trashed_post','untrashed_post','set_object_terms','added_post_meta','updated_post_meta','deleted_post_meta') as $hook) {
            add_action($hook, array(__CLASS__,'invalidate'), 20, 1);
        }
        foreach(array('edited_term','delete_term') as $hook)add_action($hook,function($id,$tt,$taxonomy){if(in_array($taxonomy,array('ba_territorio','ba_tipo_evento'),true))update_option('ba_radar_revision',sprintf('%.6f',microtime(true)),false);},20,3);
    }
    public static function invalidate($id) {
        if (get_post_type((int)$id)==='ba_evento') update_option('ba_radar_revision',sprintf('%.6f',microtime(true)),false);
    }
    public static function date($raw) {
        $raw=trim((string)$raw);
        foreach (array('Y-m-d','d/m/Y') as $format) {
            $date=DateTimeImmutable::createFromFormat('!'.$format,$raw,wp_timezone());
            if ($date && $date->format($format)===$raw) return $date->format('Y-m-d');
        }
        return null;
    }
    public static function norm($text) {
        $text=remove_accents(wp_strip_all_tags((string)$text));
        $text=function_exists('mb_strtolower')?mb_strtolower($text,'UTF-8'):strtolower($text);
        return preg_replace('/[^a-z0-9]/','',$text);
    }
    private static function geography($kind) {
        static $data=null;if($data===null)$data=require __DIR__.'/radar-geography.php';return $data[$kind];
    }
    private static function path($id) {
        $ids=array_reverse(get_ancestors($id,'ba_territorio','taxonomy'));$ids[]=$id;$out=array();
        foreach ($ids as $tid) { $term=get_term($tid,'ba_territorio');if ($term instanceof WP_Term) $out[]=array('id'=>(int)$term->term_id,'name'=>$term->name,'slug'=>$term->slug); }
        return $out;
    }
    private static function rows() {
        $revision=get_option('ba_radar_revision','initial');$key='ba_'.self::VERSION.'_rows_'.md5($revision);
        $cached=get_transient($key);if (is_array($cached)) return $cached;
        if (!class_exists('BadAround_Discovery_Query')) return new WP_Error('radar_unavailable','Aggregazione non disponibile.',array('status'=>503));
        $query=new BadAround_Discovery_Query();$rows=array();$seen=array();$paths=array();$types=array();$complete=true;
        for ($page=1;$page<=100;$page++) {
            $batch=$query->discover(array('page'=>$page,'per_page'=>100,'home_stable_order'=>true));
            foreach ($batch['items'] as $event) {
                $id=(int)$event['id'];if (isset($seen[$id])) continue;$seen[$id]=true;
                if (preg_match('/^\s*\[?QA\b/i',$event['title'])||preg_match('/nessun evento reale|collaudo tecnico sintetico/i',$event['title'])) continue;
                $tid=(int)($event['territory']['id']??0);if (!isset($paths[$tid])) $paths[$tid]=self::path($tid);
                $typeid=(int)($event['event_type']['id']??0);
                if (!isset($types[$typeid])) {
                    $type=get_term($typeid,'ba_tipo_evento');$parent=$type instanceof WP_Term?$type:null;
                    for ($i=0;$parent instanceof WP_Term&&$parent->parent&&$i<8;$i++) $parent=get_term($parent->parent,'ba_tipo_evento');
                    $types[$typeid]=$parent instanceof WP_Term?$parent->slug:'altro';
                }
                // Only the canonical public projection may supply geometry. Never inspect private metadata.
                $geo=$event['public_geo'];$safe=null;
                if (is_array($geo)&&($geo['source']??'')==='event_public'&&is_numeric($geo['lat']??null)&&is_numeric($geo['lng']??null)) {
                    $radius=(int)($geo['radius_meters']??$geo['radius_m']??0);
                    $mode=$geo['mode']??'';
                    if ($mode==='exact_point'||(($mode==='approximate_area'||isset($geo['precision']))&&$radius>=1)) $safe=array('lat'=>(float)$geo['lat'],'lng'=>(float)$geo['lng'],'radius'=>$mode==='exact_point'?0:$radius);
                }
                $rows[]=array('path'=>$paths[$tid],'category'=>$types[$typeid],'type'=>$event['event_type']['name']??'Altra tipologia','date'=>self::date($event['occurred_date']??''),'published'=>get_post_time('Y-m-d',false,$id),'geo'=>$safe);
            }
            if ($page>=(int)$batch['pages']) break;
            if ($page===100) $complete=false;
        }
        $result=array('rows'=>$rows,'complete'=>$complete,'built_at'=>gmdate('c'));
        set_transient($key,$result,3600);return $result;
    }
    public static function rest($request) {
        $source=self::rows();if (is_wp_error($source)) return $source;
        $territory=absint($request->get_param('territory'));$category=sanitize_title((string)$request->get_param('category'));
        if(!in_array($category,array('','veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti'),true))return new WP_Error('radar_category','Categoria non valida.',array('status'=>400));
        $period=(string)$request->get_param('period');if (!in_array($period,array('7','30','90','365','all'),true)) $period='all';
        $basis=$request->get_param('basis')==='published'?'published':'date';
        $today=new DateTimeImmutable('today',wp_timezone());$from=$period==='all'?null:$today->modify('-'.((int)$period-1).' days')->format('Y-m-d');
        $selected=array();$path=array();$catalog=array();$center=null;$bounds=null;$named=array();
        foreach(array('region','province','city') as $name){$value=sanitize_text_field((string)$request->get_param($name));if($value!=='')$named[]=array('id'=>0,'name'=>substr($value,0,150),'slug'=>sanitize_title($value));else break;}
        if (is_numeric($request->get_param('lat'))&&is_numeric($request->get_param('lng'))) {
            $lat=(float)$request->get_param('lat');$lng=(float)$request->get_param('lng');
            if ($lat<35.3||$lat>47.5||$lng<6||$lng>19) return new WP_Error('radar_area','Seleziona una zona in Italia.',array('status'=>400));
            $center=array($lat,$lng,max(500,min(10000,(int)$request->get_param('radius'))));$territory=0;
        }
        if (is_numeric($request->get_param('south'))) {
            $bounds=array_map('floatval',array($request->get_param('south'),$request->get_param('west'),$request->get_param('north'),$request->get_param('east')));
            if ($bounds[0]<$bounds[2]&&$bounds[1]<$bounds[3]&&$bounds[0]>=30&&$bounds[2]<=50&&$bounds[1]>=0&&$bounds[3]<=25) $territory=0;else return new WP_Error('radar_area','Area non valida.',array('status'=>400));
        }
        foreach ($source['rows'] as $row) {
            foreach ($row['path'] as $i=>$place) { $place['path']=array_slice($row['path'],0,$i+1);$catalog[$place['id']]=$place; }
        }
        if ($territory) {
            $path=$catalog[$territory]['path']??self::path($territory);
            if (!$path) return new WP_Error('radar_territory','Territorio non disponibile.',array('status'=>400));
        }
        elseif(!$center&&!$bounds&&$named) $path=$named;
        foreach ($source['rows'] as $row) {
            if ($territory&&!in_array($territory,array_column($row['path'],'id'),true)) continue;
            if (!$territory&&$named&&!$center&&!$bounds) { $matches=true;foreach($named as $i=>$place)if(self::norm($row['path'][$i]['name']??'')!==self::norm($place['name']))$matches=false;if(!$matches)continue; }
            if ($category&&$category!==$row['category']) continue;
            if ($center||$bounds) {
                $g=$row['geo'];if (!$g) continue;
                if ($center) {$a=sin(deg2rad($g['lat']-$center[0])/2)**2+cos(deg2rad($center[0]))*cos(deg2rad($g['lat']))*sin(deg2rad($g['lng']-$center[1])/2)**2;$distance=6371000*2*asin(min(1,sqrt($a)));if($distance+$g['radius']>$center[2])continue;}
                if ($bounds) {$dy=$g['radius']/111320;$dx=$dy/max(.1,cos(deg2rad($g['lat'])));if($g['lat']-$dy<$bounds[0]||$g['lat']+$dy>$bounds[2]||$g['lng']-$dx<$bounds[1]||$g['lng']+$dx>$bounds[3])continue;}
            }
            $date=$row[$basis];if ($from&&(!$date||$date<$from||$date>$today->format('Y-m-d'))) continue;
            $selected[]=$row;
        }
        $result=self::aggregate($selected,$path,$catalog,$period,$basis,$today,$source);
        if ($center||$bounds) {$result['scope']=array('path'=>array(),'name'=>$center?'Area attorno a te':'Area selezionata sulla mappa','mode'=>$center?'radius':'viewport');$result['geography']['features']=array();$result['geography']['type']='hexbin';$result['geography']['cells']=self::local_cells($selected);}
        return rest_ensure_response($result);
    }
    private static function local_cells($rows) {
        $groups=array();foreach ($rows as $row) {if(!$row['geo'])continue;$cell=self::cell($row['geo']);if(!$cell)continue;$key=$cell['key'];if(!isset($groups[$key]))$groups[$key]=$cell+array('count'=>0);$groups[$key]['count']++;}
        $out=array();foreach($groups as $cell)if($cell['count']>=self::MIN_CELL){unset($cell['key']);$out[]=$cell;}return $out;
    }
    public static function search($request) {
        $q=sanitize_text_field((string)$request->get_param('q'));if(strlen($q)<2)return rest_ensure_response(array());
        $exact=get_terms(array('taxonomy'=>'ba_territorio','hide_empty'=>false,'name'=>$q,'number'=>10));
        $matches=get_terms(array('taxonomy'=>'ba_territorio','hide_empty'=>false,'name__like'=>$q,'number'=>30));$terms=array();
        foreach(array(is_wp_error($exact)?array():$exact,is_wp_error($matches)?array():$matches) as $batch)foreach($batch as $term)$terms[$term->term_id]=$term;
        uasort($terms,function($a,$b)use($q){$needle=self::norm($q);$an=self::norm($a->name);$bn=self::norm($b->name);$as=$an===$needle?0:(strpos($an,$needle)===0?1:2);$bs=$bn===$needle?0:(strpos($bn,$needle)===0?1:2);return $as<=>$bs?:strcmp($an,$bn);});$out=array();
        foreach(array_slice($terms,0,15) as $term)$out[]=array('id'=>(int)$term->term_id,'name'=>$term->name,'path'=>self::path($term->term_id));return rest_ensure_response($out);
    }
    public static function aggregate($rows,$path,$catalog,$period,$basis,$today,$source=array()) {
        $types=array();$areas=array();$categories=array();$dated=0;$plottable=0;$bins=array();$cellGroups=array();$depth=count($path);
        $dates=array_filter(array_column($rows,$basis),function($d)use($today){return $d&&$d<=$today->format('Y-m-d');});
        $start=$period==='all'?($dates?min($dates):$today->format('Y-m-d')):$today->modify('-'.((int)$period-1).' days')->format('Y-m-d');
        // Bound chart size explicitly; the full-period total remains available separately.
        if ($start<$today->modify('-23 months')->format('Y-m-01')) $start=$today->modify('-23 months')->format('Y-m-01');
        $unit=in_array($period,array('7','30'),true)?'day':($period==='90'?'week':'month');
        $cursor=new DateTimeImmutable($start,wp_timezone());if ($unit==='month') $cursor=$cursor->modify('first day of this month');
        while ($cursor<=$today) { $bins[$cursor->format('Y-m-d')]=0;$cursor=$cursor->modify($unit==='day'?'+1 day':($unit==='week'?'+7 days':'+1 month')); }
        foreach ($rows as $row) {
            $types[$row['type']]=($types[$row['type']]??0)+1;$categories[$row['category']]=($categories[$row['category']]??0)+1;
            $date=$row[$basis];if ($date&&$date<=$today->format('Y-m-d')) $dated++;
            if ($date&&$date>=$start&&$date<=$today->format('Y-m-d')) { $bucket=null;foreach ($bins as $key=>$v) {if ($key<=$date) $bucket=$key;else break;}if ($bucket!==null) $bins[$bucket]++; }
            $area=$row['path'][$depth]??null;
            if ($area) {if (!isset($areas[$area['id']])) $areas[$area['id']]=$area+array('count'=>0);$areas[$area['id']]['count']++;}
            if ($row['geo']) {
                $plottable++;$cell=self::cell($row['geo']);
                if ($cell) { $key=$cell['key'];if (!isset($cellGroups[$key])) $cellGroups[$key]=$cell+array('count'=>0);$cellGroups[$key]['count']++; }
            }
        }
        uasort($areas,function($a,$b){return $b['count']<=>$a['count'];});arsort($types);arsort($categories);
        $cells=array();foreach ($cellGroups as $cell) if ($cell['count']>=self::MIN_CELL) {unset($cell['key']);$cells[]=$cell;}
        $features=array();
        if ($depth<2) {
            $kind=$depth===0?'regions':'provinces';$geo=self::geography($kind);
            foreach ($geo['features'] as $feature) {
                if ($depth===1&&self::norm($feature['properties']['region'])!==self::norm($path[0]['name'])) continue;
                $feature['properties']['territory_id']=null;$feature['properties']['count']=null;
                foreach ($areas as $area) if (self::norm($area['name'])===self::norm($feature['properties']['name'])) {
                    $feature['properties']['territory_id']=$area['id'];$feature['properties']['count']=$area['count']>=self::MIN_CELL?$area['count']:null;break;
                }
                $features[]=$feature;
            }
        }
        return array('version'=>self::VERSION,'scope'=>array('path'=>$path,'name'=>$path?end($path)['name']:'Italia','mode'=>'territory'),'filters'=>array('period'=>$period,'category'=>array_keys($categories),'basis'=>$basis),
            'metrics'=>array('total'=>count($rows),'types'=>count($types),'territories'=>count($areas),'dated'=>$dated,'without_date'=>count($rows)-$dated,'geographic'=>$plottable),
            'areas'=>array_values($areas),'types'=>array_map(function($name,$count){return array('name'=>$name,'count'=>$count);},array_keys($types),array_values($types)),
            'categories'=>$categories,'territories'=>array_values($catalog),'series'=>array('from'=>$start,'to'=>$today->format('Y-m-d'),'unit'=>$unit,'bins'=>array_map(function($date,$count){return array('date'=>$date,'count'=>$count);},array_keys($bins),array_values($bins))),
            'geography'=>array('type'=>$depth<3?'administrative':'hexbin','features'=>$features,'cells'=>$depth>=3?$cells:array(),'minimum'=>self::MIN_CELL,'suppressed_cells'=>count($cellGroups)-count($cells)),
            'complete'=>$source['complete']??true,'built_at'=>$source['built_at']??gmdate('c'));
    }
    /** Fixed regular hexagons in Web Mercator; enlarge until public uncertainty fits entirely. */
    public static function cell($geo) {
        $lat=$geo['lat'];$lng=$geo['lng'];if ($lat<35.3||$lat>47.5||$lng<6||$lng>19) return null;
        $x=6378137*deg2rad($lng);$y=6378137*log(tan(M_PI/4+deg2rad($lat)/2));$uncertainty=$geo['radius']/cos(deg2rad($lat));
        for ($size=1500;$size<=384000;$size*=2) {
            $q=(sqrt(3)/3*$x-1/3*$y)/$size;$r=(2/3*$y)/$size;$cx=$q;$cz=$r;$cy=-$cx-$cz;$rx=round($cx);$ry=round($cy);$rz=round($cz);
            $dx=abs($rx-$cx);$dy=abs($ry-$cy);$dz=abs($rz-$cz);if($dx>$dy&&$dx>$dz)$rx=-$ry-$rz;elseif($dy>$dz)$ry=-$rx-$rz;else $rz=-$rx-$ry;
            $px=$size*sqrt(3)*($rx+$rz/2);$py=$size*1.5*$rz;$vertices=array();$fits=true;
            for ($i=0;$i<6;$i++) {$angle=deg2rad(60*$i-30);$vertices[]=array($px+$size*cos($angle),$py+$size*sin($angle));}
            for ($i=0;$i<6;$i++) {$a=$vertices[$i];$b=$vertices[($i+1)%6];$distance=(($b[0]-$a[0])*($y-$a[1])-($b[1]-$a[1])*($x-$a[0]))/hypot($b[0]-$a[0],$b[1]-$a[1]);if($distance<$uncertainty)$fits=false;}
            if (!$fits) continue;
            $polygon=array();foreach ($vertices as $v) $polygon[]=array(round(rad2deg($v[0]/6378137),5),round(rad2deg(2*atan(exp($v[1]/6378137))-M_PI/2),5));$polygon[]=$polygon[0];
            return array('key'=>$size.':'.$rx.':'.$rz,'polygon'=>$polygon,'size_m'=>$size);
        }
        return null;
    }
    public static function boundaries($request) {
        if($request->get_param('level')==='provinces')return rest_ensure_response(self::geography('provinces'));
        $province=absint($request->get_param('province'));if ($province<1||$province>119) return new WP_Error('radar_boundary','Provincia non valida.',array('status'=>400));
        $key='ba_radar_municipalities_2026_'.$province;$cached=get_transient($key);if (is_array($cached)) return rest_ensure_response($cached);
        $response=wp_remote_get(self::GEO_SOURCE.'limits_P_'.$province.'_municipalities.geojson',array('timeout'=>12,'redirection'=>0,'limit_response_size'=>8000000));
        if (is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200) return new WP_Error('radar_boundary_unavailable','Confini comunali momentaneamente non disponibili.',array('status'=>503));
        $data=json_decode(wp_remote_retrieve_body($response),true);if (($data['type']??'')!=='FeatureCollection'||!isset($data['features'])) return new WP_Error('radar_boundary_invalid','Confini non disponibili.',array('status'=>503));
        foreach ($data['features'] as &$f) { $p=$f['properties'];$f['properties']=array('name'=>$p['name'],'province'=>$p['prov_name'],'region'=>$p['reg_name']); }unset($f);unset($data['crs']);
        set_transient($key,$data,604800);return rest_ensure_response($data);
    }
}
BadAround_Radar_Observatory::boot();
