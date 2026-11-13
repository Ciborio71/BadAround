<?php
/** Isolated model transport, privacy, queue and human-decision integration fixtures. */
define('ABSPATH', '/test/httpdocs/'); define('ARRAY_A','ARRAY_A');
$options=[];$meta=[];$posts=[];$hooks=[];$routes=[];$allowed=true;$calls=[];$scheduled=[];$mail=[];$publication=[];$responses=[];$media=[];
class WP_Error { public function __construct(private $code,private $message='',private $data=[]){}public function get_error_code(){return $this->code;}public function get_error_message(){return $this->message;} }
class WP_REST_Response { public function __construct(public $data,public $status){} }
function is_wp_error($x){return $x instanceof WP_Error;}
function add_action($n,$cb,...$args){$GLOBALS['hooks'][$n]=$cb;}
function register_rest_route($ns,$path,$args){$GLOBALS['routes'][$ns.$path]=$args;}
function get_option($n,$default=false){return $GLOBALS['options'][$n]??$default;}
function update_option($n,$value,$autoload=null){$GLOBALS['options'][$n]=$value;return true;}
function get_post_meta($id,$key=null,$single=false){if($key===null){return $GLOBALS['meta'][$id]??[];}return $GLOBALS['meta'][$id][$key]??'';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function clean_post_cache($id){}
function get_post($id){return $GLOBALS['posts'][$id]??null;}
function get_post_type($id){return get_post($id)?->post_type;}
function get_post_status($id){return get_post($id)?->post_status;}
function current_user_can(...$args){return $GLOBALS['allowed'];}
function get_current_blog_id(){return 1;}
function get_current_user_id(){return 7;}
function wp_get_post_terms(...$args){return [2,3];}
function wp_json_encode($x){return json_encode($x);}
function wp_strip_all_tags($x){return strip_tags($x);}
function wp_kses_post($x){return strip_tags($x);}
function wp_update_post($data,$errors=false){foreach($data as $k=>$v){if($k!=='ID'){$GLOBALS['posts'][$data['ID']]->$k=$v;}}return $data['ID'];}
function wp_schedule_single_event($time,$hook,$args){$GLOBALS['scheduled'][]=[$time,$hook,$args];return true;}
function wp_clear_scheduled_hook(...$args){}
function wp_remote_post($url,$args){$GLOBALS['calls'][]=[$url,$args];return array_shift($GLOBALS['responses'])??new WP_Error('unmocked');}
function wp_remote_retrieve_response_code($r){return $r['status'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function admin_url($p){return 'https://example.test/wp-admin/'.$p;}
function esc_html($x){return htmlspecialchars($x,ENT_QUOTES);}
function esc_url($x){return htmlspecialchars($x,ENT_QUOTES);}
function esc_attr($x){return htmlspecialchars($x,ENT_QUOTES);}
function esc_textarea($x){return htmlspecialchars($x,ENT_QUOTES);}
function absint($x){return abs((int)$x);}
function sanitize_textarea_field($x){return strip_tags($x);}
function is_ssl(){return $GLOBALS['ssl']??true;}
function wp_die($m,...$args){throw new RuntimeException('denied');}
function check_admin_referer($n){if(!($GLOBALS['nonce']??true)){throw new RuntimeException('nonce');}}
function wp_unslash($x){return $x;}
class TestDB {
 public $prefix='wp_';
 public function prepare($sql,...$values){return [$sql,$values];}
 public function get_var($query){return '1';}
 public function get_row($query){return $GLOBALS['row'];}
 public function get_results(...$args){return $GLOBALS['media'];}
}
$wpdb=new TestDB;
class BadAround_AI_Moderation_Config {
 public static function read(){return ['api_key'=>'sk-fixture-not-a-real-key-123456','moderator_email'=>'qa@example.test','expires_on'=>'2099-01-01','revision'=>'fixture-revision'];}
 public static function status(){return ['credential_present'=>true,'credential_expired'=>false];}
}
class BadAround_Report_Schema {
 const PRIVACY_PUBLIC='public';
 public static function fields(){return ['event.category'=>['privacy'=>'public'],'vehicle.make'=>['privacy'=>'public'],'reporter.email'=>['privacy'=>'private'],'location.exact_address'=>['privacy'=>'private'],'vehicle.plate_raw'=>['privacy'=>'private']];}
}
class BadAround_Transactional_Mailer {public function send(...$args){$GLOBALS['mail'][]=$args;return true;}}
class BadAround_Moderation_Service {public function transition($id,$target,$reason){$GLOBALS['meta'][$id]['_ba_moderation_status']=$target;return true;}}
class BadAround_Publication_Service {
 public static function uses_versioned_geo($id){return true;}
 public function publish($id){$GLOBALS['publication'][]=$id;$GLOBALS['posts'][$id]->post_status='publish';$GLOBALS['meta'][$id]['_ba_moderation_status']='published';return true;}
}
class BadAround_Audit_Log {public static function record(...$args){return true;}}
require __DIR__.'/../wordpress/mu-plugins/badaround-ai-moderation-workflow.php';
function check($value,$label){if(!$value){throw new RuntimeException('FAIL '.$label);}echo 'PASS '.$label."\n";}
function result($decision='approve'){return ['recommendation'=>$decision,'summary'=>'Segnalazione pertinente di un veicolo.','rationale'=>'Informazioni sufficienti per una revisione editoriale.','proposed_description'=>'Segnalato un veicolo rubato a Roma.','flags'=>[]];}
function response($result,$status='completed'){return ['status'=>200,'body'=>json_encode(['status'=>$status,'output'=>[['content'=>[['type'=>'output_text','text'=>json_encode($result)]]]]])];}
$canonical=['schema_version'=>'badaround-report/v2','event'=>['category'=>'vehicle'],'vehicle'=>['make'=>'Fiat','plate_raw'=>'AB123CD'],
 'location'=>['municipality'=>'Roma','exact_address'=>'Via Riservata 123, Roma','exact_lat'=>41.123456,'exact_lng'=>12.123456,'public_location_request'=>['mode'=>'approximate_area','radius_m'=>100]],
 'content'=>['description'=>'Mi chiamo Mario Rossi. Fiat rubata. Scrivete a mario@example.test o al 3331234567. Via Riservata 123, Roma.'],
 'reporter'=>['public_identity_mode'=>'anonymous','email'=>'mario@example.test','first_name'=>'Mario','last_name'=>'Rossi'], 'media'=>['items'=>[]]];
$row=(object)['content_original'=>json_encode(['canonical'=>$canonical]),'author_name'=>'Mario','author_surname'=>'Rossi','author_email'=>'mario@example.test','author_phone'=>'3331234567','exact_address'=>'Via Riservata 123, Roma','exact_lat'=>'41.123456','exact_lng'=>'12.123456'];
function fixture(){global $posts,$meta,$row,$canonical;$posts[11]=(object)['ID'=>11,'post_type'=>'ba_evento','post_status'=>'pending','post_title'=>'Da moderare','post_content'=>'Testo in attesa.'];$meta[11]=['_ba_primary_report_id'=>99,'_ba_moderation_status'=>'new'];$row->content_original=json_encode(['canonical'=>$canonical]);}
fixture();
check(!BadAround_AI_Moderation_Workflow::enabled(),'default is inactive');
check(BadAround_AI_Moderation_Workflow::redact('2026-11-13')==='2026-11-13','date is not mistaken for a phone number');
check(!str_contains(BadAround_AI_Moderation_Workflow::redact('sk-fake_key_that_must_be_redacted_123'),'sk-'),'accidentally pasted API-shaped credentials redacted');
$context=BadAround_AI_Moderation_Workflow::context(11);$payload=$context['payload'];$encoded=json_encode($payload);
foreach(['mario@example.test','Mario','Rossi','3331234567','Via Riservata','41.123456','12.123456','AB123CD'] as $secret){check(!str_contains($encoded,$secret),'payload excludes '.$secret);}
check($payload['facts']['requested_precision']==='approximate_area'&&$payload['facts']['requested_radius_m']===100,'precision and radius preserved without private point');
check(!BadAround_AI_Moderation_Workflow::validate_result(['recommendation'=>'publish_now']),'unknown or incomplete model result rejected');
$responses[]=response(result());$evaluated=BadAround_AI_Moderation_Workflow::evaluate($payload);check(!is_wp_error($evaluated),'structured response accepted');
$request=json_decode($calls[0][1]['body'],true);check($request['store']===false&&$request['service_tier']==='default'&&$request['text']['format']['strict']===true,'stateless Standard tier and strict schema');
check(!isset($request['tools'])&&str_contains($request['instructions'],'MAI veridicità')&&str_contains($request['instructions'],'DATI NON ATTENDIBILI'),'no tools, truth verification or trusted user instructions');
check(!str_contains($request['input'],'mario@example.test'),'transport receives minimized data');
$responses[]=response(result(),'incomplete');check(is_wp_error(BadAround_AI_Moderation_Workflow::evaluate($payload)),'incomplete response fails closed');
$responses[]=['status'=>429,'body'=>'secret provider body'];$e=BadAround_AI_Moderation_Workflow::evaluate($payload);check(is_wp_error($e)&&!str_contains($e->get_error_message(),'secret provider'),'provider error never leaks body');
$responses[]=['status'=>200,'body'=>json_encode(['status'=>'completed','output'=>[['content'=>[['type'=>'refusal']]]]])];check(is_wp_error(BadAround_AI_Moderation_Workflow::evaluate($payload)),'refusal fails closed');
$withImages=$payload;$withImages['media_count']=1;$responses[]=response(result());$r=BadAround_AI_Moderation_Workflow::evaluate($withImages);check($r['recommendation']==='human_review','unexamined images always require human examination');
$options['badaround_ai_daily_usage']=['day'=>gmdate('Y-m-d'),'count'=>50];$before=count($calls);check(is_wp_error(BadAround_AI_Moderation_Workflow::evaluate($payload))&&count($calls)===$before,'daily cap blocks requests before network');
$options['badaround_ai_daily_usage']=['day'=>gmdate('Y-m-d'),'count'=>0];$options['badaround_ai_moderation_enabled']=true;
check(BadAround_AI_Moderation_Workflow::enqueue(11),'event enqueued');check(count($calls)===$before,'enqueue never performs synchronous AI call');
check(!BadAround_AI_Moderation_Workflow::enqueue(11),'duplicate intake does not duplicate job');
$responses[]=response(result());BadAround_AI_Moderation_Workflow::run(11);$job=$meta[11][BadAround_AI_Moderation_Workflow::META];
check($job['state']==='ready'&&$job['mail_state']==='sent','worker stores a ready proposal and notifies moderator');
check(count($publication)===0&&$posts[11]->post_status==='pending','worker cannot publish');
check(count($mail)===1&&!str_contains(json_encode($mail),'mario@example.test'),'mail excludes reporter contacts');
check(substr_count($mail[0][3],'href=')===3&&str_contains($mail[0][3],'#confirm')&&!str_contains($mail[0][3],'admin-post.php'),'email links are read-only admin views');
BadAround_AI_Moderation_Workflow::run(11);check(count($mail)===1,'completed worker cannot send twice');
$allowed=false;$e=BadAround_AI_Moderation_Workflow::decide(11,$job['revision'],$job['digest'],'confirm','','','Testo pubblico verificato.',true);check(is_wp_error($e)&&!$publication,'unauthorized human action rejected');$allowed=true;
$e=BadAround_AI_Moderation_Workflow::decide(11,$job['revision'],$job['digest'],'confirm','','','Testo pubblico verificato.',false);check(is_wp_error($e)&&!$publication,'publication requires explicit copy review checkbox');
$posts[11]->post_content='Contenuto cambiato dopo AI';$e=BadAround_AI_Moderation_Workflow::decide(11,$job['revision'],$job['digest'],'confirm','','','Testo pubblico verificato.',true);check(is_wp_error($e)&&!$publication,'changed event invalidates proposal');$posts[11]->post_content='Testo in attesa.';
$media[]=['id'=>4,'review_status'=>'approved'];$e=BadAround_AI_Moderation_Workflow::decide(11,$job['revision'],$job['digest'],'confirm','','','Testo pubblico verificato.',true);check(is_wp_error($e)&&!$publication,'media changes invalidate proposal');$media=[];
$e=BadAround_AI_Moderation_Workflow::decide(11,$job['revision'],$job['digest'],'disagree','Troppo presto per pubblicare','hold','',false);check($e===true&&!$publication&&$posts[11]->post_status==='pending','disagreement can keep pending without rejecting');
check(count($options['badaround_ai_validated_examples'])===1,'human reason stored as a bounded validated example');
fixture();$context=BadAround_AI_Moderation_Workflow::context(11);$job=['revision'=>'revision-2','digest'=>$context['digest'],'state'=>'ready','policy'=>BadAround_AI_Moderation_Workflow::POLICY,'payload'=>$payload,'result'=>result()];$meta[11][BadAround_AI_Moderation_Workflow::META]=$job;
$e=BadAround_AI_Moderation_Workflow::decide(11,'revision-2',$job['digest'],'confirm','','','Testo pubblico verificato da un moderatore.',true);check($e===true&&$publication===[11]&&$posts[11]->post_status==='publish','explicit human confirmation calls owning publication service');
$e=BadAround_AI_Moderation_Workflow::decide(11,'revision-2',$job['digest'],'confirm','','','Testo pubblico verificato.',true);check(is_wp_error($e)&&count($publication)===1,'replayed decision cannot publish again');
fixture();$context=BadAround_AI_Moderation_Workflow::context(11);$job=['revision'=>'revision-3','digest'=>$context['digest'],'state'=>'ready','policy'=>BadAround_AI_Moderation_Workflow::POLICY,'payload'=>$payload,'result'=>result('reject')];$meta[11][BadAround_AI_Moderation_Workflow::META]=$job;
$e=BadAround_AI_Moderation_Workflow::decide(11,'revision-3',$job['digest'],'confirm','','','',false);check($e===true&&$meta[11]['_ba_moderation_status']==='rejected'&&count($publication)===1,'reject uses moderation service and never publishes');
$hooks['rest_api_init']();$route=$routes['badaround-ops/v1/ai-moderation-test'];$allowed=false;check(!$route['permission_callback'](),'synthetic API test is administrator-only');$allowed=true;
$_SERVER['REQUEST_METHOD']='GET';try{BadAround_AI_Moderation_Workflow::handle();throw new LogicException('GET accepted');}catch(RuntimeException $e){check($e->getMessage()==='denied','GET cannot execute review actions');}
check(str_contains(file_get_contents(__DIR__.'/../wordpress/mu-plugins/badaround-ai-moderation-workflow.php'),'check_admin_referer'),'human POST verifies WordPress nonce');
fixture();$context=BadAround_AI_Moderation_Workflow::context(11);$job=['revision'=>'revision-4','digest'=>$context['digest'],'state'=>'ready','policy'=>BadAround_AI_Moderation_Workflow::POLICY,'payload'=>$payload,'result'=>result('edit')];$meta[11][BadAround_AI_Moderation_Workflow::META]=$job;
$e=BadAround_AI_Moderation_Workflow::decide(11,'revision-4',$job['digest'],'modify','Rendi il testo più chiaro senza aggiungere fatti','hold','',false);check($e===true&&$meta[11][BadAround_AI_Moderation_Workflow::META]['state']==='queued'&&count($publication)===1,'correction requests create a new generation without publishing');
$_SERVER['REQUEST_METHOD']='POST';$nonce=false;$_POST=['event_id'=>'11'];try{BadAround_AI_Moderation_Workflow::handle();throw new LogicException('nonce accepted');}catch(RuntimeException $e){check($e->getMessage()==='nonce','human POST rejects invalid nonce');}
echo "AI_WORKFLOW_SUITE_PASS\n";
