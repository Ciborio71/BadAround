<?php
/** Run via wp eval-file ONLY against the disposable CI WordPress database. */
if ( ! defined('ABSPATH') || ! getenv('BA_EXT_ISOLATED_TEST') ) { throw new RuntimeException('Isolated CI only'); }
global $wpdb;
$GLOBALS['checks']=0;
function ext_check($ok,$message){global $checks;$checks++;if(!$ok){throw new RuntimeException('FAIL '.$message);}echo 'PASS '.$message."\n";}
function ext_code($value){return is_wp_error($value)?$value->get_error_code():'ok';}
function ext_row($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id));}
wp_set_current_user(1);
$init=(new BadAround_Event_Taxonomy_Initializer())->run();
ext_check(!is_wp_error($init),'Canonical taxonomy initialized');
$region=wp_insert_term('Test Regione','ba_territorio',array('slug'=>'ext-test-regione'))['term_id'];
$province=wp_insert_term('Test Provincia','ba_territorio',array('slug'=>'ext-test-provincia','parent'=>$region))['term_id'];
$municipality=wp_insert_term('Test Comune','ba_territorio',array('slug'=>'ext-test-comune','parent'=>$province))['term_id'];
$input=array('source_platform'=>'facebook','source_url'=>'https://www.facebook.com/groups/123456/permalink/987654/','source_reference'=>'group:123456:post:987654','evidence_sha256'=>str_repeat('a',64),
 'original_text'=>'Segnalazione di test: veicolo segnalato rubato. Targa AA123BB.','title'=>'Veicolo segnalato rubato nel Comune di test',
 'description'=>'Segnalazione sintetica di veicolo rubato nella notte tra il 24 e il 25 ottobre 2026.','category'=>'vehicle','subtype'=>'vehicle_stolen','territory_id'=>(int)$municipality,
 'area_label'=>'Test Comune','date_start'=>'2026-10-24','date_end'=>'2026-10-25','period'=>'night','date_note'=>'Intervallo dichiarato, nessun orario noto.',
 'exact_address'=>'Via Riservata 99, Comune di test','plate'=>'AA123BB','vehicle_type'=>'car','vehicle_make'=>'Fiat','vehicle_model'=>'500','vehicle_color'=>'red');
