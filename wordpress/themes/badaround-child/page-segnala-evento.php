<?php
/* Template Name: BadAround - Segnala evento */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<main class="ba-report-app" id="main-content">
	<section class="ba-report-workspace ba-container--wide" aria-labelledby="ba-report-page-title">
		<header class="ba-report-workspace__head">
			<div class="ba-report-workspace__identity">
				<span class="ba-report-workspace__icon" data-native-page-icon data-native-page-icon-state="event" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
						<g data-native-step-icon="event"><path d="M5 4h10a2 2 0 0 1 2 2v2M5 4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-3M7 8h5M7 12h3M7 16h4m3-3 6-6 2 2-6 6-3 1 1-3Z"/></g>
						<g data-native-step-icon="location" hidden><g transform="translate(3 0) scale(.38)" stroke="none"><?php echo badaround_pin_catalog()['neutral']['body']; ?></g></g>
						<g data-native-step-icon="details" hidden><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5"/></g>
						<g data-native-step-icon="media" hidden><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="1.5"/><path d="m3 17 5-5 4 4 3-3 6 6"/></g>
						<g data-native-step-icon="contact" hidden><circle cx="8" cy="7" r="3"/><path d="M2 20v-3a6 6 0 0 1 9-5"/><rect x="12" y="12" width="10" height="8" rx="1"/><path d="m12 13 5 4 5-4"/></g>
						<g data-native-step-icon="review" hidden><rect x="4" y="3" width="16" height="18" rx="2"/><path d="m7 8 1.5 1.5L11 7m-4 7 1.5 1.5L11 13M14 8h3M14 14h3M7 18h10"/></g>
						<g data-native-step-icon="complete" hidden><circle cx="12" cy="12" r="9"/><path d="m7.5 12 3 3 6-6"/></g>
					</svg>
				</span>
				<div>
					<h1 id="ba-report-page-title" data-native-page-title tabindex="-1">Cosa è successo?</h1>
					<p data-native-page-intro>Ti guidiamo una domanda alla volta. Scegli la categoria e il tipo di evento: vedrai solo i dettagli pertinenti. Puoi cambiare una risposta con «Modifica».</p>
				</div>
			</div>

		</header>

		<div class="ba-report-stage"><?php get_template_part( 'template-parts/native-report/shell' ); ?></div>
		
		<footer class="ba-report-trust">
			<div>
				<span aria-hidden="true">🔒</span>
				<p><strong>Privacy by design.</strong> Dati personali, posizione precisa e informazioni riservate non vengono pubblicati automaticamente.<span class="ba-report-required-legend">I campi contrassegnati con <span class="ba-required-marker" aria-hidden="true">*</span><span class="screen-reader-text">asterisco</span> sono obbligatori.</span></p>
			</div>
			<a href="<?php echo esc_url( home_url( '/come-funziona/' ) ); ?>">Come funziona la moderazione</a>
		</footer>
	</section>
</main>
<?php get_footer(); ?>
