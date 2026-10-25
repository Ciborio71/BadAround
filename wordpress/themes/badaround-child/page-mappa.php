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
	<section class="ba-map-workspace">
		<aside class="ba-map-results-panel" data-ba-map-results aria-label="Eventi pubblicati">
			<div class="ba-map-results-panel__top">
				<div>
					<span class="ba-map-results-panel__eyebrow">Mappa BadAround</span>
					<h1>Segnalazioni nella zona</h1>
				</div>
				<button class="ba-map-results-panel__close" type="button" data-ba-map-close aria-label="Chiudi elenco">×</button>
			</div>

			<div class="ba-map-results-panel__controls">
				<div class="ba-map-search-row">
					<label class="ba-map-inline-search">
						<span class="screen-reader-text">Cerca tra le segnalazioni</span>
						<input type="search" placeholder="Cerca evento o luogo" data-ba-map-search>
					</label>
					<button type="button" class="ba-map-nearby-button" data-ba-map-nearby aria-pressed="false">
						<span aria-hidden="true">⌖</span><strong>Vicino a me</strong>
					</button>
				</div>
				<div class="ba-map-category-filters" role="group" aria-label="Filtra per categoria">
					<button type="button" class="ba-map-category-filter is-active" data-ba-map-filter="all" aria-pressed="true">Tutte</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="veicoli" aria-pressed="false">Veicoli</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="case-e-attivita" aria-pressed="false">Case e attività</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="pericoli" aria-pressed="false">Pericoli</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="spazi-pubblici" aria-pressed="false">Spazi pubblici</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="animali" aria-pressed="false">Animali</button>
					<button type="button" class="ba-map-category-filter" data-ba-map-filter="oggetti-e-documenti" aria-pressed="false">Oggetti e documenti</button>
				</div>
				<div class="ba-map-time-filter-wrap">
					<span class="ba-map-filter-label">Quando</span>
					<div class="ba-map-time-filters" role="group" aria-label="Filtra per data dell'evento">
						<button type="button" class="ba-map-time-filter is-active" data-ba-map-time="all" aria-pressed="true">Tutti</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="today" aria-pressed="false">Oggi</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="3" aria-pressed="false">3 giorni</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="7" aria-pressed="false">7 giorni</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="30" aria-pressed="false">30 giorni</button>
					</div>
				</div>
				<p class="ba-map-nearby-status" data-ba-map-nearby-status role="status" aria-live="polite"></p>
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
			<div id="ba-google-map" class="ba-google-map" data-ba-map data-ba-map-context="full" role="application" aria-label="Google Maps con eventi BadAround"></div>

			<div class="ba-map-status" data-ba-map-status hidden></div>

			<details class="ba-map-legend" open>
				<summary>Legenda pinpoint</summary>
				<div class="ba-map-legend__body">
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-vehicles" aria-hidden="true"></span><span><strong>Veicoli</strong><small>Auto, moto e altri mezzi</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-property" aria-hidden="true"></span><span><strong>Case e attività</strong><small>Abitazioni, negozi e uffici</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-hazard" aria-hidden="true"></span><span><strong>Pericoli</strong><small>Rischi e situazioni pericolose</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-public" aria-hidden="true"></span><span><strong>Spazi pubblici</strong><small>Strade, aree pubbliche e decoro</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-animal" aria-hidden="true"></span><span><strong>Animali</strong><small>Smarrimenti e segnalazioni</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-object" aria-hidden="true"></span><span><strong>Oggetti e documenti</strong><small>Oggetti smarriti o ritrovati</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__pin is-resolved" aria-hidden="true">✓</span><span><strong>Risolto</strong><small>Evento concluso</small></span></div>
					<div class="ba-map-legend__item"><span class="ba-map-legend__cluster" aria-hidden="true">3</span><span><strong>Più eventi</strong><small>Pinpoint raggruppati nella stessa area</small></span></div>
				</div>
			</details>


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
