'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const read = p => fs.readFileSync(path.join(__dirname,'../wordpress/plugins/badaround-core/includes/',p),'utf8');
const contract=read('class-badaround-discovery-contract.php');
const discovery=read('class-badaround-discovery-query.php');
const publication=read('class-badaround-publication-service.php');
const persistence=read('class-badaround-report-persistence-service.php');
test('SF9: public discovery requires BOTH WordPress published status and moderation published',()=>{
  assert.match(contract,/get_post_status\(\s*\$event_id\s*\)/);
  assert.match(contract,/'publish'\s*===\s*get_post_status/);
  assert.match(contract,/STATUS_PUBLISHED\s*===\s*sanitize_key/);
  assert.match(discovery,/post_status'\s*=>\s*'publish'/);
  assert.match(discovery,/BadAround_Discovery_Contract::is_public_event\(/);
});
test('SF9: public discovery projection is explicit allowlist with no raw report or sensitive address',()=>{
  const segment=discovery.split('private function public_projection( WP_Post $post ) {')[1].split('private function public_geo(')[0];
  assert(segment,'public projection found');
  for(const key of ['id','title','permalink','excerpt','thumbnail','event_type','territory','public_geo'])assert(segment.includes("'"+key+"'"),key);
  for(const leak of ['author_email','author_phone','exact_address','exact_lat','exact_lng','full_plate','content_original','get_row(','ba_reports'])assert(!segment.includes(leak),'forbidden source in public projection: '+leak);
});
test('SF9: public geo never falls back to private coordinates or territory centroid',()=>{
  const segment=discovery.split('private function public_geo( $event_id ) {')[1].split('private function deepest_term(')[0];
  assert(segment);
  assert(segment.includes("'_ba_public_lat'")&&segment.includes("'_ba_public_lng'"));
  assert(segment.includes('radius >= 100'));
  for(const forbidden of ['exact_lat','exact_lng','get_term_meta(','ba_reports'])assert(!segment.includes(forbidden),'forbidden geo source '+forbidden);
  assert(segment.includes('return null;'));
});
test('SF9: publication safety checks block PII, exact coords, unmasked plates, sensitive meta and private originals',()=>{
  for(const key of ['ba_publication_private_data_detected','ba_publication_exact_coordinates_detected','ba_publication_plate_not_masked','ba_publication_sensitive_meta_detected','ba_publication_media_segregation_invalid'])assert(publication.includes(key),key);
  assert(publication.includes('validate_public_projection( $event_id )'));
  assert(publication.includes("post_status'   => 'publish'"));
});
test('SF9: native intake starts pending and avoids auto-generating public projection',()=>{
  assert(persistence.includes("'post_status'  => 'pending'"));
  assert(persistence.includes("! empty( $options['prepare_public_projection'] )"));
  const golden=read('class-badaround-native-report-golden-path-service.php');
  assert(golden.includes("'prepare_public_projection' => false"));
});
