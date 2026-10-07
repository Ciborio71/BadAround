<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( 'location' === $key ) : ?>
	<p>Indica Regione, Provincia, Comune e, se conosciuta, località. Civico e coordinate precise restano riservati; la posizione pubblica viene approssimata.</p>
<?php elseif ( 'time' === $key ) : ?>
	<p>Gli orari si riferiscono al fuso <?php echo esc_html( $config['timezone'] ); ?>.</p>
<?php elseif ( 'media' === $key ) : ?>
	<p>In questa anteprima puoi indicare se disponi di immagini. Il caricamento delle foto sarà integrato nel passaggio successivo; nessun file viene caricato da questo modulo.</p>
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
