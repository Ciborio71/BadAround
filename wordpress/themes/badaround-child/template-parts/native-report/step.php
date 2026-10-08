<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( 'location' === $key ) : ?>
	<p>Indica Regione, Provincia, Comune e, se conosciuta, località. Civico e coordinate precise restano riservati; la posizione pubblica viene approssimata.</p>
<?php elseif ( 'time' === $key ) : ?>
	<p>Gli orari si riferiscono al fuso <?php echo esc_html( $config['timezone'] ); ?>.</p>
<?php elseif ( 'media' === $key ) : ?>
	<p>Le immagini sono facoltative. Gli originali restano riservati per verifica e moderazione: l’invio non li rende automaticamente pubblici. Solo versioni approvate possono diventare pubbliche. Evita dati personali non necessari di terzi.</p>
<?php elseif ( 'contact' === $key ) : ?>
	<p>Email, telefono e dati di contatto sono riservati. Scegli separatamente l’identità da mostrare al pubblico.</p>
	<?php $privacy_url = get_privacy_policy_url(); if ( $privacy_url ) : ?><p><a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">Leggi l’informativa privacy</a></p><?php endif; ?>
<?php elseif ( 'review' === $key ) : ?>
	<p>Controlla i dati prima dell’invio. I dati indicati come riservati saranno usati internamente.</p>
	<div data-native-review></div>
<?php endif;
foreach ( $config['fields'] as $path => $field ) {
	if ( $field['step'] === $key ) { badaround_native_report_field( $path, $field ); }
}
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
