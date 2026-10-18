<?php
/* GEO-PRIV-02B regression, synthetic WordPress harness only.
 * No real database or staging fixture is modified.
 */
require __DIR__ . '/f1-14r-publication-contract.php';

function gp_assert($ok,$description) { check((bool)$ok,$description); }
function gp_coords($id) {
  return [get_post_meta($id,'_ba_public_lat'),get_post_meta($id,'_ba_public_lng'),
    get_post_meta($id,'_ba_public_radius_m')];
}
function gp_valid_different($id,$expected_radius,$private_lat=41.6,$private_lng=12.5) {
  $v=gp_coords($id);
  gp_assert(is_numeric($v[0]) && is_numeric($v[1]),"case $id public lat/lng exist");
  gp_assert((int)$v[2]===$expected_radius,"case $id correct radius");
  gp_assert(abs((float)$v[0]-$private_lat)>0.000001 || abs((float)$v[1]-$private_lng)>0.000001,"case $id public coordinate differs from private");
  $before=$v;
  gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection($id),"case $id repeat projection");
  gp_assert($before===gp_coords($id),"case $id deterministic idempotent public coords");
  gp_assert(!str_contains(get_post($id)->post_content,'Private Address 99'),"case $id private address not leaked");
  gp_assert(true===$GLOBALS['gp_service']->validate_public_projection($id),"case $id final privacy validation");
}
$GLOBALS['gp_service']=new BadAround_Publication_Service();
$GLOBALS['private_fixture']=['exact_lat'=>41.6,'exact_lng'=>12.5,'exact_address'=>'Private Address 99'];
foreach([201=>['f32-c4',150],202=>['f32-c5',300],203=>['f32-c6',1000]] as $id=>$fixture) {
  seed($id,4,'f16-c8','',['_ba_public_location_precision'=>$fixture[0]]);
  gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection($id),"case $id prepare");
  gp_valid_different($id,$fixture[1]);
}
seed(204,4,'f16-c8','',['_ba_public_location_precision'=>'f32-c7']);
gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection(204),'municipality projection no verified centre');
gp_assert(''===get_post_meta(204,'_ba_public_lat'),'municipality never uses private report point');
gp_assert(''===get_post_meta(204,'_ba_public_lng'),'municipality no private longitude');
gp_assert(''===get_post_meta(204,'_ba_public_radius_m'),'municipality territory-only without verified centre');
$GLOBALS['term_meta'][3]=['_ba_geo_verified'=>'yes','_ba_center_lat'=>'41.67','_ba_center_lng'=>'12.47'];
gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection(204),'municipality verified centre prepare');
$municipality=gp_coords(204);
gp_assert((float)$municipality[0]===41.67 && (float)$municipality[1]===12.47 && (int)$municipality[2]===3000,'municipality verified canonical centre and radius');
gp_assert(true===$GLOBALS['gp_service']->validate_public_projection(204),'municipality privacy validation');
unset($GLOBALS['term_meta'][3],$GLOBALS['private_fixture']);
// Fixture #269: valid canonical territory with no private coordinates remains territory-only.
seed(269,4,'f16-c8','',['_ba_public_location_precision'=>'f32-c6']);
gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection(269),'fixture #269 territory-only prepare');
gp_assert(''===get_post_meta(269,'_ba_public_lat') && ''===get_post_meta(269,'_ba_public_lng'),'fixture #269 no invented coordinates');
gp_assert(true===$GLOBALS['gp_service']->validate_public_projection(269),'fixture #269 territory-only valid');
// Fixture #260: previously published coordinates untouched, idempotent.
$GLOBALS['private_fixture']=['exact_lat'=>41.6,'exact_lng'=>12.5,'exact_address'=>'Private Address 99'];
seed(260,4,'f16-c8','',['_ba_public_location_precision'=>'f32-c6','_ba_public_lat'=>41.67467,'_ba_public_lng'=>12.502868,'_ba_public_radius_m'=>1000]);
$existing=gp_coords(260);
gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection(260),'fixture #260 public projection prepared');
gp_assert($existing===gp_coords(260),'fixture #260 preserved exactly');
gp_assert(true===$GLOBALS['gp_service']->validate_public_projection(260),'fixture #260 privacy valid');
unset($GLOBALS['private_fixture']);
// Invalid and missing private coordinates never fabricate a map pin.
foreach([205=>['exact_lat'=>'not-a-number','exact_lng'=>12.5],206=>['exact_lat'=>91,'exact_lng'=>12.5],207=>['exact_lat'=>null,'exact_lng'=>null]] as $id=>$fixture) {
  $GLOBALS['private_fixture']=$fixture;
  seed($id,4,'f16-c8','',['_ba_public_location_precision'=>'f32-c6']);
  gp_assert(true===$GLOBALS['gp_service']->prepare_public_projection($id),"case $id territory-only invalid/missing fallback");
  gp_assert(''===get_post_meta($id,'_ba_public_lat') && ''===get_post_meta($id,'_ba_public_lng'),"case $id no public pin");
  gp_assert(true===$GLOBALS['gp_service']->validate_public_projection($id),"case $id valid territory-only");
}
unset($GLOBALS['private_fixture']);
echo "GEO_PRIV_02B_REGRESSION=PASS\n";