$svc=new BadAround_External_Report_Intake();
wp_set_current_user(0);
ext_check(ext_code($svc->ingest($input))==='ba_external_forbidden','Unauthenticated import rejected');
wp_set_current_user(1);
foreach(array(
 'unknown_field'=>array('consents'=>array('privacy'=>true)),
 'source_mismatch'=>array('source_reference'=>'group:123456:post:1'),
 'category_subtype'=>array('subtype'=>'hazard_fire_or_smoke'),
 'event_date'=>array('date_start'=>'2026-02-30'),
 'date_order'=>array('date_end'=>'2026-10-23'),
 'private_public_text'=>array('description'=>'Targa AA123BB'),
 'vehicle_color'=>array('vehicle_color'=>'guess'),
 'invalid_title'=>array('title'=>array('invalid')),
 'source_url'=>array('source_url'=>'https://user:secret@facebook.com/groups/123456/permalink/987654/'),
) as $code=>$patch){
 ext_check(ext_code($svc->ingest(array_merge($input,$patch)))==='ba_external_'.$code,'Invalid input denied '.$code);
}
$prepared=BadAround_External_Report_Contract::prepare($input);
ext_check(!is_wp_error($prepared),'No native reporter or consents required');
$count_before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports");
$r=$svc->ingest($input);
ext_check(!is_wp_error($r),'Import succeeds '.ext_code($r));
$id=$r['event_id'];$rid=$r['report_id'];$row=ext_row($rid);
ext_check(get_post_status($id)==='pending' && get_post_meta($id,'_ba_moderation_status',true)==='new','Event pending and not approved');
ext_check((int)$row->event_id===$id && (int)get_post_meta($id,'_ba_primary_report_id',true)===$rid,'Bidirectional source relation');
ext_check($row->consent_privacy===null && $row->consent_publication===null && $row->consented_at===null && $row->author_email===null,'No fabricated consents or identity');
ext_check($row->full_plate==='AA123BB' && $row->exact_address===$input['exact_address'],'Private data segregated');
$payload=json_decode($row->content_original,true);
ext_check($payload['provenance']['source_url']===$input['source_url'] && $payload['provenance']['source_reference']===$input['source_reference'],'Source provenance retained');
ext_check($payload['canonical']['time']['date_range']['start']==='2026-10-24' && $payload['canonical']['time']['date_range']['end']==='2026-10-25','Cross-midnight dates retained without invented hours');
ext_check(get_post_meta($id,'_ba_vehicle_plate_masked',true)==='AA***BB','Only masked plate materialized');
ext_check(!str_contains(get_post($id)->post_content,'AA123BB') && !str_contains(get_post($id)->post_content,'Via Riservata'),'Public draft excludes private values');
$repeat=$svc->ingest($input);
ext_check(!is_wp_error($repeat) && $repeat['duplicate'] && $repeat['event_id']===$id && $repeat['report_id']===$rid,'Identical retry does not duplicate');
$alias=$input;$alias['source_url']='https://m.facebook.com/groups/123456/permalink/987654/?tracking=ignored';
ext_check(($svc->ingest($alias)['event_id']??0)===$id,'Canonical URL alias idempotent');
$changed=$input;$changed['description'].=' A changed source.';
ext_check(ext_code($svc->ingest($changed))==='ba_external_source_changed_review_required','Changed source cannot overwrite snapshot');
ext_check(ext_row($rid)->content_original===$row->content_original,'Snapshot remains immutable');
$admin=new BadAround_External_Report_Admin();$publication=new BadAround_Publication_Service();
ext_check(ext_code($publication->publish($id))==='ba_external_editorial_review_required','Publish fails closed before editorial review');
$review=array('basis'=>'other_documented_basis','reason'=>'Synthetic CI only: no real personal data; scoped test.','public_hash'=>BadAround_External_Report_Contract::public_hash($id));
ext_check($admin->save_review($id,$review)===true,'Real administrative review action recorded');
ext_check(is_wp_error($publication->publish($id)) && get_post_status($id)==='pending','Review does not approve or publish');
ext_check((new BadAround_Moderation_Service())->transition($id,'approved')===true,'Normal moderation approval');
$result=$publication->publish($id);
ext_check($result===true,'Existing Publication Service publishes synthetic external event '.ext_code($result));
ext_check(get_post_status($id)==='publish' && get_post_meta($id,'_ba_moderation_status',true)==='published','Normal public lifecycle completed');
ext_check(get_post_meta($id,'_ba_public_lat',true)==='' && get_post_meta($id,'_ba_public_lng',true)==='' && get_post_meta($id,'_ba_public_geo',true)['mode']==='territory_only','No invented coordinates or marker');
ext_check(ext_row($rid)->content_original===$row->content_original,'Publication preserves private snapshot');
$retry=$svc->ingest($input);
ext_check(($retry['event_id']??0)===$id && get_post_status($id)==='publish','Retry preserves already published lifecycle');

// Adopt one specifically identified editorial draft, never duplicate it.
$adopt=$input;$adopt['source_reference']='group:123456:post:987655';$adopt['source_url']='https://www.facebook.com/groups/123456/permalink/987655/';
$draft=wp_insert_post(array('post_type'=>'ba_evento','post_status'=>'draft','post_title'=>'Existing editorial test draft','post_content'=>'Before'));
update_post_meta($draft,'_ba_admin_test_provenance',array('source_url'=>$adopt['source_url']));
$adopt['existing_event_id']=$draft;$adopt['expected_event_hash']=str_repeat('0',64);
ext_check(ext_code($svc->ingest($adopt))==='ba_external_adoption_conflict','Stale adoption rejected');
ext_check(get_post_status($draft)==='draft' && !get_post_meta($draft,'_ba_primary_report_id',true),'Failed adoption unchanged');
$adopt['expected_event_hash']=BadAround_External_Report_Contract::event_hash($draft);
$a=$svc->ingest($adopt);
ext_check(!is_wp_error($a) && $a['event_id']===$draft && get_post_status($draft)==='pending','Same draft adopted into pending');
ext_check(!get_post_meta($draft,'_ba_admin_test_provenance',true),'Provenance removed from public projection metadata');
$oldrow=ext_row($a['report_id']);
ext_check(json_decode($oldrow->content_original,true)['provenance']['source_url']===$adopt['source_url'],'Provenance moved into private report');

