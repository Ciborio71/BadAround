<?php
/** Production PHP and SQL integration. CI uses MariaDB; local fallback uses SQLite UNIQUE constraints. */
ob_start();require __DIR__.'/native-report-f13.php';ob_end_clean();
define('ARRAY_A','ARRAY_A');
function get_current_user_id(){return 0;} function wp_salt($x=''){return 'synthetic-test-salt';}
function wp_unslash($x){return $x;} function wp_parse_url($x){return parse_url($x);}
function home_url($x='/'){return 'https://staging.badaround.it'.$x;} function is_ssl(){return true;}
function current_user_can(){return true;}
function trailingslashit($v){return rtrim($v,'/').'/';} function untrailingslashit($v){return rtrim($v,'/');}
function wp_normalize_path($v){return $v;} function sanitize_file_name($v){return basename($v);}function sanitize_mime_type($v){return $v;}
function wp_mkdir_p($v){return is_dir($v) || mkdir($v,0700,true);} function apply_filters($name,$v){return $v;}
function wp_get_image_mime($p){return (new finfo(FILEINFO_MIME_TYPE))->file($p);}
 function add_action(){} function add_filter(){} function remove_filter(){}
function get_transient($k){return $GLOBALS['transients'][$k]??false;} function set_transient($k,$v,$t){$GLOBALS['transients'][$k]=$v;return true;}
function register_rest_route($namespace,$route,$args){$GLOBALS['routes'][$route]=$args;}
function get_post_status($id){return $GLOBALS['posts'][$id]['post_status']??false;}
function get_post($id){return isset($GLOBALS['posts'][$id]) ? (object)$GLOBALS['posts'][$id] : null;}
function has_post_thumbnail($id){return false;} function set_post_thumbnail($id,$attachment){return true;}
function get_post_meta($id,$key,$single=true){return $GLOBALS['posts'][$id]['meta_input'][$key]??'';}
function wp_insert_post($a,$error=false){$id=1000+count($GLOBALS['posts']);$a['ID']=$id;$GLOBALS['posts'][$id]=$a;return $id;}
function wp_delete_post($id,$force=true){unset($GLOBALS['posts'][$id]);return true;}
function wp_set_object_terms($id,$terms,$taxonomy,$append=false){return $terms;}
function get_posts($args){$ids=[];$report=$args['meta_value']??$args['meta_query'][0]['value']??0;foreach($GLOBALS['posts'] as $id=>$p){if((int)($p['meta_input']['_ba_primary_report_id']??0)===(int)$report)$ids[]=$id;}return $ids;}
class WP_REST_Server {const CREATABLE='POST';}
class WP_REST_Request implements ArrayAccess {
	private $body;private $headers;private $params;
	function __construct($body='', $headers=[], $params=[]){$this->body=$body;$this->headers=array_change_key_case($headers);$this->params=$params;}
	function get_body(){return $this->body;} function get_header($k){return $this->headers[strtolower($k)]??'';}
	function get_file_params(){return $this->params['files']??[];} function get_body_params(){return $this->params['body']??[];}
	function offsetExists($k):bool{return isset($this->params[$k]);} function offsetGet($k):mixed{return $this->params[$k]??null;}
	function offsetSet($k,$v):void{$this->params[$k]=$v;} function offsetUnset($k):void{unset($this->params[$k]);}
}
class WP_REST_Response {public $data,$status,$headers=[];function __construct($d,$s){$this->data=$d;$this->status=$s;}function header($k,$v){$this->headers[$k]=$v;}}
class BadAround_Event_Post_Type {const POST_TYPE='ba_evento';const EVENT_TYPE_TAX='ba_tipo_evento';const TERRITORY_TAX='ba_territorio';}
class TestTax {function assign($e,$c){return true;}}
class TestTerritory {function resolve(){return 4;}}
class TestProjection {function prepare_public_projection(){throw new Exception('No native projection');}}

