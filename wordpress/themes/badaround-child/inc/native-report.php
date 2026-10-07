<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** F1.6 QA gate: staging administrators only; the normal URL keeps its legacy form. */
function badaround_native_report_qa_enabled() {
	$request_host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
	return 'staging.badaround.it' === wp_parse_url( home_url( '/' ), PHP_URL_HOST )
		&& in_array( $request_host, array( 'staging.badaround.it', 'staging.badaround.it:443' ), true )
		&& current_user_can( 'manage_options' )
		&& isset( $_GET['native_report'] ) && is_string( $_GET['native_report'] ) && '1' === $_GET['native_report']
		&& class_exists( 'BadAround_Report_Schema' ) && class_exists( 'BadAround_Event_Taxonomy_Map' )
		&& class_exists( 'BadAround_Native_Report_REST_Controller' );
}

function badaround_native_report_qa_headers() {
	if ( is_page_template( 'page-segnala-evento.php' ) && badaround_native_report_qa_enabled() ) {
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive' );
	}
}
add_action( 'template_redirect', 'badaround_native_report_qa_headers' );

/** Presentation metadata only. Membership, conditions and constraints come from F1.2. */
function badaround_native_report_labels() {
	return array(
		'event.category' => 'Che cosa vuoi segnalare?', 'event.subtype' => 'Tipo di evento', 'event.other_type' => 'Specifica il tipo di evento',
		'reporter.relationship' => 'Come sei a conoscenza dell’evento?',
		'location.exact_address' => 'Indirizzo preciso, compreso il civico', 'location.exact_lat' => 'Latitudine (facoltativa)', 'location.exact_lng' => 'Longitudine (facoltativa)',
		'location.place_id' => 'Riferimento esterno del luogo', 'location.area_label' => 'Nome dell’area', 'location.region' => 'Regione', 'location.province' => 'Provincia',
		'location.municipality' => 'Comune', 'location.locality' => 'Località, frazione o quartiere', 'location.public_precision' => 'Come indicare la zona al pubblico?',
		'location.place_type' => 'Tipo di luogo', 'location.immediate_danger' => 'C’è un pericolo immediato?',
		'time.mode' => 'Quando è successo?', 'time.date' => 'Data dell’evento', 'time.knowledge' => 'Conosci l’orario?', 'time.exact_time' => 'Orario',
		'time.range_start' => 'Dalle ore', 'time.range_end' => 'Alle ore', 'time.approximate_period' => 'Periodo indicativo', 'time.duration' => 'Da quanto tempo?', 'time.frequency' => 'Con quale frequenza?',
		'vehicle.role' => 'Eri coinvolto o testimone?', 'vehicle.type' => 'Tipo di veicolo', 'vehicle.make' => 'Marca', 'vehicle.model' => 'Modello', 'vehicle.color' => 'Colore',
		'vehicle.plate_knowledge' => 'Conosci la targa?', 'vehicle.plate_raw' => 'Targa (riservata)', 'vehicle.distinctive_features' => 'Segni distintivi del veicolo',
		'animal.type' => 'Tipo di animale', 'animal.breed' => 'Razza', 'animal.name' => 'Nome dell’animale', 'animal.appearance' => 'Aspetto e segni distintivi',
		'object.description' => 'Descrizione dell’oggetto', 'object.status' => 'Oggetto smarrito o trovato?', 'object.owner_verification_detail' => 'Dettaglio riservato per verificare il proprietario',
		'property.tampered_entry_point' => 'Punto di accesso manomesso', 'property.stolen_item_categories' => 'Che cosa è stato rubato?', 'property.access_method' => 'Modalità di accesso', 'property.alarm_status' => 'Stato generale dell’allarme',
		'content.description' => 'Racconta che cosa è successo', 'damage.status' => 'Sono presenti danni?', 'damage.description' => 'Descrivi i danni', 'witness.status' => 'Ci sono testimoni?',
		'authority.status' => 'Hai informato un’autorità o un servizio?', 'authority.type' => 'Quale autorità o servizio?', 'authority.reference' => 'Riferimento della segnalazione (riservato)',
		'media.availability' => 'Disponi di immagini?',
		'reward.status' => 'Vuoi offrire una ricompensa?', 'reward.amount' => 'Importo in euro', 'reward.conditions' => 'Condizioni della ricompensa', 'reward.expires_on' => 'Scadenza',
		'reward.confirmed' => 'Confermo l’impegno relativo alla ricompensa indicata',
		'reporter.first_name' => 'Nome', 'reporter.last_name' => 'Cognome', 'reporter.email' => 'Email', 'reporter.phone' => 'Telefono (facoltativo)',
		'reporter.public_identity_mode' => 'Come vuoi essere indicato pubblicamente?', 'reporter.pseudonym' => 'Pseudonimo pubblico', 'reporter.contact_preference' => 'Preferenza di contatto',
		'consents.truthfulness' => 'Confermo che le informazioni sono veritiere per quanto a mia conoscenza',
		'consents.media_rights' => 'Confermo di avere i diritti su eventuali contenuti forniti',
		'consents.publication_rules' => 'Accetto le regole di pubblicazione e moderazione', 'consents.terms' => 'Accetto i termini e le condizioni',
		'consents.privacy' => 'Dichiaro di aver letto l’informativa privacy',
	);
}

