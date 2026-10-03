<?php
/**
 * Template Name: BadAround — Mappa
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
$report_url = home_url( '/segnala-un-evento/' );
?>
<main class="ba-map-page" id="main-content">
	<section class="ba-map-page__toolbar">
		<div class="ba-container--wide">
			<div class="ba-map-toolbar">
				<div class="ba-map-toolbar__title">
					<span class="ba-eyebrow">Mappa BadAround</span>
					<h1>Eventi pubblicati sul territorio</h1>
				</div>
				<div class="ba-map-toolbar__actions">
					<a class="ba-button ba-button--urgent ba-map-report-cta" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
				</div>
			</div>
		</div>
	</section>

	<section class="ba-map-workspace">
		<aside class="ba-map-results-panel" data-ba-map-results aria-label="Eventi pubblicati">
			<div class="ba-map-results-panel__top">
				<div>
					<span class="ba-map-results-panel__eyebrow">Discovery</span>
					<h2>Segnalazioni pubblicate</h2>
				</div>
				<button class="ba-map-results-panel__close" type="button" data-ba-map-close aria-label="Chiudi elenco">×</button>
			</div>

			<div class="ba-map-results-panel__meta">
				<span data-ba-map-count>Caricamento…</span>
			</div>

			<div class="ba-map-results-list" data-ba-map-list aria-live="polite">
				<div class="ba-map-empty">
					<span aria-hidden="true">◎</span>
					<h3>Caricamento eventi</h3>
					<p>Stiamo recuperando le segnalazioni pubbliche disponibili.</p>
				</div>
			</div>
		</aside>

		<div class="ba-map-canvas" data-ba-map-canvas aria-label="Mappa interattiva delle segnalazioni pubbliche">
			<div id="ba-google-map" class="ba-google-map" role="application" aria-label="Google Maps con eventi BadAround"></div>

			<div class="ba-map-status" data-ba-map-status hidden></div>

			<button class="ba-map-mobile-results" type="button" data-ba-map-open>
				<span data-ba-map-mobile-count>0 risultati</span>
				<strong>Mostra elenco</strong>
			</button>

			<div class="ba-map-privacy-note">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 7.8 7 10 4.2-2.2 7-5.4 7-10V6l-7-3Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
				<span>Le posizioni mostrate sono pubbliche e possono essere approssimate.</span>
			</div>
		</div>
	</section>

	<div class="ba-map-mobile-backdrop" data-ba-map-backdrop hidden></div>
</main>
<?php get_footer(); ?>
