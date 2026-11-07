<?php
/** Distinct server receipt and user-reported occurrence dates. */
if(!defined('ABSPATH')){exit;}
final class BadAround_Event_Dates {
    const META='_ba_public_report_receipt_v1';
    private static function permitted($id){return current_user_can('ba_moderate_events')&&current_user_can('publish_ba_eventi')&&current_user_can('ba_view_private_reports')&&current_user_can('edit_post',$id);}
    private static function seal($id,$data){return hash_hmac('sha256',$id.'|'.wp_json_encode($data),wp_salt('auth').'|ba-report-receipt/v1');}
    public static function parse_receipt($value){
        if(!is_string($value))return null;
        $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('UTC'));
        return $d&&$d->format('Y-m-d H:i:s')===$value?$d:null;
    }
    public static function view($id){
        $s=get_post_meta($id,self::META,true);
        if(!is_array($s)||!is_array($s['data']??null)||!is_string($s['seal']??null)||!hash_equals(self::seal($id,$s['data']),$s['seal']))return [];
        $d=$s['data'];if(($d['report_id']??0)!==(int)get_post_meta($id,'_ba_primary_report_id',true)||!self::parse_receipt($d['received_utc']??null))return [];
        unset($d['report_id']);return $d;
    }
    public static function capture($id){
        global $wpdb;$id=(int)$id;if(!self::permitted($id))return new WP_Error('ba_receipt_forbidden','Operazione non autorizzata.',['status'=>403]);
        foreach([$wpdb->posts,$wpdb->postmeta,$wpdb->prefix.'ba_reports',$wpdb->prefix.'ba_audit_log'] as $t){
            if($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$t))!=='InnoDB')return new WP_Error('ba_receipt_transaction','Archivio non verificabile.',['status'=>409]);
        }
        if(false===$wpdb->query('START TRANSACTION'))return new WP_Error('ba_receipt_storage','Salvataggio non riuscito.',['status'=>500]);
        $done=false;
        try{
            $post=$wpdb->get_row($wpdb->prepare("SELECT ID,post_type,post_status FROM {$wpdb->posts} WHERE ID=%d FOR UPDATE",$id));clean_post_cache($id);
            if(!$post||$post->post_type!=='ba_evento'||$post->post_status!=='publish'||get_post_meta($id,'_ba_moderation_status',true)!=='published')return new WP_Error('ba_receipt_state','Evento non pubblicato.',['status'=>409]);
            $primary=(int)get_post_meta($id,'_ba_primary_report_id',true);
            $row=$wpdb->get_row($wpdb->prepare("SELECT id,created_at,source_type FROM {$wpdb->prefix}ba_reports WHERE id=%d AND event_id=%d AND deleted_at IS NULL FOR UPDATE",$primary,$id));
            if(!$row||!self::parse_receipt($row->created_at))return new WP_Error('ba_receipt_missing','Data originale della segnalazione non disponibile.',['status'=>409]);
            $kind=in_array($row->source_type,['native','wpforms'],true)?'submitted':($row->source_type==='external'?'imported':'received');
            $data=['report_id'=>$primary,'received_utc'=>$row->created_at,'kind'=>$kind];$stored=['data'=>$data,'seal'=>self::seal($id,$data)];
            if(get_post_meta($id,self::META,true)!==$stored){
                update_post_meta($id,self::META,$stored);
                if(get_post_meta($id,self::META,true)!==$stored||false===BadAround_Audit_Log::record('event',$id,'report_receipt_projected','publication','initial_report_timestamp'))return new WP_Error('ba_receipt_storage','Salvataggio non riuscito.',['status'=>500]);
            }
            if(false===$wpdb->query('COMMIT'))return new WP_Error('ba_receipt_storage','Salvataggio non riuscito.',['status'=>500]);
            $done=true;return self::view($id);
        }catch(Throwable $e){return new WP_Error('ba_receipt_storage','Salvataggio non riuscito.',['status'=>500]);}
        finally{if(!$done)$wpdb->query('ROLLBACK');clean_post_cache($id);}
    }
    public static function receipt_label($receipt){
        $date=self::parse_receipt($receipt['received_utc']??null);if(!$date)return [];
        $prefix=['submitted'=>'Segnalazione inviata il','imported'=>'Segnalazione acquisita il','received'=>'Segnalazione ricevuta il'][$receipt['kind']??'received']??'Segnalazione ricevuta il';
        return ['prefix'=>$prefix,'text'=>wp_date('d/m/Y \\a\\l\\l\\e H:i',$date->getTimestamp(),wp_timezone()),'datetime'=>$date->format('Y-m-d\\TH:i:s\\Z'),'date'=>wp_date('d/m/Y',$date->getTimestamp(),wp_timezone())];
    }
    private static function date($value){$d=is_string($value)?DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('UTC')):false;return $d&&$d->format('Y-m-d')===$value?$d->format('d/m/Y'):'';}
    private static function clock($v){return is_string($v)&&preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D',$v)?substr($v,0,5):'';}
    public static function occurrence_label($id,$details=[]){
        $t=$details['time']??[];$date=self::date((string)get_post_meta($id,'_ba_occurred_date',true))?:self::date($t['date']??'');
        $clock=self::clock((string)get_post_meta($id,'_ba_occurred_time',true));if(!$clock&&($t['knowledge']??'')==='exact')$clock=self::clock($t['exact_time']??'');
        $start=self::clock($t['range_start']??'');$end=self::clock($t['range_end']??'');
        if(($t['knowledge']??'')==='range'&&$start&&$end)return ($date?$date.' · ':'').'tra le '.$start.' e le '.$end;
        if($date)return $date.($clock?' · ore '.$clock:'');
        $period=['today'=>'Nella giornata della segnalazione','yesterday'=>'Il giorno precedente la segnalazione','last_7_days'=>'Nei 7 giorni precedenti la segnalazione','last_30_days'=>'Nei 30 giorni precedenti la segnalazione','one_to_three_months'=>'Da uno a tre mesi prima della segnalazione','over_three_months'=>'Oltre tre mesi prima della segnalazione'];
        $label=$period[$t['approximate_period']??'']??'';
        if(!$label&&($t['mode']??'')==='ongoing')$label='In corso al momento della segnalazione';
        if(!$label&&($t['mode']??'')==='repeated')$label='Evento ricorrente secondo la segnalazione';
        if(!$label)return 'Non specificata dall’autore';
        $receipt=self::receipt_label(self::view($id));$reference=$receipt['date']??self::date($t['reference_date']??'');
        return $label.($reference?' (riferimento: '.$reference.')':'');
    }
    public static function register_hooks(){
        add_action('badaround_event_published',static function($id){add_action('shutdown',static function()use($id){self::capture($id);});});
        add_action('rest_api_init',static function(){register_rest_route('badaround-ops/v1','/event-receipt/(?P<id>\d+)',[
            'methods'=>['GET','POST'],'permission_callback'=>static function($r){return self::permitted((int)$r['id']);},
            'callback'=>static function($r){$data=$r->get_method()==='POST'?self::capture((int)$r['id']):self::view((int)$r['id']);if(is_wp_error($data))return $data;$response=new WP_REST_Response($data,200);$response->header('Cache-Control','private, no-store');return $response;}
        ]);});
    }
}
