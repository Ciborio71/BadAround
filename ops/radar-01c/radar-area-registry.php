<?php
/** RADAR-01C3 — offline, fail-closed, versioned public-area registry validator. */
declare(strict_types=1);
final class BadAround_Radar_Area_Registry {
    public static function validate(array $registry): bool {
        if(array_keys($registry)!==['version','source','published_at','areas'])return false;
        if(!is_string($registry['version'])||!preg_match('/^radar-areas-v[1-9][0-9]*$/',$registry['version']))return false;
        if($registry['source']!=='synthetic_fixture' || !is_string($registry['published_at'])
            || !preg_match('/^20[0-9]{2}-[01][0-9]-[0-3][0-9]$/',$registry['published_at'])
            || !checkdate((int)substr($registry['published_at'],5,2),(int)substr($registry['published_at'],8,2),(int)substr($registry['published_at'],0,4)))return false;
        $areas=$registry['areas'];
        if(!is_array($areas)||count($areas)<1||count($areas)>200)return false;
        $previous=[];
        foreach($areas as $id=>$bounds) {
            if(!is_string($id)||!preg_match('/^AREA-[A-Z0-9-]{3,24}$/',$id)
                ||!is_array($bounds)||array_keys($bounds)!==['south','north','west','east'])return false;
            foreach(['south','north','west','east'] as $k)if(!is_int($bounds[$k])&&!is_float($bounds[$k]))return false;
            foreach($bounds as $v)if(!is_finite((float)$v))return false;
            if($bounds['south']< -90||$bounds['north']>90||$bounds['west']< -180||$bounds['east']>180
                ||$bounds['south']>=$bounds['north']||$bounds['west']>=$bounds['east'])return false;
            foreach($previous as $existing) {
                if(max($bounds['south'],$existing['south'])<min($bounds['north'],$existing['north'])
                    &&max($bounds['west'],$existing['west'])<min($bounds['east'],$existing['east']))return false;
            }
            $previous[]=$bounds;
        }
        return true;
    }
    public static function adapterRegistry(array $registry): ?array {
        return self::validate($registry)?['version'=>$registry['version'],'areas'=>$registry['areas']]:null;
    }
}
