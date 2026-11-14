<?php
/** Shared passive pin artwork. No data, geometry or provider writes. */
if(!defined('ABSPATH'))exit;
function badaround_pin_key($category){
    if(!is_string($category))return 'neutral';
    $aliases=array('vehicle'=>'veicoli','property'=>'case-e-attivita','hazard'=>'pericoli','public'=>'spazi-pubblici','public_space'=>'spazi-pubblici','animal'=>'animali','object'=>'oggetti-e-documenti','item_document'=>'oggetti-e-documenti','check'=>'resolved');
    $key=$aliases[$category]??$category;
    return in_array($key,array('veicoli','case-e-attivita','pericoli','spazi-pubblici','animali','oggetti-e-documenti','neutral','resolved'),true)?$key:'neutral';
}
function badaround_pin_catalog(){
    static $catalog=null;
    if($catalog===null){$raw=file_get_contents(dirname(__DIR__).'/assets/pin-designs.json');$catalog=is_string($raw)?json_decode($raw,true):array();if(!is_array($catalog))$catalog=array();}
    return $catalog;
}
function badaround_pin_svg($category='neutral',$mode=''){
    $c=badaround_pin_catalog();$entry=$c[badaround_pin_key($category)]??array();
    if($mode!=='approximate_area')return $entry['svg']??'';
    return '<svg xmlns="http://www.w3.org/2000/svg" class="ba-pin-svg ba-pin-svg--area" width="48" height="48" viewBox="0 0 48 48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="21" fill="'.$entry['color'].'" stroke="#fff" stroke-width="1.6"/><circle cx="24" cy="24" r="15.3" fill="#fff"/><g transform="translate(12 12)">'.$entry['glyph'].'</g></svg>';
}
/** Only verified published geometry controls precision artwork. Never inspect private input. */
function badaround_event_public_presentation($event_id){
    $geo=class_exists('BadAround_Public_Geo_Projection')?BadAround_Public_Geo_Projection::public_view((int)$event_id):null;
    return is_array($geo)?array('mode'=>$geo['mode']??'','radius_meters'=>$geo['radius_meters']??0):array();
}
function badaround_pin_map_catalog(){
    $out=array();foreach(badaround_pin_catalog() as $key=>$entry)$out[$key]=array_intersect_key($entry,array_flip(array('body','glyph')));return $out;
}
