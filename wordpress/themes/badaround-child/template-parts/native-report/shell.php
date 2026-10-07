<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
$config = badaround_native_report_config();
if ( ! $config ) {
	echo '<p role="alert">Modulo nativo non disponibile: verifica della tassonomia richiesta. Usa il modulo di riferimento.</p>';
	return;
}
?>
<div class="ba-native" data-native-report>
	<p class="ba-native-notice">Anteprima di collaudo. La segnalazione sarà sottoposta a moderazione e non verrà pubblicata automaticamente.</p>
	<a href="<?php echo esc_url( get_permalink() ); ?>">Torna al modulo di riferimento</a>
	<noscript><p>Per usare il wizard e inviare la segnalazione attiva JavaScript. I campi sono riportati di seguito per consultazione.</p></noscript>
	<p data-native-boot role="status">Preparazione del modulo…</p>
	<nav aria-label="Avanzamento della segnalazione"><ol class="ba-native-progress">
	<?php foreach ( $config['steps'] as $key => $title ) : ?><li data-native-progress="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></li><?php endforeach; ?>
	</ol></nav>
	<div class="ba-native-summary" data-native-errors tabindex="-1" role="alert" hidden></div>
	<form data-native-form novalidate>
	<?php foreach ( $config['steps'] as $key => $title ) : ?>
		<section data-native-step="<?php echo esc_attr( $key ); ?>" aria-labelledby="ba-native-heading-<?php echo esc_attr( $key ); ?>">
			<h2 id="ba-native-heading-<?php echo esc_attr( $key ); ?>" tabindex="-1"><?php echo esc_html( $title ); ?></h2>
			<?php require __DIR__ . '/step.php'; ?>
		</section>
	<?php endforeach; ?>
		<div class="ba-native-actions" data-native-navigation hidden>
			<button class="ba-button ba-button--outline" type="button" data-native-back>Indietro</button>
			<button class="ba-button" type="button" data-native-next>Continua</button>
			<button class="ba-button" type="submit" data-native-submit disabled hidden>Invia segnalazione</button>
		</div>
	</form>
	<section data-native-success hidden aria-labelledby="ba-native-received">
		<h2 id="ba-native-received" tabindex="-1">Segnalazione ricevuta</h2>
		<p>La segnalazione sarà sottoposta a moderazione. Non è ancora pubblica: l’invio non equivale ad approvazione.</p>
		<button type="button" class="ba-button" data-native-new>Inizia una nuova segnalazione</button>
	</section>
	<script type="application/json" data-native-config><?php echo wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?></script>
</div>