// A previous approval of an orphan draft cannot be carried over to a new report.
$orphan_input=$input;$orphan_input['source_reference']='group:123456:post:987657';$orphan_input['source_url']='https://www.facebook.com/groups/123456/permalink/987657/';
$orphan=wp_insert_post(array('post_type'=>'ba_evento','post_status'=>'draft','post_title'=>'Approved orphan fixture','post_content'=>'Before source intake'));
update_post_meta($orphan,'_ba_admin_test_provenance',array('source_url'=>$orphan_input['source_url']));
ext_check((new BadAround_Moderation_Service())->transition($orphan,'approved')===true,'Synthetic orphan approved through existing moderator');
$orphan_input['existing_event_id']=$orphan;$orphan_input['expected_event_hash']=BadAround_External_Report_Contract::event_hash($orphan);
ext_check(ext_code($svc->ingest($orphan_input))==='ba_external_adoption_conflict','Approved orphan not silently adopted');
ext_check(get_post_meta($orphan,'_ba_moderation_status',true)==='approved','Rejected implicit reset preserves approval');
$orphan_input['adoption_reset_approval']=true;
$recovered=$svc->ingest($orphan_input);
ext_check(!is_wp_error($recovered) && $recovered['event_id']===$orphan && get_post_meta($orphan,'_ba_moderation_status',true)==='new' && get_post_status($orphan)==='pending','Explicit orphan recovery requires fresh moderation');
$audit_before=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_audit_log WHERE object_id=%d AND action='external_orphan_approval_reset'",$orphan));
ext_check($audit_before===1,'Approval reset is audited');
$orphan_retry=$svc->ingest($orphan_input);
ext_check(($orphan_retry['event_id']??0)===$orphan && (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ba_audit_log WHERE object_id=%d AND action='external_orphan_approval_reset'",$orphan))===1,'Recovery retry does not reset or audit twice');
$no_published=$orphan_input;$no_published['source_reference']='group:123456:post:987658';$no_published['source_url']='https://www.facebook.com/groups/123456/permalink/987658/';$no_published['existing_event_id']=$id;$no_published['expected_event_hash']=BadAround_External_Report_Contract::event_hash($id);
ext_check(ext_code($svc->ingest($no_published))==='ba_external_adoption_conflict','Explicit recovery cannot overwrite published or linked event');

// Test rollback after event update (audit failure) with real InnoDB transactions.
$rollback=$input;$rollback['source_reference']='group:123456:post:987656';$rollback['source_url']='https://www.facebook.com/groups/123456/permalink/987656/';
$rollback_id=wp_insert_post(array('post_type'=>'ba_evento','post_status'=>'draft','post_title'=>'Rollback fixture','post_content'=>'Original'));
update_post_meta($rollback_id,'_ba_admin_test_provenance',array('source_url'=>$rollback['source_url']));
$rollback['existing_event_id']=$rollback_id;$rollback['expected_event_hash']=BadAround_External_Report_Contract::event_hash($rollback_id);
$before_posts=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");$before_reports=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports");
$audit_table=$wpdb->prefix.'ba_audit_log';
$fail=function($sql)use($audit_table){return str_starts_with(ltrim($sql),'INSERT INTO') && str_contains($sql,$audit_table) ? str_replace($audit_table,$audit_table.'_MISSING_TEST',$sql) : $sql;};
$wpdb->suppress_errors(true);add_filter('query',$fail);
$failure=$svc->ingest($rollback);
remove_filter('query',$fail);$wpdb->suppress_errors(false);
ext_check(is_wp_error($failure),'Injected audit failure rejected');
clean_post_cache($rollback_id);
ext_check(get_post_status($rollback_id)==='draft' && get_post($rollback_id)->post_content==='Original','Real transaction rolls back draft modification');
ext_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ba_reports")===$before_reports && (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}")===$before_posts,'Rollback leaves no partial report or event');
$fixed=$svc->ingest($rollback);
ext_check(!is_wp_error($fixed) && $fixed['event_id']===$rollback_id,'Retry after failed adoption succeeds once');

