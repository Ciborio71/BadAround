<?php
/** SEO-EVENTS URL v1.1 / editorial copy v1.3: public fields only; no private report reads. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
final class BadAround_Event_SEO {
    const VERSION = '1.1'; // URL/activation contract stays compatible with published events.
    const COPY_VERSION = '1.4';
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
                    'copy_version'=>self::COPY_VERSION,'sections'=>self::section_headings($id),'current_url'=>get_permalink($id),'frozen'=>(bool)get_post_meta($id,'_ba_seo_frozen',true),'preview'=>$seo));
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
        foreach(self::section_headings($post->ID) as $key=>$heading){
            echo '<p><strong>'.esc_html(in_array($key,array('address','occurrence','map'),true)?'H3':'H2').'</strong><br>'.esc_html($heading).'</p>';
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
    public static function project( $id, $headings_only = false ) {
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
        $color=self::text(get_post_meta($id,'_ba_vehicle_color',true));
        if ( 0 === strpos((string)$key, 'vehicle_') ) {
            $make = self::text(get_post_meta($id,'_ba_vehicle_make',true));
            $model = self::text(get_post_meta($id,'_ba_vehicle_model',true));
            $detail = trim(ucfirst($make).' '.ucfirst($model));
            if ( $detail && 'vehicle_stolen' === $key ) { $subject = $detail.($color?', colore '.lcfirst($color):'').' — veicolo segnalato rubato'; }
            elseif ( $detail ) { $subject = $detail.($color?', colore '.lcfirst($color):'').' — '.lcfirst($subject); }
        }
        // Only approved, sealed facts may enrich animal/object copy or a street label.
        $public_details=class_exists('BadAround_Event_Public_Details')?BadAround_Event_Public_Details::view($id):array();
        $zone='';$zone_depth=-1;
        foreach($places as $term){
            $chain=get_ancestors($term->term_id,'ba_territorio','taxonomy');
            if(get_post_status($id)==='publish'&&count($chain)>=3&&count($chain)>$zone_depth&&self::text($term->name)!==$municipality){
                $zone=self::text($term->name);$zone_depth=count($chain);
            }
        }
        $approved_zone=self::text($public_details['neighborhood']??'');
        if($approved_zone!=='')$zone=$approved_zone;
        $street=self::public_street($public_details['address']??'');
        $area=$street!==''?$street:($zone!==''?'zona '.$zone:'');
        $where=' a '.$municipality.($area!==''?', '.($street!==''?'in ':'').$area:'').($street!==''&&$approved_zone!==''?', zona '.$approved_zone:'');
        $animal_types=array('dog'=>'cane','cat'=>'gatto','bird'=>'uccello','rabbit'=>'coniglio','livestock'=>'animale da allevamento','wild'=>'animale selvatico','reptile'=>'rettile');
        $animal=$animal_types[$public_details['facts']['animal.type']['value']??'']??'';
        $object_status=$public_details['facts']['object.status']['value']??'';
        $copy=self::editorial_copy($key,$template,$detail,$animal,$object_status,$municipality);
        if($animal!=='' && str_starts_with((string)$key,'animal_')){
            $animal_event=array('animal_missing'=>'smarrito','animal_sighted_or_found'=>'avvistato o trovato','animal_injured'=>'ferito o in difficoltà','animal_risk'=>'coinvolto in una situazione di rischio');
            $subject=ucfirst($animal).' '.($animal_event[$key]??'segnalato');
        }
        if(isset($copy['subject']))$subject=$copy['subject'];
        // Only the reviewed, sealed public enum may enrich editorial copy.
        $place=$public_details['facts']['location.place_type']['value']??'';
        $property_places=array('private_home'=>'in abitazione privata','condominium'=>'in condominio','commercial_premises'=>'in un negozio o attività commerciale','office'=>'in un ufficio','warehouse'=>'in un magazzino','garage_outbuilding'=>'in un garage o pertinenza','public_building'=>'in un edificio pubblico');
        $place_phrase=str_starts_with((string)$key,'property_')?($property_places[$place]??''):'';
        if($place_phrase!==''){
            $subject=preg_replace('/ in un immobile/u','',$subject);
            $subject=preg_replace('/ (segnalato|segnalata)$/u',' '.$place_phrase.' $1',$subject);
            $copy['title']=str_replace(' in un immobile','',$copy['title']).' '.$place_phrase;
            $copy['description']=str_replace(' in un immobile','',$copy['description']).' '.$place_phrase;
            $copy['sections']['facts']='Dettagli dell’evento '.$place_phrase;
            $copy['sections']['photos']='Foto dei luoghi e degli eventuali danni '.$place_phrase;
        }

        $h1=str_replace(' — ',' | ',$subject).$where;
        $state=get_post_meta($id,'_ba_event_status',true);
        $state_labels=array('resolved'=>'Risolto','closed'=>'Chiuso','archived'=>'Archiviato','expired'=>'Scaduto');
        if(isset($state_labels[$state]))$h1.=' | '.$state_labels[$state];
        $title_base=$copy['title'];
        $with_color=$title_base.($detail&&$color?', colore '.lcfirst($color):'');
        $state_prefix=isset($state_labels[$state])?$state_labels[$state].': ':'';
        $title=self::choose_title(array(
            $state_prefix.$with_color.$where,
            $state_prefix.$title_base.$where,
            $state_prefix.$title_base.' a '.$municipality.($zone!==''?', zona '.$zone:''),
            $state_prefix.$with_color.' a '.$municipality,
            $state_prefix.$title_base.' a '.$municipality,
        ));
        $intro=$copy['description'].$where.'.';
        if($detail&&$color)$intro.=' Colore indicato: '.lcfirst($color).'.';
        if(isset($state_labels[$state]))$intro.=' Stato: '.lcfirst($state_labels[$state]).'.';
        elseif(!empty($public_details['seeking_witnesses']))$intro.=' Si cercano testimoni o informazioni utili.';
        $date=(string)get_post_meta($id,'_ba_occurred_date',true);
        if(preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)){
            $parts=explode('-',$date);
            if(checkdate((int)$parts[1],(int)$parts[2],(int)$parts[0]))$intro.=' Data indicata: '.$parts[2].'/'.$parts[1].'/'.$parts[0].'.';
        }
        $sections=$copy['sections'];
        $sections+=array('address'=>'Indirizzo pubblico dell’evento','occurrence'=>'Data e periodo indicati nella segnalazione','map'=>'Mappa della zona di '.$municipality);
        if($approved_zone!==''){
            $sections['location']='Dove è avvenuto l’evento: zona '.$approved_zone.', '.$municipality;
            $sections['map']='Mappa della zona '.$approved_zone.' a '.$municipality;
        }
        if($headings_only)return $sections;
        return array('h1'=>$h1,'title'=>$title,'description'=>self::limit_text($intro.' Leggi i dettagli e gli aggiornamenti su BadAround.',190),
            'intro'=>$intro,'heading'=>$sections['facts'],'prose_heading'=>$sections['prose'],'location_heading'=>$sections['location'],
            'witness_heading'=>$detail?'Hai visto questo '.$detail.'?':($animal?'Hai informazioni su questo '.$animal.'?':'Hai informazioni su questa segnalazione?'),
            // This exact formula remains frozen, independently of editorial copy.
            'slug'=>sanitize_title($template[1].'-'.$municipality.($detail?'-'.$detail:'')));
    }

    private static function length($value){return preg_match_all('/./us',$value);}
    private static function limit_text($value,$max){
        if(self::length($value)<=$max)return $value;
        preg_match_all('/./us',$value,$chars);$cut=implode('',array_slice($chars[0],0,$max-1));
        $words=preg_replace('/\s+\S*$/u','',$cut);
        return rtrim($words!==''?$words:$cut,' ,;:.').'…';
    }
    private static function choose_title($candidates){
        // Editorial budget, not a claim about a Google character limit. Never cut a URL or H1.
        foreach($candidates as $candidate){if(self::length($candidate.' | BadAround')<=75)return $candidate.' | BadAround';}
        return self::limit_text(end($candidates),77).' | BadAround';
    }
    private static function public_street($address){
        // Input is already the signed public address; never inspect the private report.
        $first=trim(preg_split('/[,;]/u',(string)$address)[0]);
        if(!preg_match('/^(?:via|viale|piazza|piazzale|corso|largo|vicolo|strada|lungomare|lungotevere|circonvallazione)\s+/ui',$first))return '';
        $street=preg_replace('/\s+(?:n[.°]?\s*)?\d+[a-z]?(?:\s*[\/-]\s*[a-z0-9]+)?\s*$/ui','',$first);
        // Ambiguous numeric street names/civic formats are omitted, never guessed.
        if(preg_match('/\d/u',$street)||self::length($street)>70)return '';
        return self::text($street);
    }
    private static function editorial_copy($key,$template,$detail,$animal,$object_status,$town){
        $topics=array(
            'vehicle_stolen'=>'Furto di un veicolo','vehicle_parked_damage'=>'Danni a un veicolo in sosta',
            'vehicle_hit_and_run'=>'Incidente con fuga','vehicle_parts_stolen'=>'Furto di parti o accessori',
            'vehicle_observed'=>'Veicolo sospetto o abbandonato','vehicle_other'=>'Segnalazione su un veicolo',
            'property_theft'=>'Furto in un immobile','property_attempted_breakin'=>'Tentato furto o scasso',
            'property_intrusion'=>'Accesso abusivo o intrusione','property_vandalism'=>'Vandalismo o danneggiamento',
            'property_observed_behavior'=>'Comportamento sospetto','property_other'=>'Segnalazione su un immobile',
            'civic_waste'=>'Rifiuti abbandonati','civic_surface_damage'=>'Buca o marciapiede dissestato',
            'civic_public_lighting'=>'Guasto all’illuminazione pubblica','civic_road_signage'=>'Segnaletica danneggiata',
            'civic_public_furniture_green'=>'Degrado di arredo urbano o verde','civic_other'=>'Disservizio o degrado urbano',
            'animal_missing'=>'Animale smarrito','animal_sighted_or_found'=>'Animale avvistato o trovato',
            'animal_injured'=>'Animale ferito o in difficoltà','animal_risk'=>'Situazione di rischio con un animale',
            'wallet_docs'=>'Documenti, portafoglio o borsa','keys'=>'Chiavi','electronics'=>'Dispositivo elettronico',
            'valuables'=>'Oggetto di valore o accessorio','other'=>'Oggetto personale',
            'hazard_falling_element'=>'Albero, ramo o elemento pericolante','hazard_road_disruption'=>'Strada interrotta o grave dissesto',
            'hazard_flood_or_ice'=>'Allagamento o ghiaccio','hazard_road_obstruction'=>'Ostacolo sulla carreggiata',
            'hazard_fire_or_smoke'=>'Fumo o incendio','hazard_other'=>'Situazione di pericolo',
        );
        $topic=$topics[$key]??$template[0];
        $vehicle=str_starts_with((string)$key,'vehicle_');$animal_event=str_starts_with((string)$key,'animal_');
        $object=in_array($key,array('wallet_docs','keys','electronics','valuables','other'),true);$status='';
        $title=$topic;$description='Segnalazione relativa a '.lcfirst($topic);
        $sections=array('photos'=>'Foto della segnalazione: '.lcfirst($topic),
            'facts'=>$template[2],'prose'=>'Che cosa è stato segnalato: '.lcfirst($topic),
            'location'=>'Luogo e periodo dell’evento a '.$town,'summary'=>'Riepilogo della segnalazione a '.$town,
            'comments'=>'Commenti e aggiornamenti su questa segnalazione');
        if($vehicle){
            if($detail)$title=$topic.': '.$detail;
            $description='Segnalazione di '.lcfirst($topic).($detail?', mezzo indicato: '.$detail:'');
            $sections['photos']=$detail?'Foto della segnalazione: '.$detail:'Foto del veicolo segnalato';
            $sections['facts']=$detail?'Caratteristiche e segni distintivi: '.$detail:'Caratteristiche del veicolo segnalato';
            $sections['prose']=array('vehicle_stolen'=>'Come è avvenuto il furto segnalato','vehicle_parked_damage'=>'Quali danni sono stati segnalati durante la sosta','vehicle_hit_and_run'=>'Dinamica dell’incidente e informazioni disponibili','vehicle_parts_stolen'=>'Quali parti o accessori risultano coinvolti','vehicle_observed'=>'Circostanze dell’avvistamento del veicolo','vehicle_other'=>'Che cosa è successo al veicolo')[$key];
            $sections['summary']='Informazioni utili'.($detail?' su '.$detail:' sul veicolo').' a '.$town;
            if($key==='vehicle_stolen'){
                $title=$detail?'Furto '.$detail:'Furto di un veicolo';
                $description=$detail?'Segnalato il furto di '.$detail:'Segnalato il furto di un veicolo';
                $sections['location']='Luogo e periodo del furto a '.$town;
                $sections['comments']='Commenti e aggiornamenti sul furto a '.$town;
            }else $sections['comments']='Contributi della community sul veicolo a '.$town;
        }elseif($animal_event){
            if($animal){$title=str_replace('Animale',ucfirst($animal),$topic);$description='Segnalazione: '.lcfirst($title);}
            $sections['photos']=$animal?'Foto dell’animale segnalato: '.$animal:'Foto per riconoscere l’animale';
            $sections['facts']='Aspetto e segni di riconoscimento'.($animal?' del '.$animal:' dell’animale');
            $sections['prose']=$key==='animal_missing'?'Circostanze dello smarrimento':'Circostanze e condizioni dell’animale segnalato';
            $sections['location']=$key==='animal_missing'?'Ultima posizione segnalata e periodo a '.$town:'Luogo e periodo dell’avvistamento a '.$town;
            $sections['comments']='Avvistamenti e aggiornamenti della community a '.$town;
        }elseif($object){
            $status=in_array($object_status,array('lost','found'),true)?($object_status==='lost'?'smarrimento':'ritrovamento'):'';
            $title=$status?ucfirst($status).': '.lcfirst($topic):'Segnalazione: '.lcfirst($topic);
            $description=$status?'Segnalazione di '.$status.' relativa a '.lcfirst($topic):'Informazioni su '.lcfirst($topic).' nella segnalazione';
            $sections['facts']='Caratteristiche per riconoscere l’oggetto';
            $sections['prose']=$status?($status==='smarrimento'?'Circostanze dello smarrimento':'Circostanze del ritrovamento'):'Informazioni disponibili sull’oggetto';
            $sections['comments']='Informazioni e aggiornamenti sull’oggetto segnalato';
        }elseif(str_starts_with((string)$key,'property_')){
            $sections['photos']='Foto dei luoghi e degli eventuali danni segnalati';
            $sections['prose']=$key==='property_theft'?'Circostanze del furto nell’immobile':($key==='property_attempted_breakin'?'Segni del tentativo di furto o scasso':'Che cosa è successo nell’immobile');
            $sections['facts']='Dettagli utili sull’immobile e sull’evento';
            $sections['comments']='Commenti della community sull’evento a '.$town;
        }elseif(str_starts_with((string)$key,'civic_')){
            $sections['photos']='Foto del problema segnalato: '.lcfirst($topic);
            $sections['prose']='Descrizione del problema nello spazio pubblico';
            $sections['facts']='Caratteristiche e condizioni segnalate';
            $sections['location']='Area interessata e periodo della segnalazione a '.$town;
            $sections['comments']='Aggiornamenti dalla zona di '.$town;
        }elseif(str_starts_with((string)$key,'hazard_')){
            $sections['photos']='Immagini della situazione segnalata';
            $sections['prose']='Che cosa è stato osservato: '.lcfirst($topic);
            $sections['facts']='Elementi utili per comprendere il pericolo segnalato';
            $sections['location']='Zona interessata e periodo a '.$town;
            $sections['comments']='Aggiornamenti della community sulla situazione';
        }
        $result=array('title'=>$title,'description'=>$description,'sections'=>$sections);
        if($object&&$status!=='')$result['subject']=$topic.' | Segnalazione di '.$status;
        return $result;
    }

    public static function section_headings($id){
        $p=self::project($id,true);
        return is_wp_error($p)?array('photos'=>'Foto della segnalazione','facts'=>'Dettagli utili','prose'=>'Che cosa è successo','location'=>'Luogo e periodo della segnalazione','summary'=>'Informazioni evento','comments'=>'Commenti della community','address'=>'Indirizzo della segnalazione','occurrence'=>'Data o periodo dell’evento','map'=>'Mappa della segnalazione'):$p;
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
