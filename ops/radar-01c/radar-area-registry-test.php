<?php
declare(strict_types=1);
require __DIR__.'/radar-area-registry.php';
require __DIR__.'/radar-public-area-adapter.php';
function must(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$fixture=['version'=>'radar-areas-v1','source'=>'synthetic_fixture','published_at'=>'2026-10-27',
 'areas'=>['AREA-DEMO-A'=>['south'=>40.0,'north'=>41.0,'west'=>12.0,'east'=>13.0],
 'AREA-DEMO-B'=>['south'=>40.0,'north'=>41.0,'west'=>13.0,'east'=>14.0]]];
must(BadAround_Radar_Area_Registry::validate($fixture),'valid');
$projection=['publication_status'=>'published','projection_authority'=>'approved_public_projection',
 'projection_version'=>'radar-areas-v1','public_geo_mode'=>'exact_point','public_point'=>['lat'=>40.5,'lng'=>12.5]];
must(BadAround_Radar_Public_Area_Adapter::assign(BadAround_Radar_Area_Registry::adapterRegistry($fixture),$projection)==='AREA-DEMO-A','adapter integration');
$mutations=[];
$bad=$fixture;$bad['source']='unverified_web';$mutations[]=$bad;
$bad=$fixture;$bad['published_at']='2026-02-30';$mutations[]=$bad;
$bad=$fixture;$bad['areas']['AREA-DEMO-B']['west']=12.5;$mutations[]=$bad;
$bad=$fixture;$bad['areas']['AREA-DEMO-A']['north']=INF;$mutations[]=$bad;
$bad=$fixture;$bad['areas']['AREA-DEMO-A']['south']=41.0;$mutations[]=$bad;
$bad=$fixture;$bad['areas']['AREA-DEMO-A']['west']='12.0';$mutations[]=$bad;
$bad=$fixture;$bad['areas']=[];$mutations[]=$bad;
$bad=$fixture;$bad['version']='radar-areas-v0';$mutations[]=$bad;
$bad=$fixture;$bad['extra']='anything';$mutations[]=$bad;
foreach($mutations as $i=>$candidate)must(!BadAround_Radar_Area_Registry::validate($candidate),'invalid fixture '.$i);
echo "RADAR-01C3 synthetic registry validation PASS\n";
