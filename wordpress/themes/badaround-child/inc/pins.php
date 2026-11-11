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
function badaround_pin_svg($category='neutral'){$c=badaround_pin_catalog();return $c[badaround_pin_key($category)]['svg']??'';}
function badaround_pin_map_catalog(){
    $out=array();foreach(badaround_pin_catalog() as $key=>$entry)$out[$key]=array_intersect_key($entry,array_flip(array('body','glyph')));return $out;
}