function badaround_native_report_option_label( $path, $value ) {
	$context = array(
		'time.mode' => array( 'exact' => 'Conosco la data', 'approximate' => 'Periodo approssimativo', 'ongoing' => 'È ancora in corso', 'repeated' => 'Si ripete nel tempo', 'unknown' => 'Non lo so' ),
		'time.knowledge' => array( 'exact' => 'Orario preciso', 'range' => 'Fascia oraria', 'unknown' => 'Non conosco l’orario' ),
		'vehicle.plate_knowledge' => array( 'full' => 'Targa completa', 'partial' => 'Solo una parte', 'unknown' => 'Non la conosco', 'none' => 'Il veicolo non ha targa' ),
		'location.public_precision' => array( 'point' => 'Punto approssimato', 'street' => 'Via, senza civico', 'area' => 'Area o quartiere', 'municipality' => 'Solo il Comune' ),
		'reward.status' => array( 'none' => 'Nessuna ricompensa', 'fixed' => 'Importo definito', 'negotiable' => 'Da concordare' ),
		'reporter.contact_preference' => array( 'community' => 'Ricevere informazioni dalla community tramite BadAround', 'badaround_only' => 'Solo contatti da BadAround', 'none' => 'Nessun contatto' ),
		'media.availability' => array( 'yes' => 'Sì', 'no' => 'No', 'possible' => 'Potrei procurarle', 'surveillance' => 'Possibili riprese di videosorveglianza' ),
	);
	if ( isset( $context[ $path ][ $value ] ) ) { return $context[ $path ][ $value ]; }
	$labels = array(
		'yes' => 'Sì', 'no' => 'No', 'unsure' => 'Non sono sicuro', 'unknown' => 'Non lo so', 'other' => 'Altro', 'none' => 'Nessuno', 'not_applicable' => 'Non applicabile', 'prefer_not' => 'Preferisco non indicarlo',
		'directly_involved' => 'Sono coinvolto direttamente', 'witness' => 'Ho assistito come testimone', 'on_behalf' => 'Segnalo per un’altra persona', 'found_subject' => 'Ho trovato il soggetto', 'has_information' => 'Ho informazioni utili', 'territory_observer' => 'Ho osservato la situazione nella zona', 'involved' => 'Coinvolto direttamente',
		'road_sidewalk' => 'Strada o marciapiede', 'public_parking' => 'Parcheggio pubblico', 'private_parking' => 'Parcheggio privato', 'private_home' => 'Abitazione privata', 'condominium' => 'Condominio', 'commercial_premises' => 'Negozio o attività commerciale', 'office' => 'Ufficio', 'warehouse' => 'Magazzino', 'garage_outbuilding' => 'Garage o pertinenza', 'park_green' => 'Parco o area verde', 'public_transport' => 'Trasporto pubblico', 'public_building' => 'Edificio pubblico', 'industrial_work_area' => 'Area industriale o di lavoro', 'rural_natural' => 'Area rurale o naturale',
		'today' => 'Oggi', 'yesterday' => 'Ieri', 'last_7_days' => 'Ultimi 7 giorni', 'last_30_days' => 'Ultimi 30 giorni', 'one_to_three_months' => 'Da uno a tre mesi', 'over_three_months' => 'Oltre tre mesi', 'days' => 'Da alcuni giorni', 'weeks' => 'Da alcune settimane',
		'daily' => 'Ogni giorno', 'several_times_week' => 'Più volte a settimana', 'weekly' => 'Ogni settimana', 'several_times_month' => 'Più volte al mese', 'occasional' => 'Occasionalmente', 'indeterminate' => 'Frequenza non definita',
		'car' => 'Auto', 'motorcycle' => 'Moto', 'scooter' => 'Scooter', 'van' => 'Furgone', 'truck' => 'Camion', 'camper' => 'Camper', 'bus' => 'Autobus', 'bicycle' => 'Bicicletta', 'scooter_device' => 'Monopattino', 'agricultural_work' => 'Mezzo agricolo o da lavoro',
		'white' => 'Bianco', 'black' => 'Nero', 'gray' => 'Grigio', 'silver' => 'Argento', 'blue' => 'Blu', 'light_blue' => 'Azzurro', 'red' => 'Rosso', 'green' => 'Verde', 'yellow' => 'Giallo', 'orange' => 'Arancione', 'brown' => 'Marrone', 'beige' => 'Beige', 'purple' => 'Viola', 'pink' => 'Rosa', 'multicolor' => 'Multicolore',
		'dog' => 'Cane', 'cat' => 'Gatto', 'bird' => 'Uccello', 'rabbit' => 'Coniglio', 'livestock' => 'Animale da allevamento', 'wild' => 'Animale selvatico', 'reptile' => 'Rettile', 'lost' => 'Smarrito', 'found' => 'Trovato',
		'door' => 'Porta', 'lock' => 'Serratura', 'window' => 'Finestra', 'shutter' => 'Persiana', 'gate' => 'Cancello', 'garage_door' => 'Porta del garage', 'shop_window' => 'Vetrina',
		'cash' => 'Denaro', 'jewelry' => 'Gioielli', 'electronics' => 'Dispositivi elettronici', 'documents' => 'Documenti', 'keys' => 'Chiavi', 'vehicle' => 'Veicolo', 'tools_merchandise' => 'Attrezzi o merci',
		'door_forced' => 'Porta forzata', 'window_balcony' => 'Finestra o balcone', 'lock_tampered' => 'Serratura manomessa', 'garage_access' => 'Accesso dal garage', 'no_evident_signs' => 'Nessun segno evidente',
		'yes_triggered' => 'Presente e scattato', 'yes_not_triggered' => 'Presente ma non scattato', 'yes_unknown' => 'Presente, esito non noto', 'unverified' => 'Da verificare',
		'contacts_available' => 'Sì, con contatti disponibili', 'known_no_contacts' => 'Sì, senza contatti', 'seeking' => 'Cerco testimoni', 'not_yet' => 'Non ancora',
		'police' => 'Polizia', 'carabinieri' => 'Carabinieri', 'local_police' => 'Polizia locale', 'fire_service' => 'Vigili del fuoco', 'municipality' => 'Comune', 'public_service' => 'Servizio pubblico', 'veterinary_association' => 'Veterinario o associazione', 'insurance' => 'Assicurazione',
		'name_initial' => 'Nome e iniziale del cognome', 'first_name' => 'Solo nome', 'pseudonym' => 'Pseudonimo', 'anonymous' => 'Anonimo',
	);
	return isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
}