// Review revision invalidated by editor changes; missing provenance never publishes.
$ad=$a['event_id'];$rev=array('basis'=>'other_documented_basis','reason'=>'Synthetic review','public_hash'=>BadAround_External_Report_Contract::public_hash($ad));
ext_check($admin->save_review($ad,$rev)===true,'Second review succeeds');
wp_update_post(array('ID'=>$ad,'post_content'=>'Changed public draft'));
ext_check(ext_code($publication->publish($ad))==='ba_external_editorial_review_required','Changed public content requires fresh review');
$anonymous=rest_do_request(new WP_REST_Request('POST','/badaround/v1/external-reports'));
wp_set_current_user(0);
$request=new WP_REST_Request('POST','/badaround/v1/external-reports');$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($input));
$response=rest_do_request($request);
ext_check($response->get_status()===401 || $response->get_status()===403,'Private REST endpoint rejects anonymous callers');
wp_set_current_user(1);
$native=$input;$native['schema_version']='badaround-report/external-v1';
ext_check(!BadAround_Report_Version_Registry::supported('badaround-report/external-v1'),'Native version registry does not accept external submissions');
// Enrichment from a synthetic capture; no external photo or rights assertion used.
$private=ABSPATH.'../external-private-ci';wp_mkdir_p($private);
add_filter('badaround_private_media_path',function()use($private){return $private;});
$img=imagecreatetruecolor(120,100);imagefill($img,0,0,imagecolorallocate($img,180,0,0));imagefilledrectangle($img,25,25,64,44,imagecolorallocate($img,255,255,255));
ob_start();imagepng($img);$capture=ob_get_clean();imagedestroy($img);
$enrich_input=$input;$enrich_input['source_reference']='group:123456:post:987659';$enrich_input['source_url']='https://www.facebook.com/groups/123456/permalink/987659/';$enrich_input['evidence_sha256']=hash('sha256',$capture);
$created=$svc->ingest($enrich_input);$eid=$created['event_id'];$erid=$created['report_id'];$immutable=ext_row($erid)->content_original;
$enrich_review=array('basis'=>'other_documented_basis','reason'=>'Synthetic CI evidence only.','public_hash'=>BadAround_External_Report_Contract::public_hash($eid));
$admin->save_review($eid,$enrich_review);(new BadAround_Moderation_Service())->transition($eid,'approved');
ext_check($publication->publish($eid)===true,'Synthetic source initially published territory-only');
$enrichment=array('expected_event_hash'=>BadAround_External_Report_Contract::event_hash($eid),'reopen_for_review'=>true,'lat'=>41.944,'lng'=>12.5525,
 'geo_source_url'=>'https://www.turismoroma.it/it/luoghi/antichi-talenti-mercato-vintage','geo_note'=>'Synthetic anchor for contract test; not an incident point.',
 'evidence_base64'=>base64_encode($capture),'evidence_sha256'=>hash('sha256',$capture),'crop'=>array(10,10,100,80),'redaction'=>array(15,15,40,20));