class MediaTestDB {
	public $prefix='test_',$insert_id=0,$last_error='',$writes=0,$queries=[],$fail=null;
	public $pdo;private $locks=[];
	function __construct($pdo=null){$this->pdo=$pdo ?: new PDO(getenv('BA_TEST_DSN') ?: 'sqlite::memory:',getenv('BA_TEST_DB_USER') ?: 'root',getenv('BA_TEST_DB_PASSWORD') ?: '');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
	function prepare($q,...$args){if(count($args)===1 && is_array($args[0]))$args=$args[0];foreach($args as $a){$q=preg_replace_callback('/%[sdf]/',function($m)use($a){return $m[0]==='%s' ? $this->pdo->quote((string)$a) : (string)(int)$a;},$q,1);}return $q;}
	function statement($q){$this->queries[]=$q;$this->last_error='';try {if($this->fail && call_user_func($this->fail,$q)){ $this->fail=null;throw new Exception('injected failure'); }return $this->pdo->query($q);}catch(Throwable $e){$this->last_error=$e->getMessage();return false;}}
	function get_var($q){
		if(strpos($q,'GET_LOCK(')!==false || strpos($q,'RELEASE_LOCK(')!==false){
			if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){ $s=$this->statement($q);return $s ? $s->fetchColumn() : null; }
			preg_match("/LOCK\('([^']+)'/",$q,$m);$key=$m[1];
			if(strpos($q,'RELEASE_LOCK')!==false){if(isset($this->locks[$key])){flock($this->locks[$key],LOCK_UN);fclose($this->locks[$key]);unset($this->locks[$key]);}return 1;}
			if(isset($this->locks[$key]))return 1;$handle=fopen(sys_get_temp_dir().'/ba-test-lock-'.$key,'c');if(!flock($handle,LOCK_EX|LOCK_NB)){fclose($handle);return 0;}$this->locks[$key]=$handle;return 1;
		}
		$s=$this->statement($q);return $s ? $s->fetchColumn() : null;
	}
	function get_row($q,$mode=null){$s=$this->statement($q);if(!$s)return null;$r=$s->fetch(PDO::FETCH_ASSOC);return $r ? ($mode===ARRAY_A ? $r : (object)$r) : null;}
	function get_results($q,$mode=null){$s=$this->statement($q);if(!$s)return null;$rows=$s->fetchAll(PDO::FETCH_ASSOC);return $mode===ARRAY_A ? $rows : array_map(fn($r)=>(object)$r,$rows);}
	function query($q){++$this->writes;$s=$this->statement($q);return $s ? $s->rowCount() : false;}
	function insert($table,$row,$formats=null){$q='INSERT INTO '.$table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_map(fn($v)=>$v===null ? 'NULL' : $this->pdo->quote((string)$v),array_values($row))).')';$r=$this->query($q);$this->insert_id=(int)$this->pdo->lastInsertId();return $r;}
	function update($table,$changes,$where,$a=null,$b=null){$set=[];$pred=[];foreach($changes as $k=>$v)$set[]=$k.'='.($v===null ? 'NULL' : $this->pdo->quote((string)$v));foreach($where as $k=>$v)$pred[]=$v===null ? $k.' IS NULL' : $k.'='.$this->pdo->quote((string)$v);return $this->query('UPDATE '.$table.' SET '.implode(',',$set).' WHERE '.implode(' AND ',$pred));}
	function schema($sql){
		if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
			$sql=preg_replace('/^\s*KEY[^\n]+\n/m','',$sql);$sql=preg_replace('/UNIQUE KEY \w+ /','UNIQUE ',$sql);
			$sql=preg_replace('/\b(bigint|tinyint|int)\b\s*(\(\d+\))?\s*(unsigned)?/i','INTEGER',$sql);
			$sql=str_replace('id INTEGER NOT NULL AUTO_INCREMENT,','id INTEGER PRIMARY KEY AUTOINCREMENT,',$sql);
			$sql=preg_replace('/PRIMARY KEY\s*\(id\),?/','',$sql);$sql=preg_replace('/,\s*\)/',')',$sql);
		}
		foreach(explode(';',$sql) as $q){if(trim($q)!=='')$this->pdo->exec($q);}
	}
}
$GLOBALS['wpdb']=$db=new MediaTestDB();$GLOBALS['posts']=[];$GLOBALS['transients']=[];
$inc=dirname(__DIR__).'/wordpress/plugins/badaround-core/includes/';
foreach(['report-repository','media-repository','moderation-service','publication-service','report-persistence-service','native-report-golden-path-service','native-report-rest-controller','native-media-config','native-media-capability','native-media-ledger','native-media-storage','native-media-validator','native-media-service','native-media-persistence','native-media-fence','native-media-cleanup','native-media-rest-controller'] as $name)require_once $inc.'class-badaround-'.$name.'.php';
$installer=file_get_contents($inc.'class-badaround-installer.php');
foreach(['reports'=>'ba_reports','media'=>'ba_report_media'] as $var=>$table){preg_match('/CREATE TABLE \{\$'.$var.'\} \((.*?)\) \{\$charset_collate\};/s',$installer,$m);$db->query('DROP TABLE IF EXISTS test_'.$table);$db->schema('CREATE TABLE test_'.$table.' ('.$m[1].');');}
foreach(['sessions','items','upload_keys','limits'] as $t)$db->query('DROP TABLE IF EXISTS test_ba_native_media_'.$t);
$db->schema(BadAround_Native_Media_Ledger::schema_sql('test_',''));
$root=sys_get_temp_dir().'/ba-native-test-'.bin2hex(random_bytes(8));mkdir($root,0700);
define('BADAROUND_PRIVATE_MEDIA_PATH',$root);
$public=sys_get_temp_dir().'/ba-public-test-'.bin2hex(random_bytes(8));mkdir($public,0700);
$settings=['enabled'=>true,'hosting_verified'=>true,'private_root'=>$root,'document_roots'=>[$public],'keys'=>['test'=>base64_encode(str_repeat('t',32))],'active_key_id'=>'test','temporary_budget'=>1073741824,'archive_budget'=>1073741824,'reserve_free_bytes'=>0,'upload_ip_limit'=>10000,'upload_session_limit'=>10000,'session_ip_limit'=>10000,'failed_ip_limit'=>10000,'failed_session_limit'=>10000,'ip_bytes_limit'=>1073741824,'session_bytes_limit'=>1073741824];
if(getenv('BA_DECODER_LIBRARIES'))$settings['decoder_library_path']=getenv('BA_DECODER_LIBRARIES');
$config=new BadAround_Native_Media_Config($settings);$ledger=new BadAround_Native_Media_Ledger($db);$storage=new BadAround_Native_Media_Storage($config);$validator=new BadAround_Native_Media_Validator($config);$fixture_epoch=2000000000;
$service=new BadAround_Native_Media_Service($config,$ledger,$storage,$validator,fn()=>$GLOBALS['now']);$GLOBALS['now']=$fixture_epoch;
