<?php
/** SEO-EVENTS v1.1: public fields only; no private report reads. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BadAround_Event_SEO {
    const VERSION = '1.1';
    const CONTRACT = '_ba_seo_contract';
    const SLUG = '_ba_seo_slug';
    const URL_ID = '_ba_public_url_id';

    public static function register_hooks() {
        add_filter( 'wp_insert_post_data', array( __CLASS__, 'preserve_slug' ), 999, 2 );
        add_action('rest_api_init', array(__CLASS__, 'register_preview'));
        add_action('add_meta_boxes_ba_evento', array(__CLASS__, 'register_preview_box'));
    }
    public static function register_preview() {
        register_rest_route('badaround-ops/v1','/event-seo/(?P<id>\\d+)',array(
            'methods'=>'GET',
            'permission_callback'=>static function($request) {
                $id=(int)$request['id'];
                return current_user_can('ba_moderate_events') && current_user_can('edit_post',$id) && 'ba_evento'===get_post_type($id);
            },
            'callback'=>static function($request) {
                $id=(int)$request['id'];$seo=self::project($id);
                if (is_wp_error($seo)) { return $seo; }
                $response=new WP_REST_Response(array('contract'=>self::VERSION,'active'=>self::active($id),
                    'current_url'=>get_permalink($id),'frozen'=>(bool)get_post_meta($id,'_ba_seo_frozen',true),'preview'=>$seo));
                $response->header('Cache-Control','private, no-store');return $response;
            },
        ));
    }
    public static function register_preview_box() {
        if (current_user_can('ba_moderate_events')) {
            add_meta_box('ba-event-seo','Anteprima SEO BadAround',array(__CLASS__,'preview_box'),'ba_evento','normal','default');
        }
    }
    public static function preview_box($post) {
        if (!current_user_can('ba_moderate_events') || !current_user_can('edit_post',$post->ID)) { return; }
        $seo=self::project($post->ID);
        if (is_wp_error($seo)) { echo '<p>Completa tipologia e Comune per vedere l’anteprima.</p>';return; }
        echo '<p>Generata dai dati pubblici salvati. Aggiorna la scheda per ricalcolarla. Il controllo privacy rimane obbligatorio alla pubblicazione.</p>';
        foreach(array('title'=>'Title','description'=>'Description','h1'=>'H1','heading'=>'Heading dei dettagli') as $key=>$label) {
            echo '<p><strong>'.esc_html($label).'</strong><br>'.esc_html($seo[$key]).'</p>';
        }
        $frozen=get_post_meta($post->ID,'_ba_seo_frozen',true);
        $published='publish'===$post->post_status || 'published'===get_post_meta($post->ID,'_ba_moderation_status',true);
        if ($frozen || $published) { echo '<p><strong>URL conservato</strong><br>'.esc_html(get_permalink($post->ID)).'</p>'; }
        else { echo '<p><strong>Slug proposto</strong><br>'.esc_html($seo['slug']).'</p><p>Assegnato alla pubblicazione; eventuali omonimie ricevono un identificativo stabile.</p>'; }
        if ($published && !self::active($post->ID)) { echo '<p>Evento precedente al contratto: questa anteprima non modifica title, contenuti o URL esistenti.</p>'; }
    }
    public static function preserve_slug( $data, $input ) {
        $id = (int) ( $input['ID'] ?? 0 );
        if ( $id && 'ba_evento' === ( $data['post_type'] ?? '' ) ) {
            $frozen = get_post_meta( $id, self::SLUG, true );
            if ( $frozen && get_post_meta($id,'_ba_seo_frozen',true) ) { $data['post_name'] = $frozen; }
        }
        return $data;
    }
    private static function text( $value ) {
        if ( ! is_scalar( $value ) ) { return ''; }
        $value = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $value ) ) );
        // Structured attributes are never a channel for contacts, plates or HTML.
        if ( strlen( $value ) > 180 || preg_match( '/[@<>]|https?:|\b[A-Z]{2}\s?\d{3}\s?[A-Z]{2}\b|\+?\d[\d .-]{7,}\d/ui', $value ) ) { return ''; }
        return $value;
    }
    public static function templates() {
        return array(
            'vehicle_stolen' => array('Veicolo segnalato rubato','veicolo-rubato','Caratteristiche del veicolo'),
            'vehicle_parked_damage' => array('Veicolo danneggiato durante la sosta','danno-veicolo-in-sosta','Danni segnalati'),
            'vehicle_hit_and_run' => array('Incidente con fuga segnalato','incidente-con-fuga','Dinamica e dettagli disponibili'),
            'vehicle_parts_stolen' => array('Furto di parti o accessori del veicolo segnalato','furto-parti-veicolo','Parti o accessori interessati'),
            'vehicle_observed' => array('Veicolo sospetto o abbandonato segnalato','veicolo-sospetto-abbandonato','Caratteristiche del veicolo'),
            'vehicle_other' => array('Evento su un veicolo segnalato','evento-veicolo','Caratteristiche del veicolo'),
            'civic_waste' => array('Rifiuti abbandonati segnalati','rifiuti-abbandonati','Tipologia dei rifiuti segnalati'),
            'civic_surface_damage' => array('Buca o marciapiede dissestato segnalato','buca-marciapiede-dissestato','Dissesto segnalato'),
            'civic_public_lighting' => array('Guasto all’illuminazione pubblica segnalato','guasto-illuminazione-pubblica','Disservizio segnalato'),
            'civic_road_signage' => array('Segnaletica danneggiata o illeggibile segnalata','segnaletica-danneggiata','Segnaletica interessata'),
            'civic_public_furniture_green' => array('Degrado di arredo urbano o verde segnalato','degrado-arredo-verde','Degrado segnalato'),
            'civic_other' => array('Disservizio o degrado segnalato','disservizio-degrado','Disservizio segnalato'),
            'property_theft' => array('Furto in un immobile segnalato','furto-immobile','Dinamica e dettagli disponibili'),
            'property_attempted_breakin' => array('Tentato furto o scasso segnalato','tentato-furto-scasso','Dinamica e dettagli disponibili'),
            'property_intrusion' => array('Accesso abusivo o intrusione segnalata','accesso-abusivo-intrusione','Dinamica e dettagli disponibili'),
            'property_vandalism' => array('Vandalismo o danneggiamento segnalato','vandalismo-danneggiamento','Danni segnalati'),
            'property_observed_behavior' => array('Comportamento sospetto segnalato','comportamento-sospetto','Dettagli della segnalazione'),
            'property_other' => array('Evento su un immobile segnalato','evento-immobile','Dettagli della segnalazione'),
            'animal_missing' => array('Animale smarrito segnalato','animale-smarrito','Come riconoscere l’animale'),
            'animal_sighted_or_found' => array('Animale vagante avvistato o trovato','animale-vagante','Come riconoscere l’animale'),
            'animal_injured' => array('Animale ferito o in difficoltà segnalato','animale-ferito','Condizioni segnalate'),
            'animal_risk' => array('Situazione di rischio con un animale segnalata','rischio-animale','Situazione segnalata'),
            'wallet_docs' => array('Segnalazione di documenti, portafoglio o borsa','documenti-portafoglio-borsa','Caratteristiche dell’oggetto'),
            'keys' => array('Segnalazione di chiavi','chiavi','Caratteristiche dell’oggetto'),
            'electronics' => array('Segnalazione di un dispositivo elettronico','dispositivo-elettronico','Caratteristiche dell’oggetto'),
            'valuables' => array('Segnalazione di un oggetto di valore o accessorio','oggetto-valore-accessorio','Caratteristiche dell’oggetto'),
            'other' => array('Segnalazione di un oggetto personale','oggetto-personale','Caratteristiche dell’oggetto'),
            'hazard_falling_element' => array('Albero, ramo o elemento pericolante segnalato','elemento-pericolante','Elemento e area interessata'),
            'hazard_road_disruption' => array('Strada interrotta o grave dissesto segnalato','strada-interrotta-dissesto','Tratto interessato'),
            'hazard_flood_or_ice' => array('Allagamento o ghiaccio insidioso segnalato','allagamento-ghiaccio','Area interessata'),
            'hazard_road_obstruction' => array('Ostacolo pericoloso sulla carreggiata segnalato','ostacolo-carreggiata','Ostacolo e tratto interessato'),
            'hazard_fire_or_smoke' => array('Fumo, incendio o situazione anomala segnalata','fumo-incendio','Situazione segnalata'),
            'hazard_other' => array('Pericolo segnalato','pericolo','Situazione segnalata'),
        );
    }
    /** Resolve only canonical public taxonomy and the same public vehicle fields as the detail. */
    public static function project( $id ) {
        $types = wp_get_post_terms( $id, 'ba_tipo_evento' );
        $places = wp_get_post_terms( $id, 'ba_territorio' );
        if ( is_wp_error( $types ) || is_wp_error( $places ) ) { return new WP_Error('ba_seo_taxonomy','Tassonomia pubblica non disponibile.'); }
        $key = ''; $fallback = ''; $depth = -1;
        foreach ( $types as $term ) {
            $d = count( get_ancestors( $term->term_id, 'ba_tipo_evento', 'taxonomy' ) );
            if ( $d > $depth ) { $key = get_term_meta( $term->term_id, '_ba_canonical_event_key', true ); $fallback = self::text($term->name); $depth=$d; }
        }
        $municipality = '';
        foreach ( $places as $term ) {
            $chain = array_merge( array_reverse( get_ancestors( $term->term_id, 'ba_territorio', 'taxonomy' ) ), array($term->term_id) );
            if ( count($chain) >= 3 ) {
                $t = get_term($chain[2], 'ba_territorio');
                if ( $t && ! is_wp_error($t) ) { $municipality = self::text($t->name); break; }
            }
        }
        if ( ! $municipality || ! $fallback ) { return new WP_Error('ba_seo_public_fields','Comune e tipologia pubblici obbligatori.'); }
        $templates = self::templates();
        $template = $templates[$key] ?? array($fallback, sanitize_title($fallback), 'Dettagli della segnalazione');
        $subject = $template[0]; $detail = '';
        if ( 0 === strpos((string)$key, 'vehicle_') ) {
            $make = self::text(get_post_meta($id,'_ba_vehicle_make',true));
            $model = self::text(get_post_meta($id,'_ba_vehicle_model',true));
            $detail = trim(ucfirst($make).' '.ucfirst($model));
            if ( $detail && 'vehicle_stolen' === $key ) { $subject = $detail.' — veicolo segnalato rubato'; }
            elseif ( $detail ) { $subject = $detail.' — '.lcfirst($subject); }
        }
        $h1 = $subject.' a '.$municipality;
        $state = get_post_meta($id,'_ba_event_status',true);
        $state_labels = array('resolved'=>'Risolto','closed'=>'Chiuso','archived'=>'Archiviato','expired'=>'Scaduto');
        if ( isset($state_labels[$state]) ) { $h1 .= ' — '.$state_labels[$state]; }
        $intro = $h1.'.';
        $date = (string)get_post_meta($id,'_ba_occurred_date',true);
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) ) {
            $parts=explode('-',$date);
            if ( checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]) ) { $intro .= ' Data indicata: '.$parts[2].'/'.$parts[1].'/'.$parts[0].'.'; }
        }
        return array('h1'=>$h1, 'title'=>$h1.' | BadAround',
            'description'=>$intro.' Consulta i dettagli pubblici della segnalazione su BadAround.',
            'intro'=>$intro, 'heading'=>$template[2],
            'slug'=>sanitize_title($template[1].'-'.$municipality.($detail?'-'.$detail:'')));
    }
    public static function freeze( $id ) {
        if (!self::active($id)) { return true; }
        update_post_meta($id,'_ba_seo_frozen',1);
        return get_post_meta($id,'_ba_seo_frozen',true) ? true : new WP_Error('ba_seo_freeze','URL non congelato.');
    }
    public static function active( $id ) { return self::VERSION === get_post_meta($id,self::CONTRACT,true); }

    /** Called only by the existing capability-checked publication service, before its privacy validator. */
    public static function prepare( $id ) {
        $post=get_post($id);
        if ( ! $post || 'ba_evento' !== $post->post_type ) { return new WP_Error('ba_seo_event','Evento non valido.'); }
        // No migration or metadata rewrites of previously published legacy pages.
        if ( 'publish' === $post->post_status || 'published' === get_post_meta($id,'_ba_moderation_status',true) ) { return true; }
        $p=self::project($id);
        if ( is_wp_error($p) ) { return $p; }
        global $wpdb;
        if ( '1' !== (string)$wpdb->get_var("SELECT GET_LOCK('badaround_event_seo_v11', 5)") ) { return new WP_Error('ba_seo_busy','Pubblicazione occupata, riprova.'); }
        try {
            $public_id=get_post_meta($id,self::URL_ID,true);
            if ( ! $public_id ) { $public_id=str_replace('-','',wp_generate_uuid4()); update_post_meta($id,self::URL_ID,$public_id); }
            if ( ! preg_match('/^[a-f0-9]{32}$/D',(string)$public_id) ) { return new WP_Error('ba_seo_id','Identificativo URL non valido.'); }
            $slug=get_post_meta($id,'_ba_seo_frozen',true) ? get_post_meta($id,self::SLUG,true) : '';
            if ( ! $slug ) {
                $base=substr($p['slug'],0,160);
                $slug=wp_unique_post_slug($base,$id,'publish','ba_evento',0);
                if ( $slug !== $base ) {
                    $base=rtrim($base,'-').'-'.substr($public_id,0,12);
                    $slug=wp_unique_post_slug($base,$id,'publish','ba_evento',0);
                    if ( $slug !== $base ) { return new WP_Error('ba_seo_collision','Conflitto URL, riprova.'); }
                }
            }
            $result=wp_update_post(array('ID'=>$id,'post_title'=>$p['h1'],'post_name'=>$slug),true);
            if ( is_wp_error($result) ) { return $result; }
            if ( get_post($id)->post_name !== $slug ) { return new WP_Error('ba_seo_slug_write','URL non salvato.'); }
            update_post_meta($id,self::SLUG,$slug);
            update_post_meta($id,self::CONTRACT,self::VERSION);
            if ( get_post_meta($id,self::SLUG,true)!==$slug || !self::active($id) || get_post_meta($id,self::URL_ID,true)!==$public_id ) { return new WP_Error('ba_seo_storage','Metadati SEO non salvati.'); }
            return true;
        } finally { $wpdb->get_var("SELECT RELEASE_LOCK('badaround_event_seo_v11')"); }
    }
}
