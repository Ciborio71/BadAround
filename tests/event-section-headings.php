<?php
require __DIR__.'/test-ba-event-seo.php';
$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='vehicle_stolen';
$GLOBALS['meta'][9003]['_ba_vehicle_make']='Honda';$GLOBALS['meta'][9003]['_ba_vehicle_model']='SH';
$before=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']]);$p=BadAround_Event_SEO::project(9003);$s=BadAround_Event_SEO::section_headings(9003);
check($s['photos']==='Foto della segnalazione di furto Honda SH','photo heading names the public model and event');
check($s['summary']==='Informazioni evento: Honda SH segnalato rubato a Pomezia','summary includes event, model and canonical town');
check(!str_contains(implode(' ',$s),'Private Address')&&!str_contains(implode(' ',$s),'Torvaianica'),'unpublished locality and private address are excluded');
check($before===serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']]),'heading projection does not mutate reports, posts or metadata');
check($p===BadAround_Event_SEO::project(9003)&&count(array_filter($p,'is_array'))===0,'existing flat SEO/URL contract is unchanged');
foreach(BadAround_Event_SEO::templates() as $key=>$template){$GLOBALS['term_meta'][11]['_ba_canonical_event_key']=$key;$s=BadAround_Event_SEO::section_headings(9003);foreach($s as $value){check(is_string($value)&&$value!==''&&strlen($value)<400,'descriptive section label '.$key);}}
$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='keys';$s=BadAround_Event_SEO::section_headings(9003);check(!str_contains(implode(' ',$s),'smarrit'),'generic object does not invent lost state');
check(BadAround_Event_SEO::section_headings(99999)['photos']==='Foto della segnalazione','incomplete legacy event has a safe generic fallback');
echo "EVENT_SECTION_HEADINGS_SUITE_PASS\n";