$enricher=new BadAround_External_Report_Enrichment();
wp_set_current_user(0);ext_check(is_wp_error($enricher->save($eid,$enrichment)),'Enrichment rejects anonymous callers');wp_set_current_user(1);
$bad=$enrichment;$bad['evidence_sha256']=str_repeat('0',64);
ext_check(is_wp_error($enricher->save($eid,$bad)) && get_post_status($eid)==='publish','Wrong capture hash preserves published event');
$bad=$enrichment;$bad['redaction']=array(0,0,999,999);
ext_check(is_wp_error($enricher->save($eid,$bad)),'Out of bounds image edit rejected');
$bad=$enrichment;$bad['expected_event_hash']=str_repeat('0',64);
ext_check(is_wp_error($enricher->save($eid,$bad)) && get_post_status($eid)==='publish','Stale enrichment cannot withdraw event');
$old_hash=BadAround_External_Report_Contract::public_hash($eid);
$wpdb->suppress_errors(true);add_filter('query',$fail);$broken=$enricher->save($eid,$enrichment);remove_filter('query',$fail);$wpdb->suppress_errors(false);
clean_post_cache($eid);
ext_check(is_wp_error($broken) && get_post_status($eid)==='publish' && BadAround_External_Report_Contract::public_hash($eid)===$old_hash,'Enrichment audit failure rolls back event and review');
$updated=$enricher->save($eid,$enrichment);
ext_check(!is_wp_error($updated),'Enrichment succeeds '.ext_code($updated));
ext_check(get_post_status($eid)==='pending' && get_post_meta($eid,'_ba_moderation_status',true)==='new','Enrichment withdraws event for fresh moderation');
ext_check(get_post_meta($eid,'_ba_public_geo',true)==='' && get_post_meta($eid,'_ba_public_lat',true)==='' && !has_post_thumbnail($eid),'No premature marker or public image');
ext_check(ext_row($erid)->content_original===$immutable && ext_row($erid)->consent_privacy===null,'Original capture contract and consents unchanged');
$manifest=get_post_meta($eid,BadAround_External_Report_Enrichment::META,true);
$private_file=(new BadAround_Media_Repository())->private_file_for_media_id($updated['media_id']);
ext_check(!is_wp_error($private_file) && !str_starts_with(realpath($private_file['path']),realpath(ABSPATH)),'Redacted candidate stored outside public WordPress');
$check_image=imagecreatefromjpeg($private_file['path']);$rgb=imagecolorat($check_image,30,25);imagedestroy($check_image);
ext_check((($rgb>>16)&255)<50 && (($rgb>>8)&255)<50 && ($rgb&255)<50,'Candidate plate region actually obscured');
ext_check(hash_file('sha256',$private.'/report-'.$erid.'/'.$manifest['original_key'])===hash('sha256',$capture),'Original screenshot retained privately byte exact');
$dupe=$enricher->save($eid,$enrichment);
ext_check(($dupe['duplicate']??false) && $dupe['media_id']===$updated['media_id'],'Enrichment retry does not create duplicate media');
$enrich_review['public_hash']=BadAround_External_Report_Contract::public_hash($eid);
ext_check(is_wp_error($admin->save_review($eid,$enrich_review)),'Photo requires separate explicit rights and redaction review');
$enrich_review['media_reviewed']='1';
ext_check($admin->save_review($eid,$enrich_review)===true,'Synthetic photo review recorded');
ext_check(is_wp_error($publication->publish($eid)) && get_post_status($eid)==='pending','Enrichment review does not bypass B2 approval');
ext_check((new BadAround_Moderation_Service())->transition($eid,'approved')===true,'Fresh normal B2 approval required');
$published=$publication->publish($eid);
ext_check($published===true,'B3 publishes enriched synthetic candidate '.ext_code($published));
$view=BadAround_Public_Geo_Projection::public_view($eid);
ext_check(is_array($view) && $view['mode']==='approximate_area' && $view['radius_m']==1000 && abs($view['lat']-41.944)>0.00001,'Public marker is generalized and sealed by B3');
ext_check(has_post_thumbnail($eid),'Only reviewed derivative becomes featured image');
ext_check(ext_row($erid)->content_original===$immutable,'Enriched publication preserves immutable source');

echo 'EXT_INTAKE_01_WORDPRESS=PASS checks='.$GLOBALS['checks']."\n";

