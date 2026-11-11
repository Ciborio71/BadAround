<?php
/** Public-only editorial projection over the existing real publication/GEO harness. */
require __DIR__.'/test-ba-event-public-details.php';
function dynamic_fixture($id,$mode='exact_point',$key='vehicle_stolen'){
    g5seed($id,$mode,$mode);
    $GLOBALS['term_meta'][11]['_ba_canonical_event_key']=$key;
    $raw=json_decode($GLOBALS['private_fixture']['content_original'],true);
    $raw['canonical']['consents']=['public_details_version'=>'public-details-v1','truthfulness'=>true,'publication_rules'=>true,'terms'=>true,'privacy'=>true];
    $raw['canonical']['event']=['category'=>'vehicle','subtype'=>$key];
    $raw['canonical']['vehicle']=['plate_knowledge'=>'full','plate_raw'=>'AB123CD'];
    $raw['canonical']['witness']=['status'=>'seeking'];
    $GLOBALS['private_fixture']['full_plate']='AB123CD';
    $GLOBALS['private_fixture']['exact_address']='Viale Marconi, 123, Pomezia';
    $GLOBALS['private_fixture']['content_original']=json_encode($raw);
    $GLOBALS['meta'][$id]['_ba_vehicle_plate_masked']='AB***CD';
    $GLOBALS['meta'][$id]['_ba_vehicle_make']='Fiat';$GLOBALS['meta'][$id]['_ba_vehicle_model']='Panda';$GLOBALS['meta'][$id]['_ba_vehicle_color']='Bianco';
}
dynamic_fixture(9600);
$draft=BadAround_Event_SEO::project(9600);
check(!str_contains(implode(' ',$draft),'Viale Marconi'),'unpublished/private address never enriches SEO');
check($svc->publish(9600)===true,'declared exact event publishes with dynamic SEO');
$before=serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']]);
$p=BadAround_Event_SEO::project(9600);$s=BadAround_Event_SEO::section_headings(9600);
check(str_contains($p['h1'],'Fiat Panda, colore bianco | veicolo segnalato rubato a Pomezia, in Viale Marconi'),'H1 preserves entity, color, pipe and approved street');
check(str_contains($p['title'],'Furto Fiat Panda')&&$p['title']!==$p['h1'].' | BadAround','Title has a distinct concise editorial role');
check(str_contains($p['description'],'Segnalato il furto')&&str_contains($p['description'],'Si cercano testimoni'),'description uses reported facts and explicit witness choice');
check(!str_contains(implode(' ',array_merge($p,$s)),'123')&&!str_contains(implode(' ',array_merge($p,$s)),'AB123CD'),'street-only SEO excludes public civic number and plate');
check($s['photos']==='Foto della segnalazione: Fiat Panda'&&$s['prose']==='Come è avvenuto il furto segnalato','photos and narrative headings answer different intents');
check(count(array_unique(array_values($s)))===count($s),'section labels are distinct within an event');
check(count(array_filter($p,'is_array'))===0,'publication validator still receives a flat string DTO');
check(serialize([$GLOBALS['meta'],$GLOBALS['posts'],$GLOBALS['private_fixture']])===$before,'projection does not mutate reports, snapshots, status or frozen URLs');
check($p['slug']==='veicolo-rubato-pomezia-fiat-panda','existing URL formula is unchanged');
$slug=get_post_meta(9600,BadAround_Event_SEO::SLUG,true);
foreach(['resolved'=>'Risolto','closed'=>'Chiuso','archived'=>'Archiviato','expired'=>'Scaduto'] as $state=>$label){
    $GLOBALS['meta'][9600]['_ba_event_status']=$state;$x=BadAround_Event_SEO::project(9600);
    check(str_contains($x['title'],$label)&&!str_contains($x['description'],'Si cercano testimoni')&&get_post_meta(9600,BadAround_Event_SEO::SLUG,true)===$slug,'terminal status suppresses stale witness solicitation and preserves URL '.$state);
}
$GLOBALS['meta'][9600]['_ba_event_status']='open';
// Only the sealed public snapshot is read; a private report query would fail this test.
$real_db=$wpdb;
class DynamicNoPrivateDB extends Geo05DB{function get_row($sql){throw new RuntimeException('SEO must not read private rows');}}
$wpdb=new DynamicNoPrivateDB();check(!is_wp_error(BadAround_Event_SEO::project(9600)),'SEO renderer makes no private-row queries');$wpdb=$real_db;
dynamic_fixture(9601,'approximate_area');check($svc->publish(9601)===true,'approximate fixture publishes');
$x=BadAround_Event_SEO::project(9601);check(!str_contains(implode(' ',$x),'Viale Marconi'),'approximate mode never exposes the precise street from the private address');
// Ambiguous civic formats fall back to town/locality rather than guessing.
foreach(['Via Roma 77'=>'Via Roma','Via 20 Settembre, 77, Pomezia'=>'','Via Roma 77 Pomezia'=>''] as $address=>$street){
    dynamic_fixture(9602);$GLOBALS['private_fixture']['exact_address']=$address;
    check($svc->publish(9602)===true,'address format fixture publishes');$x=BadAround_Event_SEO::project(9602);
    check($street?str_contains($x['h1'],$street):!str_contains($x['h1'],'Via '),'conservative approved street extraction '.$address);
}
// All 33 subtype templates work with missing optional facts and give varied purpose-based copy.
$seen=[];
foreach(BadAround_Event_SEO::templates() as $key=>$template){
    g5seed(9650);$GLOBALS['term_meta'][11]['_ba_canonical_event_key']=$key;
    $x=BadAround_Event_SEO::project(9650);$h=BadAround_Event_SEO::section_headings(9650);
    check(!is_wp_error($x)&&str_ends_with($x['title'],' | BadAround')&&$x['h1'].' | BadAround'!==$x['title'],'distinct title and H1 for '.$key);
    check(!str_contains(implode(' ',$x),'Fiat')&&!str_contains(implode(' ',$x),'Marconi'),'unrelated event attributes do not carry into '.$key);
    foreach($h as $label)check(is_string($label)&&$label!==''&&!str_contains($label,'  '),'nonempty clean section heading '.$key);
    $seen[$x['title']]=true;
}
check(count($seen)===33,'each subtype has its own title rather than a global boilerplate');
// Species and lost/found state must come from approved public enum facts.
g5seed(9660);$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='animal_missing';
$raw=json_decode($GLOBALS['private_fixture']['content_original'],true);$raw['canonical']['animal']=['type'=>'dog'];$GLOBALS['private_fixture']['content_original']=json_encode($raw);
check($svc->publish(9660)===true,'animal fixture publishes');$c=BadAround_Event_Public_Details::candidate(9660);check(!is_wp_error(BadAround_Event_Public_Details::approve(9660,$c['source_hash'])),'animal enum facts approved');
$x=BadAround_Event_SEO::project(9660);check(str_contains($x['h1'],'Cane smarrito')&&str_contains($x['title'],'Cane smarrito'),'approved species personalizes animal copy');
g5seed(9661);$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='keys';
$x=BadAround_Event_SEO::project(9661);check(!str_contains(implode(' ',$x),'smarrimento')&&!str_contains(implode(' ',$x),'ritrovamento'),'object missing-state is never inferred');
$raw=json_decode($GLOBALS['private_fixture']['content_original'],true);$raw['canonical']['object']=['status'=>'found'];$GLOBALS['private_fixture']['content_original']=json_encode($raw);
check($svc->publish(9661)===true,'object fixture publishes');$c=BadAround_Event_Public_Details::candidate(9661);check(!is_wp_error(BadAround_Event_Public_Details::approve(9661,$c['source_hash'])),'object state approved');
$x=BadAround_Event_SEO::project(9661);check(str_contains($x['title'],'Ritrovamento')&&str_contains($x['h1'],'Segnalazione di ritrovamento'),'approved found-state personalizes object copy');
// Long Unicode attributes must not produce broken UTF-8 or unlimited metadata.
g5seed(9662);$GLOBALS['term_meta'][11]['_ba_canonical_event_key']='vehicle_stolen';$GLOBALS['meta'][9662]['_ba_vehicle_make']=str_repeat('Èlite ',22);$GLOBALS['meta'][9662]['_ba_vehicle_model']='CX-5';
$x=BadAround_Event_SEO::project(9662);check(preg_match('//u',$x['title'])===1&&preg_match_all('/./us',$x['title'])<=90&&preg_match_all('/./us',$x['description'])<=190,'bounded Unicode-safe metadata with long attributes');
check(str_contains($x['h1'],'CX-5'),'H1 retains full model identity independently of compact Title');
echo "EVENT_DYNAMIC_SEO_SUITE_PASS\n";