function badaround_native_report_step( $path ) {
	if ( 0 === strpos( $path, 'event.' ) || 'reporter.relationship' === $path ) { return 'event'; }
	if ( 0 === strpos( $path, 'location.' ) ) { return 'location'; }
	if ( 0 === strpos( $path, 'time.' ) ) { return 'time'; }
	if ( 0 === strpos( $path, 'media.' ) ) { return 'media'; }
	if ( 0 === strpos( $path, 'reporter.' ) || 0 === strpos( $path, 'consents.' ) ) { return 'contact'; }
	return 'details';
}

function badaround_native_report_config() {
	$labels = badaround_native_report_labels();
	$map = new BadAround_Event_Taxonomy_Map();
	$categories = array();
	foreach ( BadAround_Report_Schema::category_subtypes() as $key => $subtypes ) {
		$term = $map->resolve_category( $key );
		if ( ! $term ) { return null; } // Never invent or materialize taxonomy during rendering.
		$items = array();
		foreach ( $subtypes as $subtype ) {
			$child = $map->resolve_subtype( $subtype, $term );
			if ( ! $child ) { return null; }
			$items[ $subtype ] = $child->name;
		}
		$categories[ $key ] = array( 'label' => $term->name, 'subtypes' => $items );
	}
	// Presentation order of the six approved groups; membership still comes from the contract.
	$order = array_flip( array( 'vehicle', 'property', 'hazard', 'public_space', 'animal', 'item_document' ) );
	uksort( $categories, function ( $a, $b ) use ( $order ) { return ( $order[ $a ] ?? 99 ) <=> ( $order[ $b ] ?? 99 ); } );
	$fields = array();
	foreach ( BadAround_Report_Schema::fields() as $path => $definition ) {
		if ( in_array( $path, array( 'schema_version', 'submission_id', 'consents.version', 'media.items', 'location.place_id' ), true ) ) { continue; }
		$options = array();
		foreach ( $definition['constraints']['enum'] ?? $definition['constraints']['item_enum'] ?? array() as $value ) {
			$options[ $value ] = badaround_native_report_option_label( $path, $value );
		}
		if ( 'event.category' === $path ) {
			$options = array_map( function ( $category ) { return $category['label']; }, $categories );
		} elseif ( 'event.subtype' === $path ) {
			$options = array();
			foreach ( $categories as $category ) { $options = array_merge( $options, $category['subtypes'] ); }
		}
		$fields[ $path ] = array(
			'type' => $definition['type'], 'required' => $definition['required'], 'conditions' => $definition['conditions'],
			'constraints' => $definition['constraints'], 'label' => $labels[ $path ] ?? $path, 'options' => $options,
			'step' => badaround_native_report_step( $path ),
			'help' => 'private' === $definition['privacy'] ? 'Dato riservato: non viene pubblicato integralmente.' : 'La pubblicazione avviene solo dopo moderazione.',
		);
	}
	// Keep the territorial hierarchy ahead of the precise private address.
	$location_order = array_flip( array( 'location.region', 'location.province', 'location.municipality', 'location.locality', 'location.area_label', 'location.exact_address', 'location.exact_lat', 'location.exact_lng', 'location.public_precision', 'location.place_type', 'location.immediate_danger' ) );
	$original_order = array_flip( array_keys( $fields ) );
	uksort( $fields, function ( $a, $b ) use ( $location_order, $original_order ) {
		if ( isset( $location_order[ $a ], $location_order[ $b ] ) ) { return $location_order[ $a ] <=> $location_order[ $b ]; }
		return $original_order[ $a ] <=> $original_order[ $b ];
	} );
	$fields['location.exact_address']['help'] = 'Indirizzo e civico restano riservati. La posizione pubblica sarà approssimata.';
	$fields['vehicle.plate_raw']['help'] = 'La targa completa resta riservata. L’eventuale versione pubblica sarà mascherata.';
	$fields['reporter.public_identity_mode']['help'] = 'Nome e cognome restano nei dati riservati; la modalità scelta autorizza solo l’identità pubblica indicata. Email e telefono non sono pubblicati.';
	$fields['content.description']['help'] = 'Riporta i fatti. Evita nomi, recapiti, accuse e dati personali non necessari. Il testo sarà moderato.';
	return array(
		'schemaVersion' => BadAround_Report_Schema::VERSION,
		'consentVersion' => 'native-f16-v1', // Version of this QA presentation, not a claim of finalized legal policies.
		'endpoint' => rest_url( BadAround_Native_Report_REST_Controller::REST_NAMESPACE . BadAround_Native_Report_REST_Controller::REST_ROUTE ),
		'markerHeader' => BadAround_Native_Report_REST_Controller::REQUEST_HEADER,
		'markerValue' => BadAround_Native_Report_REST_Controller::REQUEST_HEADER_VALUE,
		'media' => array( 'endpoint' => rest_url( 'badaround/v1/report-media-sessions' ) ),
		'timezone' => wp_timezone_string(), 'fields' => $fields, 'categories' => $categories,
		'steps' => array( 'event' => 'Cosa è successo', 'location' => 'Dove', 'time' => 'Quando', 'details' => 'Dettagli', 'media' => 'Immagini', 'contact' => 'Contatto e privacy', 'review' => 'Riepilogo e invio' ),
	);
}

