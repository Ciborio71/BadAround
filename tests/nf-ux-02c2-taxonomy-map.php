<?php
/** Actual canonical mapper + actual persistence; synthetic read-only registry. */
ob_start();require __DIR__.'/native-report-f14.php';ob_end_clean();
require dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/class-badaround-event-taxonomy-map.php';
$GLOBALS['rmap_terms']=['hazard'=>[new WP_Term(13,0,'pericoli')],'hazard_road_obstruction'=>[new WP_Term(45,13,'ostacolo-pericoloso-sulla-carreggiata')]];
function get_terms($args){return $GLOBALS['rmap_terms'][$args['meta_value']]??[];}
class RMapPause extends BadAround_Event_Taxonomy_Map {public $pause=true;public function assign($id,$p){if($this->pause){$this->pause=false;throw new RuntimeException('map interrupt');}return parent::assign($id,$p);}}
$checks=0;function mc($ok,$why){global $checks;++$checks;if(!$ok)throw new RuntimeException($why);}
foreach(['empty','category_only','subtype_only','territory_only','both','conflict','missing_mapping','duplicate_mapping'] as $i=>$state){
 $rid=701+$i;$repo=new F14Repo();$map=new RMapPause();$svc=new BadAround_Report_Persistence_Service($repo,$map,new F14Territory(),new F14Media(),new F14Pub());$source=['type'=>'native','submission_id'=>report()['submission_id']];
 try{$svc->persist($rid,report(),$source);throw new LogicException('No interruption');}catch(RuntimeException $e){mc($e->getMessage()==='map interrupt','real mapper boundary reached');}
 $events=get_posts(['meta_query'=>[['value'=>$rid]]]);mc(count($events)===1,'one inserted event');$event=$events[0];$n=count($GLOBALS['f14_posts']);
 $GLOBALS['f14_terms'][$event]=['ba_tipo_evento'=>match($state){'category_only'=>[13],'subtype_only'=>[45],'both'=>[13,45],'conflict'=>[999],default=>[]},'ba_territorio'=>in_array($state,['territory_only','both'])?[4]:[]];
 if($state==='missing_mapping')$GLOBALS['rmap_terms']['hazard_road_obstruction']=[];
 if($state==='duplicate_mapping')$GLOBALS['rmap_terms']['hazard_road_obstruction'][]=new WP_Term(46,13,'ostacolo-pericoloso-sulla-carreggiata');
 $before=$GLOBALS['f14_terms'][$event];$r=$svc->persist($rid,report(),$source);
 if(in_array($state,['conflict','missing_mapping','duplicate_mapping']))mc(is_wp_error($r)&&$before===$GLOBALS['f14_terms'][$event],'registry/assignment conflict preserved');
 else{$terms=$GLOBALS['f14_terms'][$event]['ba_tipo_evento'];sort($terms);mc(!is_wp_error($r)&&$r['event_id']===$event&&$terms===[13,45]&&$GLOBALS['f14_terms'][$event]['ba_territorio']===[4],'real canonical mapper completion');$before=$GLOBALS['f14_terms'][$event];$r=$svc->persist($rid,report(),$source);mc(!is_wp_error($r)&&$before===$GLOBALS['f14_terms'][$event],'complete mapper retry unchanged');}
 mc(count($GLOBALS['f14_posts'])===$n,'no duplicate or deletion');$GLOBALS['rmap_terms']['hazard_road_obstruction']=[new WP_Term(45,13,'ostacolo-pericoloso-sulla-carreggiata')];
}
echo "NF_UX_02C2_REAL_MAP=PASS assertions={$checks} states=8 registry_mutations=0\n";