dynamic_fixture(9670,'exact_point','property_attempted_breakin');
$raw=json_decode($GLOBALS['private_fixture']['content_original'],true);
$raw['canonical']['event']['category']='property';$raw['canonical']['location']['place_type']='private_home';
$GLOBALS['private_fixture']['content_original']=json_encode($raw);
$before=BadAround_Event_SEO::project(9670);
check(!str_contains($before['h1'],'abitazione privata'),'private place never enriches draft SEO');
check($svc->publish(9670)===true,'property fixture publishes through owning service');
$candidate=BadAround_Event_Public_Details::candidate(9670);
check(!is_wp_error(BadAround_Event_Public_Details::approve(9670,$candidate['source_hash'])),'public place approved through moderation');
$x=BadAround_Event_SEO::project(9670);$h=BadAround_Event_SEO::section_headings(9670);
check(str_contains($x['h1'],'Tentato furto o scasso in abitazione privata segnalato a Pomezia'),'approved place enriches property H1');
check(str_contains($x['title'],'abitazione privata')&&str_contains($x['description'],'abitazione privata'),'approved place enriches metadata');
check(str_contains($h['facts'],'abitazione privata')&&$h['facts']!==$h['photos'],'place-aware sections have distinct purposes');
check($x['slug']===$before['slug'],'place enrichment preserves URL formula');
$sealed=$GLOBALS['meta'][9670][BadAround_Event_Public_Details::META];
$GLOBALS['meta'][9670][BadAround_Event_Public_Details::META]['data']['facts']['location.place_type']['value']='office';
check(!str_contains(BadAround_Event_SEO::project(9670)['h1'],'ufficio'),'tampered place excluded from SEO');
$GLOBALS['meta'][9670][BadAround_Event_Public_Details::META]=$sealed;
echo "CONTEXTUAL_PLACE_SEO_PASS\n";
