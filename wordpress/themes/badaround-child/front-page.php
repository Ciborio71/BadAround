<?php
get_header();

$demo_events = array(
	array(
		'title'        => 'Auto rubata nella zona',
		'status'       => 'urgent',
		'status_label' => 'Urgente',
		'meta'         => 'Oggi · zona Lungomare · 1,8 km',
		'type'         => 'Furto veicolo',
		'image'        => '',
	),
	array(
		'title'        => 'Danneggiamento in parcheggio',
		'status'       => 'witness',
		'status_label' => 'Cerca testimoni',
		'meta'         => 'Ieri · zona centrale · 6,4 km',
		'type'         => 'Danneggiamento',
		'image'        => '',
	),
	array(
		'title'        => 'Veicolo ritrovato',
		'status'       => 'resolved',
		'status_label' => 'Risolto',
		'meta'         => '2 giorni fa · area vicina · 8,1 km',
		'type'         => 'Veicolo ritrovato',
		'image'        => '',
	),
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

	<section class="ba-home-localbar" aria-label="Riepilogo della zona">
		<div class="ba-container--wide ba-home-localbar__inner">
			<div class="ba-home-localbar__place">
				<span class="ba-home-localbar__marker" aria-hidden="true">●</span>
				<div><span>Esplora</span><strong>l’area attorno a te</strong></div>
			</div>
			<div class="ba-home-stat"><strong>18</strong><span>segnalazioni</span></div>
			<div class="ba-home-stat"><strong>6</strong><span>richieste di aiuto</span></div>
			<div class="ba-home-stat"><strong>4</strong><span>eventi risolti</span></div>
		</div>
	</section>

	<section class="ba-home-main ba-section--tight">
		<div class="ba-container--wide">
			<nav class="ba-home-categories" aria-label="Categorie principali">
				<a href="#"><span class="ba-home-category__icon is-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 13h16l-1.5-5h-13L4 13Zm1 0v5m14-5v5M7 18h10M7 8l1.2-3h7.6L17 8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></span>Furti</a>
				<a href="#"><span class="ba-home-category__icon is-coral" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m14.5 5.5 4 4M6 18l4.4-4.4m-2-7.2 9.2 9.2M5 5l14 14" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></span>Danni</a>
				<a href="#"><span class="ba-home-category__icon is-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 12s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg></span>Testimoni</a>
				<a href="#"><span class="ba-home-category__icon is-purple" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19v-2a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v2M9 7a3 3 0 1 0 6 0 3 3 0 0 0-6 0Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></span>Sicurezza</a>
				<a href="#"><span class="ba-home-category__icon is-slate" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 16h14l-1-6H6l-1 6Zm2 0v2m10-2v2M8 10l1-3h6l1 3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></span>Veicoli abbandonati</a>
				<a href="#"><span class="ba-home-category__icon is-green" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 3 20h18L12 3Z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 9v5m0 3h.01" stroke="currentColor" stroke-width="1.8"/></svg></span>Degrado</a>
			</nav>

			<div class="ba-home-sectionhead">
				<h2>Segnalazioni vicino a te</h2>
				<a href="#">Vedi tutte le segnalazioni <span aria-hidden="true">→</span></a>
			</div>

			<div class="ba-home-events" id="segnalazioni">
				<?php foreach ( $demo_events as $event ) : ?>
					<article class="ba-home-event ba-card">
						<div class="ba-home-event__thumb" aria-hidden="true"><span></span></div>
						<div class="ba-home-event__body">
							<span class="ba-badge ba-badge--<?php echo esc_attr( $event['status'] ); ?>"><?php echo esc_html( $event['status_label'] ); ?></span>
							<h3><?php echo esc_html( $event['title'] ); ?></h3>
							<p><?php echo esc_html( $event['meta'] ); ?></p>
							<div class="ba-home-event__foot">
								<span><?php echo esc_html( $event['type'] ); ?></span>
								<a href="#">Vedi segnalazione <span aria-hidden="true">→</span></a>
							</div>
						</div>
					</article>
				<?php endforeach; ?>
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
						<a class="ba-button" href="#">Attiva gli alert <span aria-hidden="true">→</span></a>
					</div>
					<div class="ba-home-phone" aria-hidden="true">
						<div class="ba-home-phone__notch"></div>
						<div class="ba-home-phone__alert"><strong>BadAround</strong><span>Nuova segnalazione<br>a 2 km da te</span></div>
					</div>
				</section>

				<section class="ba-home-how" id="come-funziona">
					<h2>Come funziona</h2>
					<ol>
						<li><span>1</span><div><strong>Segnala</strong><p>Descrivi cosa hai visto in modo semplice e anonimo.</p></div></li>
						<li><span>2</span><div><strong>Verifichiamo</strong><p>Controlliamo la segnalazione e rimuoviamo contenuti inappropriati.</p></div></li>
						<li><span>3</span><div><strong>La comunità aiuta</strong><p>Le informazioni utili sono visibili a tutti.</p></div></li>
					</ol>
					<p class="ba-home-disclaimer">Le segnalazioni sono pubblicate dagli utenti e non costituiscono dati ufficiali.</p>
				</section>
			</div>

			<aside class="ba-home-ad" aria-label="Contenuto sponsorizzato">
				<div class="ba-home-ad__label">Contenuto sponsorizzato</div>
				<div class="ba-home-ad__copy">
					<span class="ba-home-ad__kicker">Attività della zona</span>
					<div><strong>Proteggi il tuo veicolo nella tua zona</strong><p>Antifurti satellitari, block shaft e soluzioni di sicurezza personalizzate.</p></div>
				</div>
				<a class="ba-button ba-button--dark" href="#">Scopri di più <span aria-hidden="true">→</span></a>
			</aside>
		</div>
	</section>
</main>
<?php get_footer(); ?>
