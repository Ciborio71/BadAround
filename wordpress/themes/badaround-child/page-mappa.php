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
	<section class="ba-map-commandbar" aria-label="Ricerca e filtri mappa">
		<div class="ba-map-commandbar__summary">
			<span class="ba-map-results-panel__eyebrow">Mappa BadAround</span>
			<div class="ba-map-commandbar__heading">
				<h1>Segnalazioni nella zona</h1>
				<span class="ba-map-commandbar__count" data-ba-map-count>Caricamento…</span>
			</div>
		</div>

		<div class="ba-map-commandbar__controls">
			<div class="ba-map-commandbar__primary">
				<label class="ba-map-inline-search">
					<span class="screen-reader-text">Cerca tra le segnalazioni</span>
					<input type="search" placeholder="Cerca evento o luogo" data-ba-map-search>
				</label>
				<button type="button" class="ba-map-nearby-button" data-ba-map-nearby aria-pressed="false">
					<span aria-hidden="true">⌖</span><strong>Vicino a me</strong>
				</button>
				<p class="ba-map-nearby-status" data-ba-map-nearby-status role="status" aria-live="polite"></p>
			</div>

			<div class="ba-map-commandbar__filters">
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
						<button type="button" class="ba-map-time-filter" data-ba-map-time="today" aria-pressed="false">Oggi</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="3" aria-pressed="false">Ultimi 3 giorni</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="7" aria-pressed="false">7 giorni</button>
						<button type="button" class="ba-map-time-filter" data-ba-map-time="30" aria-pressed="false">30 giorni</button>
						<button type="button" class="ba-map-time-filter is-active" data-ba-map-time="all" aria-pressed="true">Tutti</button>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section class="ba-map-workspace">
		<aside class="ba-map-results-panel" data-ba-map-results aria-label="Eventi pubblicati">
			<div class="ba-map-results-panel__mobile-head">
				<div>
					<span class="ba-map-results-panel__eyebrow">Risultati</span>
					<strong data-ba-map-mobile-count>0 risultati</strong>
				</div>
				<button class="ba-map-results-panel__close" type="button" data-ba-map-close aria-label="Chiudi elenco">×</button>
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

			<details class="ba-map-legend">
				<summary><span class="ba-map-legend__label">Legenda pinpoint</span></summary>
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

			<aside class="ba-map-preview ba-home-map__preview" data-ba-map-preview hidden aria-live="polite" aria-label="Anteprima evento selezionato">
				<button class="ba-map-preview__close" type="button" data-ba-map-preview-close aria-label="Chiudi anteprima">×</button>
				<div data-ba-map-preview-content></div>
			</aside>

			<button class="ba-map-mobile-results" type="button" data-ba-map-open>
				<span data-ba-map-mobile-count>0 risultati</span>
				<strong>Mostra elenco</strong>
			</button>

		</div>
	</section>

	<div class="ba-map-mobile-backdrop" data-ba-map-backdrop hidden></div>
</main>
<?php get_footer(); ?>
