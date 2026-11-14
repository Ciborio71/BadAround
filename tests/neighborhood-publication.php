<?php
require __DIR__.'/test-ba-event-public-details.php';
foreach(array('exact_point','approximate_area') as $mode){
    $id=$mode==='exact_point'?9350:9351;g5seed($id,$mode,$mode);
    $raw=json_decode($GLOBALS['private_fixture']['content_original'],true);
    $raw['canonical']['consents']=array('truthfulness'=>true,'publication_rules'=>true,'terms'=>true,'privacy'=>true,'public_details_version'=>'public-details-v1');
    $raw['canonical']['location']['neighborhood_label']='Aurelio';
    $raw['canonical']['location']['neighborhood_source']='google_neighborhood';
    $raw['canonical']['location']['neighborhood_confirmed']=true;
    $GLOBALS['private_fixture']['content_original']=json_encode($raw);
    check($svc->publish($id)===true,'zone fixture published '.$mode);
    check(empty(BadAround_Event_Public_Details::view($id)['neighborhood']),'intake never auto-publishes zone '.$mode);
    $p=BadAround_Event_Public_Details::candidate($id,true);
    check($p['neighborhood_proposal']==='Aurelio','private confirmed proposal available to moderator');
    $r=BadAround_Event_Public_Details::approve($id,$p['source_hash'],false,false,true,false);
    check(!is_wp_error($r)&&$r['neighborhood']==='','text review alone does not disclose zone');
    $r=BadAround_Event_Public_Details::approve($id,$p['source_hash'],false,false,false,true);
    check(!is_wp_error($r)&&$r['neighborhood']==='','zone review without text review does not disclose');
    $r=BadAround_Event_Public_Details::approve($id,$p['source_hash'],false,false,true,true);
    check(!is_wp_error($r)&&$r['neighborhood']==='Aurelio','two explicit reviews publish signed zone '.$mode.(is_wp_error($r)?$r->get_error_code():''));
    $view=BadAround_Event_Public_Details::view($id);
    check($view['neighborhood']==='Aurelio'&&!isset($view['neighborhood_source'])&&!isset($view['neighborhood_proposal']),'public DTO excludes proposal/source');
    $seo=BadAround_Event_SEO::project($id);
    check(str_contains($seo['h1'],'zona Aurelio')&&str_contains($seo['description'],'zona Aurelio'),'approved zone in SEO '.$mode);
    check(str_contains(BadAround_Event_SEO::section_headings($id)['location'],'Aurelio'),'geographic heading includes approved zone');
    $stored=get_post_meta($id,BadAround_Event_Public_Details::META,true);
    $GLOBALS['meta'][$id]['_ba_public_geo']['radius_m']=333;
    check(empty(BadAround_Event_Public_Details::view($id)['neighborhood']),'geometry/proof mismatch immediately hides neighborhood');
    $GLOBALS['meta'][$id][BadAround_Event_Public_Details::META]=$stored;
}
g5seed(9352);check($svc->publish(9352)===true,'private text test');
foreach(array('secret@example.invalid','+39 333 1234567','Via Privata 99','AB123CD') as $unsafe){
    $raw=json_decode($GLOBALS['private_fixture']['content_original'],true);
    $raw['canonical']['location']+=array('neighborhood_source'=>'user_proposed','neighborhood_confirmed'=>true);
    $raw['canonical']['location']['neighborhood_label']=$unsafe;
    $GLOBALS['private_fixture']['content_original']=json_encode($raw);
    check(BadAround_Event_Public_Details::candidate(9352,true)['neighborhood_proposal']==='','unsafe proposal not publishable');
}
echo "NEIGHBORHOOD_PUBLICATION_SUITE_PASS\n";
