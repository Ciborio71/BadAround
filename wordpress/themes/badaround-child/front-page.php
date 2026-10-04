<?php
get_header();

$recent_events = array(
	'items' => array(),
	'total' => 0,
);

if ( class_exists( 'BadAround_Discovery_Query' ) ) {
	$recent_events = ( new BadAround_Discovery_Query() )->discover(
		array(
			'page'     => 1,
			'per_page' => 3,
		)
	);
}

$home_categories = array(
	array( 'label' => 'Veicoli',              'slug' => 'veicoli',              'class' => 'is-blue' ),
	array( 'label' => 'Case e attività',      'slug' => 'case-e-attivita',      'class' => 'is-coral' ),
	array( 'label' => 'Pericoli',             'slug' => 'pericoli',             'class' => 'is-amber' ),
	array( 'label' => 'Spazi pubblici',       'slug' => 'spazi-pubblici',       'class' => 'is-purple' ),
	array( 'label' => 'Animali',              'slug' => 'animali',              'class' => 'is-green' ),
	array( 'label' => 'Oggetti e documenti',  'slug' => 'oggetti-e-documenti',  'class' => 'is-slate' ),
);
?>
<main class="ba-home" id="main-content">
	<section class="ba-home-hero" aria-labelledby="ba-home-title">
		<div class="ba-home-hero__content">
			<p class="ba-eyebrow">La comunità che osserva, segnala e aiuta</p>
			<h1 id="ba-home-title">Cosa succede<br>intorno a te?</h1>
			<p class="ba-home-hero__lead">Segnalazioni locali, richieste di testimoni e aggiornamenti utili dalla tua comunità.</p>

			<form class="ba-home-search" role="search" action="<?php echo esc_url( home_url( '/cerca/' ) ); ?>" method="get">
				<label class="ba-sr-only" for="ba-home-location">Cerca territorio o evento</label>
				<span class="ba-home-search__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" fill="currentColor"/></svg></span>
				<input id="ba-home-location" name="q" type="search" placeholder="Cerca comune, quartiere o evento…">
				<button class="ba-button" type="submit">Cerca</button>
			</form>

			<button class="ba-button ba-button--outline ba-home-position" type="button" data-ba-geolocate aria-describedby="ba-home-geo-status">Usa la mia posizione</button>
			<p class="ba-home-privacy">La posizione pubblica degli eventi è sempre approssimata. La tua posizione non viene salvata.</p>
			<p id="ba-home-geo-status" class="ba-home-geo-status" data-ba-geolocation-status role="status" aria-live="polite"></p>
		</div>

		<div class="ba-home-map" id="ba-map" aria-label="Mappa interattiva delle segnalazioni pubbliche">
			<div id="ba-home-google-map" class="ba-google-map ba-home-google-map" data-ba-map data-ba-map-context="home" role="application" aria-label="Google Maps con eventi pubblicati BadAround"></div>

			<div class="ba-map-status ba-home-map__status" data-ba-map-status hidden></div>

			<details class="ba-map-legend ba-map-legend--home" open>
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

			<aside class="ba-map-preview ba-home-map__preview" data-ba-map-preview hidden aria-live="polite" aria-label="Anteprima evento selezionato">
				<button class="ba-map-preview__close" type="button" data-ba-map-preview-close aria-label="Chiudi anteprima">×</button>
				<div data-ba-map-preview-content></div>
			</aside>

			<div class="ba-map-privacy-note">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 7.8 7 10 4.2-2.2 7-5.4 7-10V6l-7-3Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
				<span>Le posizioni degli eventi sono pubbliche e possono essere approssimate.</span>
			</div>
		</div>
	</section>

	<section class="ba-home-localbar" aria-label="Esplora BadAround">
		<div class="ba-container--wide ba-home-localbar__inner">
			<div class="ba-home-localbar__place">
				<span class="ba-home-localbar__marker" aria-hidden="true">●</span>
				<div><span>Esplora</span><strong>l’area attorno a te</strong></div>
			</div>
			<a class="ba-home-localbar__link" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>">Apri la mappa</a>
			<a class="ba-home-localbar__link" href="<?php echo esc_url( home_url( '/segnalazioni/' ) ); ?>">Tutte le segnalazioni</a>
			<a class="ba-home-localbar__link" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala un evento</a>
		</div>
	</section>

	<section class="ba-home-main ba-section--tight">
		<div class="ba-container--wide">
			<nav class="ba-home-categories" aria-label="Categorie principali">
				<?php foreach ( $home_categories as $category ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'categoria', $category['slug'], home_url( '/segnalazioni/' ) ) ); ?>">
						<span class="ba-home-category__icon <?php echo esc_attr( $category['class'] ); ?>" aria-hidden="true">●</span>
						<?php echo esc_html( $category['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="ba-home-sectionhead">
				<h2>Segnalazioni recenti</h2>
				<a href="<?php echo esc_url( home_url( '/segnalazioni/' ) ); ?>">Vedi tutte le segnalazioni <span aria-hidden="true">→</span></a>
			</div>

			<div class="ba-home-events" id="segnalazioni">
				<?php if ( ! empty( $recent_events['items'] ) ) : ?>
					<?php foreach ( $recent_events['items'] as $item ) : ?>
						<?php
						$meta = array_filter(
							array(
								$item['occurred_date'] ?? '',
								$item['occurred_time'] ?? '',
								$item['public_place_name'] ?? ( $item['territory']['name'] ?? '' ),
							)
						);
						get_template_part(
							'template-parts/components/event-card',
							null,
							array(
								'title'        => $item['title'],
								'url'          => $item['permalink'],
								'status'       => 'info',
								'status_label' => $item['event_type']['name'] ?? 'Segnalazione',
								'meta'         => implode( ' · ', $meta ),
								'image'        => $item['thumbnail'] ?? '',
								'image_alt'    => $item['title'],
								'excerpt'      => $item['excerpt'] ?? '',
								'link_label'   => 'Visualizza segnalazione',
							)
						);
						?>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="ba-card ba-home-events-empty">
						<h3>Nessuna segnalazione pubblicata</h3>
						<p>Quando saranno disponibili nuovi eventi moderati e pubblicati, compariranno qui.</p>
						<a class="ba-button ba-button--urgent" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala un evento</a>
					</div>
				<?php endif; ?>
			</div>

			<div class="ba-home-lower">
				<section class="ba-home-sentinel" id="sentinelle">
					<div class="ba-home-sentinel__copy">
						<div class="ba-home-sentinel__badge">
							<span class="ba-home-sentinel__badge-icon" aria-hidden="true">
								<svg viewBox="0 0 28 28" focusable="false">
									<path d="M14 25s7-6.2 7-13a7 7 0 1 0-14 0c0 6.8 7 13 7 13Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
									<path d="M9.6 12s1.7-2.7 4.4-2.7 4.4 2.7 4.4 2.7-1.7 2.7-4.4 2.7S9.6 12 9.6 12Z" fill="none" stroke="currentColor" stroke-width="1.55"/>
									<circle cx="14" cy="12" r="1.45" fill="currentColor"/>
								</svg>
							</span>
							<span>Sentinella BadAround</span>
						</div>
						<h2>Vuoi aiutare a tenere d’occhio una zona?</h2>
						<p>Diventa una <strong>Sentinella BadAround</strong>: ricevi aggiornamenti sulla zona che ti interessa e contribuisci alla community con segnalazioni e avvistamenti utili!</p>
						<p class="ba-home-sentinel__privacy">La tua identità non viene resa pubblica.</p>
						<a class="ba-button" href="<?php echo esc_url( home_url( '/sentinelle/' ) ); ?>">Attiva una Sentinella <span aria-hidden="true">→</span></a>
					</div>
					<div class="ba-home-phone" aria-hidden="true">
						<div class="ba-home-phone__notch"></div>
						<div class="ba-home-phone__alert"><strong>BadAround</strong><span>Nuova segnalazione<br>nella zona che segui</span></div>
					</div>
				</section>

				<section class="ba-home-how" id="come-funziona">
					<h2>Come funziona</h2>
					<ol>
						<li><span>1</span><div><strong>Segnala</strong><p>Descrivi cosa hai visto in modo semplice.</p></div></li>
						<li><span>2</span><div><strong>Verifichiamo</strong><p>Controlliamo la segnalazione e rimuoviamo contenuti inappropriati.</p></div></li>
						<li><span>3</span><div><strong>La comunità aiuta</strong><p>Le informazioni utili sono visibili a tutti.</p></div></li>
					</ol>
					<p class="ba-home-disclaimer">Le segnalazioni sono pubblicate dagli utenti e non costituiscono dati ufficiali.</p>
					<a class="ba-button ba-button--outline" href="<?php echo esc_url( home_url( '/come-funziona/' ) ); ?>">Scopri come funziona</a>
				</section>
			</div>

		</div>
	</section>
</main>
<?php get_footer(); ?>
