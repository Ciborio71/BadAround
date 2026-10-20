<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( 'event' === $key ) : ?>
	<p>Ti guidiamo una domanda alla volta. Scegli la categoria e il tipo di evento: vedrai solo i dettagli pertinenti. Puoi cambiare una risposta con «Modifica».</p>
<?php elseif ( 'details' === $key ) : ?>
	<p>Descrivi i fatti e aggiungi le informazioni utili a chi si trova nella zona. Puoi lasciare vuoti i campi facoltativi.</p>
<?php elseif ( 'location' === $key ) : ?>
	<p>Indica il punto più preciso possibile. Questa posizione resterà riservata e ci aiuterà a capire meglio dove si verificano gli eventi. La posizione mostrata pubblicamente potrà essere approssimata per tutelare la privacy.</p>
<div class="ba-native-smart-address" data-native-smart-address>
  <label for="ba-smart-search">Dove è successo?</label>
  <input id="ba-smart-search" data-smart-start type="text" readonly autocomplete="off" placeholder="Cerca indirizzo o località" aria-describedby="ba-smart-privacy ba-smart-status">
  <p id="ba-smart-privacy" class="ba-native-help"><strong>L’indirizzo preciso e il numero civico restano riservati. La posizione pubblica può essere approssimata per proteggere la privacy.</strong></p>
  <div data-smart-widget></div>
  <button data-smart-geolocate type="button" class="ba-button ba-button--outline">Usa la mia posizione</button>
  <p class="ba-native-help">Seleziona un suggerimento Google, anche usando le frecce e Invio. La posizione del dispositivo viene richiesta solo premendo il pulsante.</p>
  <p id="ba-smart-status" data-smart-status role="status" aria-live="polite">Seleziona il campo per cercare un luogo.</p>
  <div data-smart-confirmation hidden><strong>Posizione riconosciuta</strong><p data-smart-address-label></p><small>L'indirizzo preciso non sarà pubblicato automaticamente.</small></div>
  <div data-smart-map-panel hidden>
    <div data-smart-map class="ba-native-location-map" role="region" aria-label="Mappa della posizione riservata dell’evento" aria-describedby="ba-smart-map-help"></div>
    <p id="ba-smart-map-help" class="ba-native-help">Il punto che stai indicando è riservato. Se necessario, sposta il segnaposto nel punto esatto in cui è avvenuto l'evento. L’indirizzo viene verificato di nuovo. Da tastiera, seleziona il segnaposto e premi Alt e Invio (Option e Invio su Mac) per spostarlo con le frecce, poi Invio per confermare.</p>
  </div>
  <p data-smart-map-status role="status" aria-live="polite"></p>
  <button data-smart-manual type="button" class="ba-button" hidden>Inserisci manualmente</button>
  </div>
<?php elseif ( 'time' === $key ) : ?>
	<p>Indica quando è successo, anche in modo approssimativo. Se non lo sai, puoi indicarlo. Gli orari si riferiscono al fuso <?php echo esc_html( $config['timezone'] ); ?>.</p>
<?php elseif ( 'media' === $key ) : ?>
	<p>Le immagini sono facoltative. Gli originali restano riservati per verifica e moderazione: l’invio non li rende automaticamente pubblici. Solo versioni approvate possono diventare pubbliche. Evita dati personali non necessari di terzi.</p>
<?php elseif ( 'contact' === $key ) : ?>
	<p>I tuoi recapiti restano riservati e servono a BadAround per gestire la segnalazione e, se lo desideri, per inoltrarti eventuali informazioni utili ricevute dalla community.</p>
	<?php $privacy_url = get_privacy_policy_url(); if ( $privacy_url ) : ?><p><a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">Leggi l’informativa privacy</a></p><?php endif; ?>
<?php elseif ( 'review' === $key ) : ?>
	<p>Controlla i dati prima dell’invio. I dati indicati come riservati saranno usati internamente.</p>
	<div data-native-review></div>
<?php endif;
if ( 'location' === $key ) : ?><div data-native-location-fields hidden><?php endif;
foreach ( $config['fields'] as $path => $field ) {
	if ( $field['step'] === $key && ( 'location' !== $key || 0 !== strpos( $path, 'time.' ) ) ) { badaround_native_report_field( $path, $field ); }
}
if ( 'location' === $key ) : ?></div>
	<div data-native-time-fields>
	<p>Indica anche quando è successo. Puoi scegliere una data, un periodo approssimativo oppure «Non lo so». Gli orari si riferiscono al fuso <?php echo esc_html( $config['timezone'] ); ?>.</p>
	<?php foreach ( $config['fields'] as $path => $field ) { if ( 0 === strpos( $path, 'time.' ) ) { badaround_native_report_field( $path, $field ); } } ?>
	</div>
<?php endif;
if ( 'media' === $key ) : ?>
	<div class="ba-native-media" data-native-media>
		<label for="ba-native-media-files">Scegli immagini (facoltativo)</label>
		<input id="ba-native-media-files" type="file" multiple accept="image/jpeg,image/png,image/webp" aria-describedby="ba-native-media-help ba-native-media-formats ba-native-media-notice" disabled>
		<p id="ba-native-media-help" class="ba-native-help">Massimo 5 immagini, 5 MiB (5.242.880 byte) ciascuna. Scegli «Sì» per aggiungerle. L’anteprima locale non conferma il caricamento: attendi lo stato «Accettata».</p>
		<p id="ba-native-media-formats" class="ba-native-help" data-media-formats></p>
		<p data-media-count></p>
		<p id="ba-native-media-notice" class="ba-native-error" data-media-notice role="alert" hidden></p>
		<p data-media-live role="status" aria-live="polite" aria-atomic="true"></p>
		<ul class="ba-native-media-list" data-media-list aria-label="Immagini selezionate"></ul>
	</div>
<?php endif;

