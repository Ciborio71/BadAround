<?php
/** Private QA moderation, explicit actor association and approved-only read model. */
declare(strict_types=1);
require_once __DIR__.'/wordpress-composition.php';

final class BadAround_Identity_Community_Review {
    private static ?self $runtime=null;
    private static ?object $transactionDb=null;
    private BadAround_Identity_Sessions $sessions;
    private BadAround_Identity_Mysql_Active_Status $active;
    private BadAround_Identity_Mysql_WP_Actor_Binding $binding;
    private BadAround_Identity_Moderation_Inbox $inbox;
    private string $key;
    private string $table;
    private PDO $db;
    private string $prefix;
    private string $csrfKey;
    private Closure $publisher;

    public function __construct(PDO $db,string $prefix,string $root,?callable $publisher=null) {
        if (!preg_match('/^[A-Za-z0-9_]{1,48}$/D',$prefix) || strlen($root)<32)
            throw new InvalidArgumentException('Invalid review configuration');
        $this->key=hash_hmac('sha256','BA-ID-01H:moderation-encryption',$root,true);
        $this->table=$prefix.'ba_identity_moderation';
        $this->db=$db;$this->prefix=$prefix;
        $this->csrfKey=hash_hmac('sha256','BA-ID-01H:csrf',$root,true);
        $this->publisher=$publisher===null?static fn()=>new BadAround_Publication_Service():Closure::fromCallable($publisher);
        $this->inbox=new BadAround_Identity_Moderation_Inbox($db,$this->table,$this->key);
        $this->sessions=new BadAround_Identity_Sessions(
            new BadAround_Identity_Mysql_Session_Store($db,$prefix.'ba_identity_sessions'),
            hash_hmac('sha256','BA-ID-01H:sessions',$root,true));
        $this->active=new BadAround_Identity_Mysql_Active_Status($db,$prefix.'ba_identities');
        $this->binding=new BadAround_Identity_Mysql_WP_Actor_Binding($db,$prefix.'ba_identity_wp_actors');
    }
    public static function enabled():bool {
        return class_exists('BadAround_Identity_Private_Access',false)
            && BadAround_Identity_Private_Access::authorized()
            && defined('BADAROUND_IDENTITY_COMMUNITY_ENABLED') && BADAROUND_IDENTITY_COMMUNITY_ENABLED===true
            && BadAround_Identity_WordPress_Privacy_Notice::runtime_version()!==null;
    }
    public function register_hooks():void {
        if (!self::enabled())return;
        self::$runtime=$this;
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_post_ba_identity_link_actor',[$this,'handle_link']);
        add_action('admin_post_ba_identity_review',[$this,'handle_decision']);
    }
    private function actor():?string {
        if (!self::enabled() || !is_user_logged_in() || !current_user_can('ba_moderate_contributions')
            || !current_user_can('ba_view_private_reports'))return null;
        $token=$_COOKIE['__Host-ba_identity']??null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        try {
            $actor=$this->sessions->identity_for($token,time());
            return is_string($actor)&&$this->active->is_active_verified($actor)?$actor:null;
        }catch(Throwable $error){return null;}
    }
    private function moderator():?string {
        $actor=$this->actor();
        return $actor!==null && $this->binding->matches_current_user((int)get_current_user_id(),$actor)?$actor:null;
    }
    public static function is_moderator():bool {
        return self::$runtime!==null && self::$runtime->moderator()!==null;
    }
    public static function owns_transaction():bool {
        global $wpdb;
        return self::$transactionDb!==null && self::$transactionDb===$wpdb;
    }
    public function menu():void {
        if (self::enabled())add_submenu_page('edit.php?post_type=ba_evento','Proposte Identity',
            'Proposte Identity','ba_moderate_contributions','ba-identity-review',[$this,'page']);
    }
    private function redirect(bool $ok):void {
        wp_safe_redirect(admin_url('edit.php?post_type=ba_evento&page=ba-identity-review&result='.($ok?'ok':'failed')));
        exit;
    }
    public function handle_link():void {
        check_admin_referer('ba_identity_link_actor');
        $actor=$this->actor();
        // Association is explicit and proves both independently authenticated sessions.
        // No email comparison and no request-supplied account or identity identifiers.
        $ok=$actor!==null && current_user_can('manage_options')
            && ($this->binding->matches_current_user((int)get_current_user_id(),$actor)
                || $this->binding->grant_verified((int)get_current_user_id(),$actor,time()));
        $this->redirect($ok);
    }
    public function handle_decision():void {
        $id=is_string($_POST['intent_id']??null)?wp_unslash($_POST['intent_id']):'';
        check_admin_referer('ba_identity_review_'.$id);
        $version=$_POST['version']??null;
        $ok=is_string($version)&&preg_match('/^[0-9]{1,9}$/D',$version)
            && $this->decide($id,(int)$version,
                is_string($_POST['decision']??null)?wp_unslash($_POST['decision']):'',
                is_string($_POST['public_text']??null)?wp_unslash($_POST['public_text']):'',
                is_string($_POST['public_alias']??null)?wp_unslash($_POST['public_alias']):'',
                is_string($_POST['reason']??null)?wp_unslash($_POST['reason']):'');
        $this->redirect((bool)$ok);
    }
    public function page():void {
        if (!self::enabled() || !current_user_can('ba_moderate_contributions')
            || !current_user_can('ba_view_private_reports'))wp_die('Accesso non disponibile.');
        nocache_headers();
        echo '<div class="wrap ba-identity-review"><h1>Proposte Identity</h1>';
        if (isset($_GET['result']))echo '<p role="status">'.esc_html($_GET['result']==='ok'
            ?'Operazione completata.':'Operazione non completata. Controlla accesso, versione e dati pubblicabili.').'</p>';
        if ($this->moderator()===null) {
            echo '<p>Accedi anche a BadAround Identity sulla stessa connessione. Associa esplicitamente il tuo account amministrativo prima di moderare.</p>';
            if ($this->actor()!==null && current_user_can('manage_options')) {
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('ba_identity_link_actor');
                echo '<input type="hidden" name="action" value="ba_identity_link_actor"><button class="button button-primary">Associa i miei due accessi verificati</button></form>';
            }
            echo '</div>';return;
        }
        global $wpdb;
        $page=isset($_GET['paged'])&&is_string($_GET['paged'])?max(1,min(10000,(int)$_GET['paged'])):1;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT intent_id FROM `{$this->table}` WHERE state='pending' ORDER BY created_at,intent_id LIMIT 20 OFFSET %d",($page-1)*20));
        if (!$ids)echo '<p>Nessuna proposta in attesa.</p>';
        foreach($ids as $id) {
            try {$row=$this->inbox->load_private($id);}catch(Throwable $e){$row=null;}
            if (!$row)continue;
            $intent=$row['intent'];$kind=$row['kind'];
            echo '<section class="card"><h2>'.esc_html($kind==='comment'?'Commento':'Modifica della segnalazione').' — '.esc_html($id).'</h2>';
            echo '<p>Risorsa #'.(int)$row['resource_id'].' · versione '.(int)$row['version'].'</p>';
            echo '<p>Motivo originale: '.esc_html((string)($intent['reason']??'')).'</p>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('ba_identity_review_'.$id);
            echo '<input type="hidden" name="action" value="ba_identity_review"><input type="hidden" name="intent_id" value="'.esc_attr($id).'">';
            echo '<input type="hidden" name="version" value="'.(int)$row['version'].'">';
            echo '<p><label>Testo pubblico revisionato<br><textarea name="public_text" rows="6" cols="80" maxlength="5000">'.esc_textarea((string)($intent['body']??$intent['proposed_description']??'')).'</textarea></label></p>';
            if ($kind==='comment' && ($intent['display_mode']??'')==='pseudonym')
                echo '<p><label>Pseudonimo pubblico <input name="public_alias" maxlength="80" value="'.esc_attr((string)($intent['public_alias']??'')).'"></label></p>';
            echo '<p><label>Nota di moderazione (obbligatoria per il rifiuto)<br><textarea name="reason" maxlength="500" rows="2" cols="80"></textarea></label></p>';
            echo '<button class="button button-primary" name="decision" value="approved">Approva e applica</button> ';
            echo '<button class="button" name="decision" value="rejected">Rifiuta</button></form></section>';
        }
        echo '<p><a href="'.esc_url(admin_url('edit.php?post_type=ba_evento&page=ba-identity-review&paged='.($page+1))).'">Pagina successiva</a></p></div>';
    }
    public function parent(string $id,int $event):?array {
        if (!self::enabled())return null;
        try {$row=$this->inbox->load_private($id);}catch(Throwable $error){return null;}
        if (!$row || $row['kind']!=='comment' || $row['state']!=='approved'
            || (int)$row['resource_id']!==$event || !isset($row['intent']['public_projection'])
            || ($row['intent']['event_id']??null)!==$event
            || ($row['intent']['parent_id']??null)!==null)return null;
        return ['comment_id'=>$id,'event_id'=>$event,'moderation_status'=>'approved','parent_id'=>null];
    }
    public static function snapshot(int $event,bool $allowPending=false):?array {
        if ($event<=0 || !class_exists('BadAround_Report_Repository'))return null;
        $post=get_post($event);
        if (!$post || $post->post_type!=='ba_evento')return null;
        $status=get_post_meta($event,'_ba_moderation_status',true);
        $public=$post->post_status==='publish' && $status==='published';
        $pending=$allowPending && in_array($post->post_status,['draft','pending'],true)
            && in_array($status,['new','in_review','needs_information'],true);
        if (!$public&&!$pending)return null;
        $raw=get_post_meta($event,'_ba_primary_report_id',true);
        if ((!is_int($raw)&&!is_string($raw)) || !preg_match('/^[1-9][0-9]{0,17}$/D',(string)$raw))return null;
        $repo=new BadAround_Report_Repository();$report=(int)$raw;
        if ((int)$repo->event_id_for_report($report)!==$event)return null;
        $hash=$repo->payload_hash_for_report($report);
        if (!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash))return null;
        return ['report_id'=>$report,'digest'=>hash('sha256',$hash."\0".(string)$post->post_content)];
    }
    public function my_reports(string $token,string $csrf,string $origin):?array {
        if (!self::enabled() || !BadAround_Identity_Csrf_Policy::origin_allowed($origin,'https://badaround.it')
            || !BadAround_Identity_Csrf_Policy::verify($csrf,$token,$this->csrfKey))return null;
        try {
            $identity=$this->sessions->identity_for($token,time());
            if (!is_string($identity)||!$this->active->is_active_verified($identity))return null;
            $q=$this->db->prepare("SELECT p.ID,p.post_title,p.post_status FROM {$this->prefix}ba_identity_ownership o
                JOIN {$this->prefix}ba_reports r ON r.id=o.resource_id
                JOIN {$this->prefix}posts p ON p.ID=r.event_id
                WHERE o.identity_id=:identity AND o.resource_kind='report' AND o.relation='creator'
                AND o.revoked_at IS NULL AND r.deleted_at IS NULL AND p.post_type='ba_evento'
                AND p.post_status IN ('draft','pending','publish') ORDER BY o.verified_binding_at DESC,p.ID DESC LIMIT 50");
            $q->execute([':identity'=>$identity]);$safe=[];
            foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $event=(int)$row['ID'];
                $safe[]=['event_id'=>$event,'title'=>(string)$row['post_title'],
                    'status'=>$row['post_status']==='publish'?'published':'pending_moderation',
                    'url'=>$row['post_status']==='publish'?(string)get_permalink($event):null,
                    'can_propose_revision'=>self::snapshot($event,true)!==null];
            }
            return $safe;
        }catch(Throwable $error){return null;}
    }
    private function safe_text(int $event,string $text):bool {
        global $wpdb;
        if (preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u',$text)
            || preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',$text)
            || preg_match('/(?<!\d)\+?\d(?:[\s().\-]*\d){8,14}(?!\d)/u',$text))return false;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT author_name,author_surname,author_email,author_phone,exact_address,full_plate FROM {$wpdb->prefix}ba_reports WHERE event_id=%d AND deleted_at IS NULL LIMIT 1001",$event),ARRAY_A);
        if (!is_array($rows)||!$rows||count($rows)>1000||$wpdb->last_error!=='')return false;
        $lower=mb_strtolower($text,'UTF-8');
        foreach($rows as $r) {
            foreach([trim(($r['author_name']??'').' '.($r['author_surname']??'')),
                $r['author_email']??'',$r['author_phone']??'',$r['exact_address']??''] as $private) {
                $private=trim(mb_strtolower((string)$private,'UTF-8'));
                if (mb_strlen($private,'UTF-8')>=5 && str_contains($lower,$private))return false;
            }
            $plate=preg_replace('/[^A-Z0-9]/','',strtoupper((string)($r['full_plate']??'')));
            if (strlen($plate)>=5 && str_contains(preg_replace('/[^A-Z0-9]/','',strtoupper($text)),$plate))return false;
        }
        return true;
    }
    public function decide(string $id,int $version,string $decision,string $text,string $alias,string $reason):bool {
        global $wpdb;
        $moderator=$this->moderator();
        if ($moderator===null || !preg_match('/^[a-f0-9]{32}$/D',$id) || $version<0
            || !in_array($decision,['approved','rejected'],true) || mb_strlen($reason,'UTF-8')>500
            || ($decision==='rejected' && trim($reason)===''))return false;
        foreach([$this->table,$wpdb->posts,$wpdb->postmeta,$wpdb->prefix.'ba_reports',$wpdb->prefix.'ba_audit_log'] as $table)
            if ($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table))!=='InnoDB')return false;
        if (self::$transactionDb!==null || $wpdb->query('START TRANSACTION')===false)return false;
        self::$transactionDb=$wpdb;
        $done=false;$event=0;
        $cacheSuspended=function_exists('wp_suspend_cache_addition')?wp_suspend_cache_addition():null;
        if ($cacheSuspended!==null)wp_suspend_cache_addition(true);
        try {
            $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM `{$this->table}` WHERE intent_id=%s FOR UPDATE",$id),ARRAY_A);
            if (!$row || $row['state']!=='pending' || (int)$row['version']!==$version
                || (int)$row['created_at']>=time())return false;
            $plain=openssl_decrypt($row['payload'],'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$row['nonce'],$row['tag']);
            if (!is_string($plain))return false;
            $intent=json_decode($plain,true,32,JSON_THROW_ON_ERROR);
            if (!is_array($intent)||($intent['identity_id']??null)!==$row['identity_id'])return false;
            if ($decision==='approved' && !$this->active->is_active_verified($row['identity_id']))return false;
            $event=$row['kind']==='comment'?(int)$row['resource_id']:(int)(new BadAround_Report_Repository())->event_id_for_report((int)$row['resource_id']);
            if ($decision==='approved') {
                $post=$wpdb->get_row($wpdb->prepare("SELECT ID,post_type,post_status FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$event));
                clean_post_cache($event);
                if (!$post||$post->post_type!=='ba_evento')return false;
                if ($row['kind']==='comment' && ($post->post_status!=='publish'
                    || get_post_meta($event,'_ba_moderation_status',true)!=='published'))return false;
                $text=trim(wp_strip_all_tags($text));$alias=trim(wp_strip_all_tags($alias));
                if (!$this->safe_text($event,$text."\n".$alias))return false;
                if ($row['kind']==='comment') {
                    if (($intent['event_id']??null)!==$event || mb_strlen($text,'UTF-8')<3 || mb_strlen($text,'UTF-8')>2500)return false;
                    if (($intent['parent_id']??null)!==null && (!is_string($intent['parent_id']) || $this->parent($intent['parent_id'],$event)===null))return false;
                    $mode=$intent['display_mode']??'anonymous';
                    if (!in_array($mode,['anonymous','pseudonym'],true))return false;
                    $label=BadAround_Identity_Community_Policy::public_identity('','',$mode,$alias);
                    $intent['public_projection']=['body'=>$text,'label'=>$label,'parent_id'=>$intent['parent_id']??null];
                } elseif ($row['kind']==='revision') {
                    if (($intent['report_id']??null)!==(int)$row['resource_id'])return false;
                    $owner=$wpdb->get_row($wpdb->prepare("SELECT identity_id,relation,revoked_at FROM {$wpdb->prefix}ba_identity_ownership WHERE resource_kind='report' AND resource_id=%d FOR UPDATE",(int)$row['resource_id']),ARRAY_A);
                    if (!$owner || $owner['identity_id']!==$row['identity_id'] || $owner['relation']!=='creator' || $owner['revoked_at']!==null)return false;
                    $snapshot=self::snapshot($event,true);
                    if (!$snapshot || !hash_equals($snapshot['digest'],(string)($intent['original_snapshot_digest']??'')))return false;
                    $publisher=($this->publisher)();
                    if (!$publisher instanceof BadAround_Publication_Service)return false;
                    $result=$publisher->apply_identity_revision($event,$text,$snapshot['digest']);
                    if (is_wp_error($result)||$result!==true)return false;
                    $intent['applied_public_digest']=hash('sha256',$text);
                } else return false;
            }
            $intent['review_note']=trim($reason);
            $nonce=random_bytes(12);$tag='';
            $cipher=openssl_encrypt(json_encode($intent,JSON_THROW_ON_ERROR),'aes-256-gcm',$this->key,OPENSSL_RAW_DATA,$nonce,$tag);
            if (!is_string($cipher))return false;
            $changed=$wpdb->update($this->table,['payload'=>$cipher,'nonce'=>$nonce,'tag'=>$tag,
                'state'=>$decision,'version'=>$version+1,'moderator_id'=>$moderator,'moderated_at'=>time()],
                ['intent_id'=>$id,'state'=>'pending','version'=>$version]);
            if ($changed!==1 || BadAround_Audit_Log::record('event',$event,'identity_'.$row['kind'].'_'.$decision,'identity_review',null,$id)!==1)return false;
            if ($wpdb->query('COMMIT')===false)return false;
            $done=true;return true;
        } catch(Throwable $error){return false;}
        finally {
            if(!$done)$wpdb->query('ROLLBACK');
            self::$transactionDb=null;
            if($event>0)clean_post_cache($event);
            if($cacheSuspended!==null)wp_suspend_cache_addition($cacheSuspended);
        }
    }
    public static function render_comments(int $event):string {
        if (!self::enabled()||self::$runtime===null||self::snapshot($event)===null)return '';
        global $wpdb;$self=self::$runtime;
        $ids=$wpdb->get_col($wpdb->prepare("SELECT intent_id FROM `{$self->table}` WHERE kind='comment' AND state='approved' AND resource_id=%d ORDER BY created_at DESC,intent_id DESC LIMIT 50",$event));
        $out='<section class="ba-community-discussion" aria-label="Commenti approvati"><h3>Commenti approvati</h3>';
        if (!$ids)$out.='<p>Non ci sono ancora commenti approvati.</p>';
        foreach(array_reverse($ids??[]) as $id) {
            try {$row=$self->inbox->load_private($id);}catch(Throwable $e){continue;}
            if (!$row || $row['state']!=='approved'||$row['kind']!=='comment'||(int)$row['resource_id']!==$event)continue;
            if (($row['intent']['event_id']??null)!==$event)continue;
            $p=$row['intent']['public_projection']??null;
            if (!is_array($p)||!is_string($p['body']??null)||!is_string($p['label']??null)
                || mb_strlen($p['body'],'UTF-8')>2500 || mb_strlen($p['label'],'UTF-8')>80)continue;
            $parent=$p['parent_id']??null;
            if ($parent!==null && (!is_string($parent)||$self->parent($parent,$event)===null))continue;
            $out.='<article id="ba-comment-'.esc_attr($id).'" class="ba-community-comment'.($parent!==null?' ba-community-comment--reply':'').'">';
            $out.='<h4>'.esc_html($p['label']).'</h4>';
            if ($parent!==null)$out.='<p><a href="#ba-comment-'.esc_attr($parent).'">Risposta a un commento</a></p>';
            $out.='<p>'.nl2br(esc_html($p['body'])).'</p>';
            if ($parent===null)$out.='<button type="button" data-ba-reply="'.esc_attr($id).'">Rispondi</button>';
            $out.='</article>';
        }
        return $out.'</section>';
    }
}
