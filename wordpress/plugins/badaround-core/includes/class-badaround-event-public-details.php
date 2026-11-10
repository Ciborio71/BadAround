<?php
/** Moderated public-only snapshot for detail pages. Never read reports while rendering. */
if (!defined('ABSPATH')) { exit; }
require_once __DIR__.'/class-badaround-event-dates.php';
final class BadAround_Event_Public_Details {
    const META='_ba_detail_public_v1';
    private static function value($data,$path){foreach(explode('.',$path) as $part){if(!is_array($data)||!array_key_exists($part,$data))return null;$data=$data[$part];}return $data;}
    private static function seal($id,$data){return hash_hmac('sha256',$id.'|'.wp_json_encode($data),wp_salt('auth').'|ba-public-detail/v1');}
    public static function view($id){
        $stored=get_post_meta($id,self::META,true);
        if(!is_array($stored)||!isset($stored['data'],$stored['seal'])||!is_array($stored['data'])||!is_string($stored['seal'])
            ||!hash_equals(self::seal($id,$stored['data']),$stored['seal']))return [];
        $data=$stored['data'];
        // A later privacy downgrade hides an earlier address immediately.
        $geo=BadAround_Public_Geo_Projection::public_view($id);
        if(!is_array($geo)||($geo['mode']??'')!=='exact_point')$data['address']='';
        return $data;
    }
    public static function address_authorized($id,$address){$p=self::view($id);return is_string($address)&&$address!==''&&($p['address']??'')===$address;}
    private static function permitted($id){return get_post_type($id)==='ba_evento'&&current_user_can('ba_moderate_events')&&current_user_can('ba_view_private_reports')&&current_user_can('publish_ba_eventi')&&current_user_can('edit_post',$id);}
    private static function report($id){
        global $wpdb;$primary=(int)get_post_meta($id,'_ba_primary_report_id',true);
        if(!$primary)return null;
        return $wpdb->get_row($wpdb->prepare("SELECT created_at,content_original,exact_address,author_name,author_surname,author_email,author_phone,full_plate FROM {$wpdb->prefix}ba_reports WHERE id=%d AND event_id=%d AND deleted_at IS NULL",$primary,$id));
    }
    private static function safe($value,$report){
        $value=trim(sanitize_text_field((string)$value));if($value===''||strlen($value)>2000)return '';
        foreach([trim($report->author_name.' '.$report->author_surname),$report->author_email,$report->author_phone,$report->full_plate] as $secret){
            $secret=trim((string)$secret);if(strlen($secret)>=5&&stripos($value,$secret)!==false)return '';
        }
        return $value;
    }
    private static function publication_requested($canonical){
        return self::value($canonical,'consents.public_details_version')==='public-details-v1'
            && self::value($canonical,'consents.truthfulness')===true
            && self::value($canonical,'consents.publication_rules')===true
            && self::value($canonical,'consents.terms')===true
            && self::value($canonical,'consents.privacy')===true;
    }
    public static function candidate($id,$reviewed_text=false){
        $r=self::report($id);$source=$r?json_decode($r->content_original,true):null;$c=$source['canonical']??null;
        if(!is_array($c))return new WP_Error('ba_detail_source','Report nativo non disponibile.',['status'=>409]);
        $definitions=BadAround_Report_Schema::fields();$facts=[];
        $labels=['vehicle.type'=>'Tipo di veicolo','animal.type'=>'Tipo di animale','object.status'=>'Stato dell’oggetto','damage.status'=>'Danni',
            'location.place_type'=>'Tipo di luogo','time.duration'=>'Durata','time.frequency'=>'Frequenza'];
        if($reviewed_text)$labels+=['vehicle.distinctive_features'=>'Segni distintivi','animal.breed'=>'Razza','animal.name'=>'Nome dell’animale','animal.appearance'=>'Aspetto e segni distintivi','object.description'=>'Descrizione dell’oggetto','damage.description'=>'Descrizione dei danni'];
        foreach($labels as $path=>$label){
            $value=self::value($c,$path);$def=$definitions[$path]??null;
            if(!$def||!is_scalar($value)||$value==='')continue;
            if(!$reviewed_text && $def['privacy']!==BadAround_Report_Schema::PRIVACY_PUBLIC)continue;
            if(isset($def['constraints']['enum'])&&!in_array($value,$def['constraints']['enum'],true))continue;
            $value=self::safe($value,$r);if($value===''||($r->exact_address!==''&&stripos($value,$r->exact_address)!==false))continue;
            $facts[$path]=['label'=>$label,'value'=>$value];
        }
        $time=['reference_date'=>substr((string)($r->created_at??''),0,10)];foreach(['mode','date','knowledge','exact_time','range_start','range_end','approximate_period'] as $key){
            $value=self::value($c,'time.'.$key);$def=$definitions['time.'.$key]??null;
            if(!$def||!is_string($value)||$value==='')continue;
            if(isset($def['constraints']['enum'])&&!in_array($value,$def['constraints']['enum'],true))continue;
            if(in_array($key,['date'],true)&&!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value))continue;
            if(in_array($key,['exact_time','range_start','range_end'],true)&&!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value))continue;
            $time[$key]=self::safe($value,$r);
        }
        $publication_requested=self::publication_requested($c);
        $plate='';
        if($publication_requested && self::value($c,'event.category')==='vehicle'
            && in_array(self::value($c,'vehicle.plate_knowledge'),['full','partial'],true)){
            $raw=self::value($c,'vehicle.plate_raw');
            if(is_string($raw)&&preg_match('/^[A-Z0-9?]{1,32}$/D',$raw)&&$raw===(string)$r->full_plate){
                $identity_only=clone $r;$identity_only->full_plate='';$plate=self::safe($raw,$identity_only);
            }
        }
        return ['plate'=>$plate,'publication_requested'=>$publication_requested,
            'address_requested'=>$publication_requested && BadAround_Public_Geo_Contract::request_mode($c['location']??[])==='exact_point',
            'facts'=>$facts,'time'=>$time,'seeking_witnesses'=>self::value($c,'witness.status')==='seeking',
            'address'=>'','source_hash'=>hash('sha256',$r->content_original),'address_available'=>trim((string)$r->exact_address)!==''];
    }
    public static function approve($id,$expected,$include_address=false,$attested=false,$reviewed_text=false){
        if(!self::permitted($id)||get_post_type($id)!=='ba_evento')return new WP_Error('ba_detail_forbidden','Operazione non autorizzata.',['status'=>403]);
        if(get_post_status($id)!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published')return new WP_Error('ba_detail_not_published','Pubblicare prima tramite il flusso di moderazione.',['status'=>409]);
        $p=self::candidate($id,$reviewed_text);if(is_wp_error($p))return $p;
        if(!is_string($expected)||!hash_equals($p['source_hash'],$expected))return new WP_Error('ba_detail_conflict','Il report è cambiato. Ricarica la revisione.',['status'=>409]);
        // The new final declaration is an explicit request, not a retroactive assumption.
        $geo=BadAround_Public_Geo_Projection::public_view($id);
        if(!empty($p['address_requested']) && is_array($geo) && ($geo['mode']??'')==='exact_point' && $p['address_available']){
            $include_address=true;$attested=true;
        }
        if($include_address){
            if(!$attested||!is_array($geo)||($geo['mode']??'')!=='exact_point')return new WP_Error('ba_detail_address_authorization','Indirizzo pubblicabile solo con posizione esatta e autorizzazione verificata.',['status'=>409]);
            $r=self::report($id);$p['address']=self::safe($r->exact_address,$r);
            if($p['address']==='')return new WP_Error('ba_detail_address_invalid','Indirizzo non pubblicabile.',['status'=>409]);
        }
        if($p['publication_requested'])$p['publication_policy']='public-details-v1';
        unset($p['address_available'],$p['publication_requested'],$p['address_requested']);
        if($p['plate']==='')unset($p['plate']);
        $stored=['data'=>$p,'seal'=>self::seal($id,$p)];
        $result=(new BadAround_Publication_Service())->apply_public_details($id,$stored,$p['source_hash']);
        return is_wp_error($result)?$result:self::view($id);
    }

    /** Build only; Publication Service owns the transaction, validation and audit. */
    public static function publication_snapshot($id){
        $r=self::report($id);$source=$r?json_decode($r->content_original,true):null;
        if(!self::publication_requested($source['canonical']??[]))return null;
        if(!self::permitted($id))return new WP_Error('ba_detail_forbidden','Operazione non autorizzata.');
        $p=self::candidate($id);if(is_wp_error($p))return $p;
        $geo=BadAround_Public_Geo_Projection::public_view($id);
        if(!empty($p['address_requested']) && is_array($geo) && ($geo['mode']??'')==='exact_point' && $p['address_available']){
            $p['address']=self::safe($r->exact_address,$r);
            if($p['address']==='')return new WP_Error('ba_detail_address_invalid','Verificare l’indirizzo destinato alla pubblicazione.');
        }
        $p['publication_policy']='public-details-v1';
        unset($p['address_available'],$p['publication_requested'],$p['address_requested']);
        if($p['plate']==='')unset($p['plate']);
        return ['data'=>$p,'seal'=>self::seal($id,$p)];
    }
    public static function published($id){
        // New declarations are projected atomically during B3, not at shutdown.
        if((self::view($id)['publication_policy']??'')==='public-details-v1')return;
        $p=self::candidate($id);if(is_wp_error($p))return;
        // B3 is still the moderation gate. Versioned reporter declarations may project address and plate into sealed, public-only fields.
        self::approve($id,$p['source_hash']);
    }
    private static function render_preview($id){
        if(get_post_status($id)!=='publish')return '';
        global $wp_query,$post;$saved_query=$wp_query;$saved_post=$post;$buffer_level=ob_get_level();
        ob_start();
        try{$wp_query=new WP_Query(['post_type'=>'ba_evento','p'=>$id,'post_status'=>'publish']);get_template_part('template-parts/single-event-layout');return ob_get_clean();}
        finally{while(ob_get_level()>$buffer_level)ob_end_clean();$wp_query=$saved_query;$post=$saved_post;}
    }
    public static function register_hooks(){
        BadAround_Event_Dates::register_hooks();
        add_filter('badaround_event_contribution_heading',static function($heading,$id){$p=self::view($id);if(!empty($p['seeking_witnesses'])&&!in_array(get_post_meta($id,'_ba_event_status',true),['resolved','closed','archived','expired'],true)){ $seo=BadAround_Event_SEO::project($id);if(!is_wp_error($seo))return $seo['witness_heading'];}return $heading;},10,2);
        add_action('badaround_event_published',static function($id){add_action('shutdown',static function()use($id){self::published($id);});});
        add_action('add_meta_boxes_ba_evento',static function(){add_meta_box('ba-public-details','Dettagli pubblici del wizard',[__CLASS__,'box'],'ba_evento','normal','default');});
        add_action('admin_post_ba_public_details',static function(){
            $id=absint($_POST['event_id']??0);check_admin_referer('ba_public_details_'.$id);
            $result=self::approve($id,sanitize_text_field(wp_unslash($_POST['source_hash']??'')),isset($_POST['include_address']),isset($_POST['address_attested']),isset($_POST['reviewed_text']));
            if(is_wp_error($result))wp_die(esc_html($result->get_error_message()));
            wp_safe_redirect(get_edit_post_link($id,'raw'));exit;
        });
        add_action('rest_api_init',static function(){register_rest_route('badaround-ops/v1','/event-details/(?P<id>\d+)',[
            'methods'=>['GET','POST'],'permission_callback'=>static function($r){return self::permitted((int)$r['id']);},
            'callback'=>static function($r){
                $id=(int)$r['id'];$body=$r->get_json_params();
                $data=$r->get_method()==='GET'?['candidate'=>self::candidate($id,true),'public'=>self::view($id)]:self::approve($id,$body['source_hash']??null,($body['include_address']??false)===true,($body['address_attested']??false)===true,($body['reviewed_text']??false)===true);
                if(is_wp_error($data))return $data;
                if($r->get_method()==='GET'&&$r->get_param('render')==='1')$data['public_html']=self::render_preview($id);
                $response=new WP_REST_Response($data,200);$response->header('Cache-Control','private, no-store');return $response;
            }
        ]);});
    }
    public static function box($post){
        if(!self::permitted($post->ID))return;$p=self::candidate($post->ID,true);if(is_wp_error($p)){echo '<p>Dettagli nativi non disponibili.</p>';return;}
        echo '<p>La scheda pubblica include i dati strutturati pertinenti e l’eventuale ricerca testimoni. I nuovi report con dichiarazione di pubblicazione mostrano la targa e, con Punto esatto, l’indirizzo completo. I report precedenti e i testi descrittivi richiedono una revisione esplicita.</p>';
        foreach($p['facts'] as $fact)echo '<p><strong>'.esc_html($fact['label']).':</strong> '.esc_html($fact['value']).'</p>';
        echo '<p>'.($p['seeking_witnesses']?'Si cercano testimoni.':'Nessuna ricerca testimoni indicata.').'</p>';
        if(get_post_status($post->ID)!=='publish'){echo '<p>Pubblica prima l’evento; i dati strutturati saranno proiettati automaticamente.</p>';return;}
        // Separate form avoids nesting a form inside the Classic Editor post form.
        $form='ba-detail-form-'.$post->ID;
        echo '<input type="hidden" form="'.esc_attr($form).'" name="action" value="ba_public_details"><input type="hidden" form="'.esc_attr($form).'" name="event_id" value="'.(int)$post->ID.'"><input type="hidden" form="'.esc_attr($form).'" name="source_hash" value="'.esc_attr($p['source_hash']).'"><input type="hidden" form="'.esc_attr($form).'" name="_wpnonce" value="'.esc_attr(wp_create_nonce('ba_public_details_'.$post->ID)).'">';
        echo '<p><label><input form="'.esc_attr($form).'" type="checkbox" name="reviewed_text"> Ho revisionato i dettagli descrittivi sopra e ne autorizzo la pubblicazione.</label></p>';
        if($p['address_available'])echo '<p><label><input form="'.esc_attr($form).'" type="checkbox" name="include_address"> Pubblica l’indirizzo completo del report, compreso il civico.</label><br><label><input form="'.esc_attr($form).'" type="checkbox" name="address_attested"> Ho verificato che l’autore abbia destinato questo indirizzo alla pubblicazione. Disponibile solo con posizione pubblica esatta.</label></p>';
        echo '<button form="'.esc_attr($form).'" class="button" type="submit">Approva i dettagli pubblici</button>';
        add_action('admin_footer',static function()use($form){echo '<form id="'.esc_attr($form).'" method="post" action="'.esc_url(admin_url('admin-post.php')).'"></form>';});
    }
}
