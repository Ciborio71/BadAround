<?php
/** Integration tests: disposable DB, real crypto/session/ownership/review classes. */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
define('BADAROUND_IDENTITY_PRIVATE_QA_ENABLED',true);
define('BADAROUND_IDENTITY_COMMUNITY_ENABLED',true);
define('BADAROUND_IDENTITY_QA_IP_ALLOWLIST',['203.0.113.42']);
$_SERVER['REMOTE_ADDR']='203.0.113.42';
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-private-access.php';
class BadAround_Identity_WordPress_Privacy_Notice {public static function runtime_version(){return 'qa-fixture';}}
require __DIR__.'/../wordpress/plugins/badaround-core/identity-dormant/wordpress-community-review.php';
require __DIR__.'/../wordpress/plugins/badaround-core/includes/class-badaround-publication-service.php';
$dsn=getenv('BA_TEST_DB_DSN')?:'sqlite::memory:';
if ($dsn!=='sqlite::memory:'&&!str_contains($dsn,'dbname=ba_identity_ci;'))throw new RuntimeException('Disposable database only');
$db=new PDO($dsn,getenv('BA_TEST_DB_USER')?:'root',getenv('BA_TEST_DB_PASS')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$mysql=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
$prefix='ci_review_'.bin2hex(random_bytes(4)).'_';$tables=[];
function table(string $name,string $columns):void {
    global $db,$mysql,$prefix,$tables;$tables[]=$prefix.$name;
    $db->exec('CREATE TABLE `'.$prefix.$name.'` ('.$columns.')'.($mysql?' ENGINE=InnoDB':''));
}
table('ba_identities','identity_id VARCHAR(128) PRIMARY KEY,verified_at BIGINT,revoked_at BIGINT NULL');
table('ba_identity_sessions','session_digest VARCHAR(64) PRIMARY KEY,identity_id VARCHAR(128),created_at BIGINT,last_seen_at BIGINT,expires_at BIGINT,revoked_at BIGINT NULL');
table('ba_identity_wp_actors','wp_user_id BIGINT PRIMARY KEY,identity_id VARCHAR(128) UNIQUE,verified_at BIGINT,revoked_at BIGINT NULL,version INT');
table('ba_identity_ownership','identity_id VARCHAR(128),resource_kind VARCHAR(24),resource_id BIGINT,relation VARCHAR(24),verified_binding_at BIGINT,revoked_at BIGINT NULL,UNIQUE(resource_kind,resource_id)');
table('ba_identity_moderation','intent_id VARCHAR(32) PRIMARY KEY,kind VARCHAR(16),identity_id VARCHAR(128),resource_id BIGINT,payload BLOB,nonce BLOB,tag BLOB,state VARCHAR(16),version INT,created_at BIGINT,moderated_at BIGINT NULL,moderator_id VARCHAR(128) NULL');
table('posts','ID BIGINT PRIMARY KEY,post_type VARCHAR(24),post_status VARCHAR(16),post_title TEXT,post_content TEXT');
table('postmeta','post_id BIGINT,meta_key VARCHAR(128),meta_value TEXT');
table('ba_reports','id BIGINT PRIMARY KEY,event_id BIGINT,deleted_at BIGINT NULL,payload_hash VARCHAR(64),content_original TEXT,author_name TEXT,author_surname TEXT,author_email TEXT,author_phone TEXT,exact_address TEXT,full_plate TEXT');
table('ba_audit_log','action VARCHAR(80),request_id VARCHAR(64),event_id BIGINT');
class ReviewWPDB {
    public string $last_error='';public string $posts;public string $postmeta;
    public function __construct(public PDO $db,public string $prefix){$this->posts=$prefix.'posts';$this->postmeta=$prefix.'postmeta';}
    public function prepare($sql,...$args){$i=0;return preg_replace_callback('/%[ds]/',function($m)use($args,&$i){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:$this->db->quote((string)$v);},$sql);}
    public function query($sql){$this->last_error='';try{if($sql==='START TRANSACTION')return $this->db->beginTransaction()?0:false;if($sql==='COMMIT')return $this->db->commit()?0:false;if($sql==='ROLLBACK')return $this->db->rollBack()?0:false;return $this->db->exec($sql);}catch(Throwable $e){$this->last_error=$e->getMessage();return false;}}
    private function sql($sql){return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?str_replace(' FOR UPDATE','',$sql):$sql;}
    public function get_var($sql){$this->last_error='';if(str_contains($sql,'information_schema.TABLES'))return 'InnoDB';if(str_contains($sql,'@@in_transaction'))return $this->db->inTransaction()?1:0;if(str_contains($sql,'JSON_CONTAINS_PATH')&&$this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return 0;return $this->db->query($this->sql($sql))->fetchColumn();}
    public function get_row($sql,$format=null){$this->last_error='';$row=$this->db->query($this->sql($sql))->fetch(PDO::FETCH_ASSOC);return $row===false?null:($format===ARRAY_A?$row:(object)$row);}
    public function get_results($sql,$format=null){$this->last_error='';return $this->db->query($this->sql($sql))->fetchAll(PDO::FETCH_ASSOC);}
    public function get_col($sql){return $this->db->query($this->sql($sql))->fetchAll(PDO::FETCH_COLUMN);}
    public function update($table,$values,$where){$sets=[];$clauses=[];$args=[];foreach($values as $k=>$v){$sets[]="`$k`=?";$args[]=$v;}foreach($where as $k=>$v){$clauses[]="`$k`=?";$args[]=$v;}$q=$this->db->prepare("UPDATE `$table` SET ".implode(',',$sets).' WHERE '.implode(' AND ',$clauses));$q->execute($args);return $q->rowCount();}
}
$wpdb=new ReviewWPDB($db,$prefix);$caps=true;$auditFail=false;$validationFail=false;$updated=0;
function current_user_can(...$args){return $GLOBALS['caps'];}
function is_user_logged_in(){return true;}
function get_current_user_id(){return 11;}
function check_admin_referer(...$args){throw new RuntimeException('fixture-invalid-nonce');}
function add_action(...$args){}
function add_filter(...$args){}
function remove_filter(...$args){}
function clean_post_cache(...$args){}
function absint($v){return abs((int)$v);}
function sanitize_key($v){return $v;}
function wp_strip_all_tags($v){return strip_tags((string)$v);}
function wp_kses_post($v){return $v;}
function __($v,...$args){return $v;}
function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function esc_attr($v){return esc_html($v);}
function get_post($id){global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d",$id));}
function get_post_status($id){return get_post($id)->post_status??null;}
function get_post_meta($id,$key,$single=true){global $wpdb;return $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",$id,$key))?:'';}
function get_permalink($id){return 'https://badaround.it/eventi/fixture-'.$id.'/';}
function wp_update_post($data,$error=false){global $wpdb;$GLOBALS['updated']++;return $wpdb->update($wpdb->posts,['post_content'=>$data['post_content']],['ID'=>$data['ID']])===1?$data['ID']:new WP_Error('failed');}
function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error {public function __construct(public string $code,...$args){}}
class BadAround_Report_Repository {
    public function event_id_for_report($id){global $wpdb;return $wpdb->get_var($wpdb->prepare("SELECT event_id FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id));}
    public function payload_hash_for_report($id){global $wpdb;return $wpdb->get_var($wpdb->prepare("SELECT payload_hash FROM {$wpdb->prefix}ba_reports WHERE id=%d",$id));}
}
class BadAround_Audit_Log {
    public static function record($kind,$event,$action,$context,$reason,$id){global $db,$prefix;if($GLOBALS['auditFail'])return false;$q=$db->prepare("INSERT INTO {$prefix}ba_audit_log VALUES (?,?,?)");$q->execute([$action,$id,$event]);return $q->rowCount();}
}
class ReviewPublicationProbe extends BadAround_Publication_Service {
    public function validate_public_projection($event,$recovery=false,$candidate=null){$GLOBALS['candidate']=$candidate;return $GLOBALS['validationFail']?new WP_Error('denied'):true;}
}
function ok(bool $value,string $name):void{if(!$value)throw new RuntimeException('FAIL '.$name);echo 'PASS '.$name.PHP_EOL;}
$root=str_repeat('r',64);$identity='identity_12345678';$other='identity_87654321';$now=time()-10;
try {
    $q=$db->prepare("INSERT INTO {$prefix}ba_identities VALUES (?,?,NULL)");$q->execute([$identity,$now]);$q->execute([$other,$now]);
    $db->exec("INSERT INTO {$prefix}posts VALUES (801,'ba_evento','publish','Evento di prova','Descrizione pubblica originale'),(802,'ba_evento','pending','Da moderare','Descrizione in attesa')");
    foreach([[801,'_ba_moderation_status','published'],[802,'_ba_moderation_status','new'],[801,'_ba_primary_report_id','701'],[802,'_ba_primary_report_id','702']] as $meta){$q=$db->prepare("INSERT INTO {$prefix}postmeta VALUES (?,?,?)");$q->execute($meta);}
    foreach([[701,801],[702,802]] as [$report,$event]){$q=$db->prepare("INSERT INTO {$prefix}ba_reports VALUES (?,?,NULL,?,'{}','Nome','Privato','private@example.invalid','0612345678','Via privata 99','AB123CD')");$q->execute([$report,$event,str_repeat('a',64)]);}
    $ownership=new BadAround_Identity_Mysql_Ownership_Store($db,$prefix.'ba_identity_ownership');$ownership->grant_creator($identity,'report',701,$now);$ownership->grant_creator($identity,'report',702,$now);
    $sessions=new BadAround_Identity_Sessions(new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),hash_hmac('sha256','BA-ID-01H:sessions',$root,true));
    $token=$sessions->issue($identity,time());$_COOKIE['__Host-ba_identity']=$token;
    $csrf=BadAround_Identity_Csrf_Policy::issue($token,hash_hmac('sha256','BA-ID-01H:csrf',$root,true));
    $review=new BadAround_Identity_Community_Review($db,$prefix,$root,static fn()=>new ReviewPublicationProbe());$review->register_hooks();
    $empty=BadAround_Identity_Community_Review::render_comments(801);ok(str_contains($empty,'Non ci sono ancora commenti approvati.')&&str_contains($empty,'Commenti approvati'),'empty event retains an explicit discussion section');
    foreach(['handle_link','handle_decision'] as $handler) {
        try {$review->$handler();throw new RuntimeException('Nonce was not checked');}
        catch(RuntimeException $error){ok($error->getMessage()==='fixture-invalid-nonce','administrator action rejects invalid nonce before mutation');}
    }
    $inbox=new BadAround_Identity_Moderation_Inbox($db,$prefix.'ba_identity_moderation',hash_hmac('sha256','BA-ID-01H:moderation-encryption',$root,true));
    $make=function(string $kind,int $resource,array $intent)use($inbox,$identity,$now):string{$id=bin2hex(random_bytes(16));$inbox->submit($id,$kind,$identity,$resource,$intent,$now);return $id;};
    $comment=BadAround_Identity_Comment_Moderation_Policy::propose($identity,801,'Testo originale','anonymous',null,$now);
    $id=$make('comment',801,$comment);
    ok(!$review->decide($id,0,'approved','Testo sicuro','',''),'missing verified WP association denies moderation');
    $binding=new BadAround_Identity_Mysql_WP_Actor_Binding($db,$prefix.'ba_identity_wp_actors');$binding->grant_verified(11,$identity,$now);
    ok($review->decide($id,0,'approved','Testo approvato <b>sicuro</b>','',''),'bound moderator approves reviewed text');
    ok(!$review->decide($id,0,'approved','Duplicato','',''),'replayed approval cannot apply twice');
    $row=$inbox->load_private($id);ok($row['intent']['body']==='Testo originale'&&$row['intent']['public_projection']['body']==='Testo approvato sicuro','original private text retained separately from projection');
    $html=BadAround_Identity_Community_Review::render_comments(801);ok(str_contains($html,'Testo approvato sicuro')&&!str_contains($html,$identity)&&!str_contains($html,'private@example.invalid'),'public renderer exposes approved text and no private identity');
    ok($review->parent($id,801)!==null&&$review->parent($id,802)===null,'reply parent must belong to the same event');
    $reply=BadAround_Identity_Comment_Moderation_Policy::propose($identity,801,'Risposta di prova','anonymous',$review->parent($id,801),$now);
    $replyId=$make('comment',801,$reply);ok($review->decide($replyId,0,'approved','Risposta approvata','','')&&$review->parent($replyId,801)===null,'reply is approved but cannot become a nested parent');
    foreach(['Email private@example.invalid','Numero 3331234567','Targa AB123CD','Via privata 99','Nome Privato'] as $text){$next=$make('comment',801,$comment);ok(!$review->decide($next,0,'approved',$text,'',''),'private data rejected before publication');}
    $next=$make('comment',801,$comment);$auditFail=true;ok(!$review->decide($next,0,'approved','Testo con audit guasto','','')&&$inbox->load_private($next)['state']==='pending','audit failure rolls back approval');$auditFail=false;
    ok(!$review->decide($next,0,'rejected','','','')&&$review->decide($next,0,'rejected','','','Motivo di prova'),'rejection requires a reason and records a terminal decision');
    $next=$make('comment',801,$comment);$caps=false;ok(!$review->decide($next,0,'approved','Testo sicuro','',''),'missing capability prevents writes');$caps=true;
    $snapshot=BadAround_Identity_Community_Review::snapshot(802,true);ok(is_array($snapshot)&&BadAround_Identity_Community_Review::snapshot(802)===null,'pending snapshot is available only to the private revision path');
    $revision=BadAround_Identity_Revision_Request_Policy::propose($identity,702,'Proposta da revisionare','Correzione',true,$snapshot['digest'],$now);
    $revisionId=$make('revision',702,$revision);$auditFail=true;
    ok(!$review->decide($revisionId,0,'approved','Descrizione da annullare','','')&&get_post(802)->post_content==='Descrizione in attesa'
        &&$inbox->load_private($revisionId)['state']==='pending','audit failure rolls back both event content and revision state');$auditFail=false;
    ok($review->decide($revisionId,0,'approved','Descrizione corretta dal moderatore','',''),'pending revision applies through Publication Service');
    ok(get_post(802)->post_content==='Descrizione corretta dal moderatore'&&get_post_status(802)==='pending'&&get_post_meta(802,'_ba_moderation_status')==='new','pending revision never publishes or changes B2 state');
    $staleId=$make('revision',702,$revision);ok(!$review->decide($staleId,0,'approved','Sovrascrittura obsoleta','',''),'stale public snapshot cannot overwrite later changes');
    $snapshot=BadAround_Identity_Community_Review::snapshot(802,true);$revision['original_snapshot_digest']=$snapshot['digest'];$next=$make('revision',702,$revision);
    $db->exec("UPDATE {$prefix}ba_identity_ownership SET revoked_at=1 WHERE resource_id=702");ok(!$review->decide($next,0,'approved','Modifica non autorizzata','',''),'revoked creator ownership prevents applying revision');
    $before=$updated;$service=new ReviewPublicationProbe();$snapshot=BadAround_Identity_Community_Review::snapshot(801);
    ok(is_wp_error($service->apply_identity_revision(801,'Descrizione senza transazione',$snapshot['digest']))&&$updated===$before,'Publication Service refuses an unowned transaction');
    $publicRevision=BadAround_Identity_Revision_Request_Policy::propose($identity,701,'Proposta pubblica sicura','Correzione',true,$snapshot['digest'],$now);
    $next=$make('revision',701,$publicRevision);$validationFail=true;
    ok(!$review->decide($next,0,'approved','Descrizione candidata respinta','','')&&$updated===$before,'candidate validation runs before the public write');$validationFail=false;
    ok($review->decide($next,0,'approved','Nuova descrizione pubblica sicura','','')&&$candidate==='Nuova descrizione pubblica sicura','published candidate goes through existing publication validation');
    $mine=$review->my_reports($token,$csrf,'https://badaround.it');ok(is_array($mine)&&count($mine)===1&&$mine[0]['event_id']===801,'private report list excludes revoked bindings');
    $otherToken=$sessions->issue($other,time());$otherCsrf=BadAround_Identity_Csrf_Policy::issue($otherToken,hash_hmac('sha256','BA-ID-01H:csrf',$root,true));
    ok($review->my_reports($otherToken,$otherCsrf,'https://badaround.it')===[],'another account cannot enumerate reports');
    ok($review->my_reports($token,$csrf,'https://elsewhere.invalid')===null,'cross-origin report listing denied');
    $_SERVER['REMOTE_ADDR']='203.0.113.43';ok(!$review->decide($id,1,'rejected','','','Altro motivo')&&BadAround_Identity_Community_Review::render_comments(801)==='','QA network restriction remains enforced');
    echo 'ALL_COMMUNITY_REVIEW_TESTS_PASS'.PHP_EOL;
} finally {foreach(array_reverse($tables) as $table)$db->exec('DROP TABLE IF EXISTS `'.$table.'`');}
