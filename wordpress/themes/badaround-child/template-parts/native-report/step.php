<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( 'event' === $key ) : ?>
	<p>Ti guidiamo una domanda alla volta. Scegli la categoria e il tipo di evento: vedrai solo i dettagli pertinenti. Puoi cambiare una risposta con «Modifica».</p>
<?php elseif ( 'details' === $key ) : ?>
	<p>Descrivi i fatti e aggiungi le informazioni utili a chi si trova nella zona. Puoi lasciare vuoti i campi facoltativi.</p>
<?php elseif ( 'location' === $key ) : ?>
	<p>Indica il luogo più preciso possibile: il segnaposto finale viene acquisito privatamente. Scegli come mostrarlo al pubblico: punto esatto oppure area approssimativa. La segnalazione sarà esaminata prima della pubblicazione.</p>
<div class="ba-native-smart-address" data-native-smart-address>
  <label for="ba-smart-search">Dove è successo?</label>
  <div class="ba-native-address-slot" data-smart-search-slot>
  <input id="ba-smart-search" data-smart-start type="text" readonly autocomplete="off" placeholder="Cerca indirizzo, comune o quartiere…" aria-describedby="ba-smart-status">
  <div data-smart-widget hidden></div>
  </div>
  <button data-smart-geolocate type="button" class="ba-button ba-native-gps-button"><svg class="ba-native-gps-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4"/></svg><span data-smart-geolocate-label>Usa la mia posizione</span></button>
  <p id="ba-smart-status" data-smart-status role="status" aria-live="polite"></p>
  <div class="ba-native-location-layout" data-smart-location-layout hidden>
  <div class="ba-native-location-card" data-smart-location-card hidden>
  <div data-smart-confirmation hidden><strong>La tua posizione è:</strong><p data-smart-address-label></p></div>
  <div data-smart-map-panel hidden>
    <div data-smart-map class="ba-native-location-map" role="region" aria-label="Mappa della posizione riservata dell’evento" aria-describedby="ba-smart-map-help"></div>
    <p id="ba-smart-map-help" class="ba-native-help">Il segnaposto indica il luogo preciso dell’evento. Con Area approssimata, il pubblico vedrà solo la zona scelta. Se necessario, spostalo nel punto esatto in cui è avvenuto l'evento. L’indirizzo viene verificato di nuovo. Da tastiera, seleziona il segnaposto e premi Alt e Invio (Option e Invio su Mac) per spostarlo con le frecce, poi Invio per confermare.</p>
  </div>
  </div>
  <div class="ba-native-location-side" data-smart-location-side>
  <?php if ( ! empty( $config['publicLocationRequest'] ) ) : ?>
  <fieldset class="ba-native-public-location-choice" data-native-public-location-choice hidden aria-describedby="ba-native-public-location-help">
    <legend>Come vuoi mostrare la posizione al pubblico?</legend>
    <div class="ba-native-public-location-options">
    <label class="ba-native-public-location-option">
      <span class="ba-native-public-location-visual" aria-hidden="true">
        <svg viewBox="0 0 160 104" focusable="false">
          <rect x="6" y="6" width="148" height="92" rx="14" class="ba-geo-map-bg"/>
          <path d="M20 30h120M20 53h120M20 76h120M47 18v68M83 18v68M118 18v68" class="ba-geo-map-grid"/>
          <g transform="translate(54 12) scale(.72)"><?php echo badaround_pin_catalog()['neutral']['body']; ?></g>
        </svg>
      </span>
      <span class="ba-native-public-location-copy"><input type="radio" name="ba-public-location-choice" value="exact_point" checked><strong>Punto esatto</strong><small>Mostra il punto sulla mappa e l’indirizzo completo, compreso il civico indicato, dopo la moderazione.</small></span>
    </label>
    <label class="ba-native-public-location-option">
      <span class="ba-native-public-location-visual" aria-hidden="true">
        <svg viewBox="0 0 160 104" focusable="false">
          <rect x="6" y="6" width="148" height="92" rx="14" class="ba-geo-map-bg"/>
          <path d="M20 30h120M20 53h120M20 76h120M47 18v68M83 18v68M118 18v68" class="ba-geo-map-grid"/>
          <circle cx="80" cy="52" r="28" class="ba-geo-area"/>
          <circle cx="80" cy="52" r="5" class="ba-geo-area-core"/>
        </svg>
      </span>
      <span class="ba-native-public-location-copy"><input type="radio" name="ba-public-location-choice" value="approximate_area"><strong>Area approssimata</strong><small>Richiedi di mostrare una zona approssimata intorno al luogo dell’evento.</small></span>
    </label>
    </div>
    <div class="ba-native-radius" data-native-radius-field hidden>
      <label for="ba-public-location-radius">Raggio dell’area approssimativa <span class="ba-required-marker" aria-hidden="true">*</span></label>
      <select id="ba-public-location-radius" name="ba-public-location-radius" aria-describedby="ba-public-radius-help ba-public-radius-error" disabled>
        <option value="50">50 m — area ridotta</option>
        <option value="100">100 m — piccola area</option>
        <option value="250">250 m — zona limitata</option>
        <option value="500">500 m — zona ampia</option>
        <option value="1000">1 km — area estesa</option>
      </select>
      <p id="ba-public-radius-help" class="ba-native-help">Il cerchio mostra l’area richiesta per la pubblicazione, con il raggio in metri indicato. Puoi regolare lo zoom per vederla interamente; cambiare raggio non modifica la scala della mappa.</p>
      <p id="ba-public-radius-error" class="ba-native-error" hidden></p>
    </div>
    <p id="ba-native-public-location-help" class="ba-native-help">Scegli il punto esatto oppure un’area approssimativa con il suo raggio. Se la segnalazione è approvata, Punto esatto pubblica anche indirizzo completo e civico; Area approssimata mantiene questi dati riservati.</p>
  </fieldset>
  <?php endif; ?>
  </div>
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
