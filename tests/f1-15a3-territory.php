<?php
/** F1.15A.3 synthetic, no WordPress bootstrap, database or staging fixtures. */
define('ABSPATH', __DIR__ . '/');
function __($s){return $s;}
function absint($x){return abs((int)$x);}
function sanitize_key($x){return strtolower((string)$x);}
function sanitize_text_field($x){return trim((string)$x);}
function is_wp_error($x){return $x instanceof WP_Error;}
function wp_generate_uuid4(){return 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';}
function current_time($mode,$gmt=false){return '2026-10-12 10:00:00';}
class WP_Error {
  private $code,$data;
  function __construct($code,$message='',$data=array()){$this->code=$code;$this->data=$data;}
  function get_error_code(){return $this->code;}
  function get_error_data(){return $this->data;}
}
class BadAround_Event_Post_Type {
  const POST_TYPE='ba_evento'; const TERRITORY_TAX='ba_territorio';
}
class BadAround_Audit_Log {static function record(){} static function technical_error(){}}
class WP_Term {
  public $term_id,$parent,$name,$taxonomy='ba_territorio';
  function __construct($id,$parent,$name){$this->term_id=$id;$this->parent=$parent;$this->name=$name;}
}
$GLOBALS['term_fixture']=array(
  new WP_Term(2,0,'Lazio'),new WP_Term(3,2,'Roma'),new WP_Term(4,3,'Pomezia'),
  new WP_Term(5,4,'Torvaianica'),new WP_Term(12,0,'Abruzzo'),new WP_Term(13,12,'Chieti'),
  new WP_Term(14,13,'Pomezia'),new WP_Term(15,14,'Torvaianica'),
);
function get_terms($args){
  if($args['taxonomy']!=='ba_territorio')return new WP_Error('bad_taxonomy');
  return array_values(array_filter($GLOBALS['term_fixture'],function($t)use($args){
    return $t->name===$args['name'] && $t->parent===$args['parent'];
  }));
}
function get_term($id,$tax='ba_territorio'){
  foreach($GLOBALS['term_fixture'] as $term)if($term->term_id===$id && $term->taxonomy===$tax)return $term;
  return null;
}
function ok($test,$message){if(!$test){fwrite(STDERR,"FAIL ".$message."\n");exit(1);}echo "PASS ".$message."\n";}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-territory-resolver.php';
$r=new BadAround_Territory_Resolver();
function path_for($region='Lazio',$province='Roma',$municipality='Pomezia',$locality='Torvaianica'){
  return compact('region','province','municipality','locality');
}
ok($r->resolve_path(path_for())===5,'full path resolves term 5');
ok($r->resolve_path(path_for('Lazio','Roma','Pomezia',''))===4,'optional locality resolves term 4');
ok(is_wp_error($r->resolve_path(path_for('Lazio','Chieti'))),'province wrong parent rejected');
ok(is_wp_error($r->resolve_path(path_for('Lazio','Roma','Altro'))),'municipality wrong parent rejected');
ok(is_wp_error($r->resolve_path(path_for('Lazio','Roma','Pomezia','Sconosciuta'))),'locality wrong parent rejected');
ok(is_wp_error($r->resolve_path(path_for('Assente'))),'nonexistent region rejected');
ok(is_wp_error($r->resolve_path(path_for('Lazio','Roma','',''))),'required municipality rejected');
ok($r->resolve_path(path_for('Abruzzo','Chieti','Pomezia','Torvaianica'))===15,'same-name municipalities different provinces deterministic');
$GLOBALS['term_fixture'][]=new WP_Term(16,3,'Pomezia');
ok(is_wp_error($r->resolve_path(path_for('Lazio','Roma','Pomezia',''))),'indistinguishable homonym rejected');
array_pop($GLOBALS['term_fixture']);
$a=path_for();$a['exact_address']='Chieti, Via Riservata 25';$a['area_label']='Abruzzo';
ok($r->resolve_path($a)===5,'private address and area label do not affect identity');

class FakeRepo {
  public $linked=0,$event=0;
  function event_id_for_report($r){return $this->event;}
  function link_event($r,$id){$this->linked++;$this->event=$id;return true;}
}
class FakeTax {function assign($event,$report){return array('category_term_id'=>1);}}
class FakeMedia{}
class FakePublic {public $calls=0;function prepare_public_projection($id){$this->calls++;return true;}}
$GLOBALS['events']=array();$GLOBALS['assigned']=array();
function wp_insert_post($data,$error=true){$id=200+count($GLOBALS['events']);$GLOBALS['events'][$id]=$data;return $id;}
function wp_set_object_terms($id,$terms,$tax,$append=false){$GLOBALS['assigned'][$id][$tax]=$terms;return $terms;}
function get_posts($args){return array();}
function wp_delete_post($id,$force=false){unset($GLOBALS['events'][$id]);return true;}
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-report-persistence-service.php';
$repo=new FakeRepo();$publication=new FakePublic();
$persist=new BadAround_Report_Persistence_Service($repo,new FakeTax(),$r,new FakeMedia(),$publication);
$report=array('location'=>array_merge(path_for(),array('exact_address'=>'Chieti, Via Riservata 25','area_label'=>'Abruzzo','public_precision'=>'area')), 'event'=>array('category'=>'hazard'));
$verified=$r->resolve_path($report['location']);
$out=$persist->persist(77,$report,array('type'=>'native'),array('territory_term_id'=>$verified,'prepare_public_projection'=>false));
ok(!is_wp_error($out) && $GLOBALS['assigned'][$out['event_id']]['ba_territorio']===array(5),'verified native term ID preserved in event taxonomy');
ok($publication->calls===0,'territory-only publication service never invoked');
ok(!str_contains(json_encode($GLOBALS['events'][$out['event_id']]['meta_input']),'Via Riservata'),'private address not exposed in event metadata');
$again=$persist->persist(77,$report,array('type'=>'native'),array('territory_term_id'=>$verified));
ok($again['duplicate'] && count($GLOBALS['events'])===1,'idempotent retry creates no duplicate event');
ok($repo->linked===1,'report/event linkage remains single');
$source=file_get_contents(dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-native-report-intake-service.php');
ok(strpos($source,'->resolve_path(')<strpos($source,'->reserve('),'native path validation runs before idempotency reserve');
ok(strpos($source,'->resolve_path(')<strpos($source,'persist_canonical_private_data('),'native path validation runs before private persistence');
ok(strpos($source,"result['territory_term_id']")!==false,'native intake passes validated identity only as internal result metadata');
echo "F1.15A3_SYNTHETIC=PASS\n";