function badaround_native_report_field( $path, $field ) {
	$id = 'ba-native-' . str_replace( '.', '-', $path );
	$type = $field['type'];
	$c = $field['constraints'];
	$description = $id . '-help ' . $id . '-error';
	?>
	<div class="ba-native-field" data-native-field="<?php echo esc_attr( $path ); ?>">
	<?php if ( 'array' === $type ) : ?>
		<fieldset id="<?php echo esc_attr( $id ); ?>" tabindex="-1" aria-describedby="<?php echo esc_attr( $description ); ?>"><legend><?php echo esc_html( $field['label'] ); ?></legend>
		<?php foreach ( $field['options'] as $value => $label ) : ?>
		<label class="ba-native-choice"><input type="checkbox" name="<?php echo esc_attr( $path ); ?>" value="<?php echo esc_attr( $value ); ?>" data-native-input><?php echo esc_html( $label ); ?></label>
		<?php endforeach; ?></fieldset>
	<?php elseif ( 'bool' === $type ) : ?>
		<label class="ba-native-choice" for="<?php echo esc_attr( $id ); ?>"><input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $path ); ?>" type="checkbox" data-native-input aria-describedby="<?php echo esc_attr( $description ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
	<?php else : ?>
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?><span data-native-required><?php echo $field['required'] ? ' (obbligatorio quando applicabile)' : ''; ?></span></label>
		<?php if ( 'enum' === $type ) : ?>
		<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $path ); ?>" data-native-input aria-describedby="<?php echo esc_attr( $description ); ?>"><option value="">Seleziona…</option>
		<?php foreach ( $field['options'] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
		<?php elseif ( 'string' === $type && ( $c['max_length'] ?? 0 ) >= 500 ) : ?>
		<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $path ); ?>" data-native-input rows="4" maxlength="<?php echo esc_attr( $c['max_length'] ); ?>" aria-describedby="<?php echo esc_attr( $description ); ?>"></textarea>
		<?php else :
			$input_type = array( 'email' => 'email', 'phone' => 'tel', 'date' => 'date', 'time' => 'time', 'number' => 'number', 'decimal' => 'number' )[ $type ] ?? 'text';
			?>
		<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $path ); ?>" type="<?php echo esc_attr( $input_type ); ?>" data-native-input aria-describedby="<?php echo esc_attr( $description ); ?>"
		<?php if ( isset( $c['max_length'] ) ) : ?> maxlength="<?php echo esc_attr( $c['max_length'] ); ?>"<?php endif; ?>
		<?php foreach ( array( 'min', 'max' ) as $limit ) { if ( isset( $c[ $limit ] ) ) { echo ' ' . $limit . '="' . esc_attr( $c[ $limit ] ) . '"'; } } ?>
		<?php if ( 'number' === $input_type ) : ?> step="any" inputmode="decimal"<?php endif; ?>
		<?php if ( 'email' === $input_type ) : ?> autocomplete="email"<?php elseif ( 'tel' === $input_type ) : ?> autocomplete="tel"<?php elseif ( 'reporter.first_name' === $path ) : ?> autocomplete="given-name"<?php elseif ( 'reporter.last_name' === $path ) : ?> autocomplete="family-name"<?php endif; ?>>
		<?php endif; ?>
	<?php endif; ?>
		<p id="<?php echo esc_attr( $id ); ?>-help" class="ba-native-help"><?php echo esc_html( $field['help'] ); ?></p>
		<p id="<?php echo esc_attr( $id ); ?>-error" class="ba-native-error" hidden></p>
	</div>
	<?php
}
