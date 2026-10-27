<?php
declare(strict_types=1);
require __DIR__.'/radar-public-area-adapter.php';
function check(bool $ok,string $reason): void {if(!$ok)throw new RuntimeException($reason);}
$registry=['version'=>'radar-areas-v1','areas'=>[
 'AREA-ALPHA'=>['south'=>40.0,'north'=>41.0,'west'=>12.0,'east'=>13.0],
 'AREA-BETA'=>['south'=>40.0,'north'=>41.0,'west'=>13.0,'east'=>14.0]
]];
$base=['publication_status'=>'published','projection_authority'=>'approved_public_projection',
 'projection_version'=>'radar-areas-v1'];
$exact=$base+['public_geo_mode'=>'exact_point','public_point'=>['lat'=>40.5,'lng'=>12.5]];
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$exact)==='AREA-ALPHA','exact point');
$area=$base+['public_geo_mode'=>'approximate_area',
 'public_uncertainty_bounds'=>['south'=>40.1,'north'=>40.9,'west'=>12.1,'east'=>12.9]];
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$area)==='AREA-ALPHA','contained public area');
$border=$area;$border['public_uncertainty_bounds']['east']=13.2;
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$border)===null,'crossing suppression');
$border=$exact;$border['public_point']['lng']=13.0;
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$border)===null,'boundary suppression');
$bad=$area;unset($bad['public_uncertainty_bounds']);$bad['public_point']=['lat'=>40.5,'lng'=>12.5];
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$bad)===null,'no approximate centroid shortcut');
$bad=$area;$bad['publication_status']='draft';
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$bad)===null,'published only');
$bad=$area;$bad['projection_authority']='client';
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$bad)===null,'untrusted projection');
$bad=$area;$bad['projection_version']='radar-areas-v2';
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$bad)===null,'version');
$overlap=$registry;$overlap['areas']['AREA-BETA']['west']=12.8;
check(BadAround_Radar_Public_Area_Adapter::assign($overlap,$exact)===null,'overlapping registry');
$broken=$registry;$broken['areas']['AREA-ALPHA']['north']=INF;
check(BadAround_Radar_Public_Area_Adapter::assign($broken,$exact)===null,'invalid numeric boundary');
$bad=$base+['public_geo_mode'=>'unknown','public_point'=>['lat'=>40.5,'lng'=>12.5]];
check(BadAround_Radar_Public_Area_Adapter::assign($registry,$bad)===null,'unknown geo');
echo "RADAR-01C2: synthetic area assignment tests PASS\n";
