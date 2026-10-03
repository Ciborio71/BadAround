<?php
get_header();

$demo_events = array(
	array(
		'title'        => 'Auto rubata a Torvaianica',
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
		'meta'         => 'Ieri · Pomezia · 6,4 km',
		'type'         => 'Danneggiamento',
		'image'        => '',
	),
	array(
		'title'        => 'Veicolo ritrovato',
		'status'       => 'resolved',
		'status_label' => 'Risolto',
		'meta'         => '2 giorni fa · Ardea · 8,1 km',
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
				<input id="ba-home-location" name="q" type="search" placeholder="Cerca Torvaianica, Pomezia, veicolo rubato…">
				<button class="ba-button" type="submit">Cerca</button>
			</form>

			<button class="ba-button ba-button--outline ba-home-position" type="button">Usa la mia posizione</button>
			<p class="ba-home-privacy">La posizione pubblica è sempre approssimata.</p>
		</div>

		<div class="ba-home-map" id="ba-map" role="img" aria-label="Anteprima della mappa delle segnalazioni nell'area di Torvaianica">
			<div class="ba-home-map__toolbar" aria-hidden="true">
				<span class="is-active">Eventi</span>
				<span>Mappa di calore</span>
				<span>Tutte le categorie</span>
				<span>Ultimi 7 giorni</span>
				<span>Entro 10 km</span>
			</div>
			<div class="ba-map-cluster ba-map-cluster--red" style="left:35%;top:34%">12</div>
			<div class="ba-map-cluster ba-map-cluster--amber" style="left:57%;top:20%">7</div>
			<div class="ba-map-cluster ba-map-cluster--teal" style="left:66%;top:62%">3</div>
			<div class="ba-map-pin ba-map-pin--home" style="left:49%;top:58%" aria-hidden="true"></div>
			<div class="ba-map-pin ba-map-pin--car" style="left:53%;top:31%" aria-hidden="true"></div>
			<div class="ba-map-pin ba-map-pin--alert" style="left:77%;top:42%" aria-hidden="true"></div>
			<div class="ba-home-map__labels" aria-hidden="true">
				<span style="left:25%;top:48%">Torvaianica</span>
				<span style="left:63%;top:13%">Pomezia</span>
				<span style="left:74%;top:56%">Ardea</span>
			</div>
			<div class="ba-home-map__legend" aria-hidden="true">
				<span><i class="is-red"></i> Evento recente</span>
				<span><i class="is-amber"></i> Più segnalazioni</span>
				<span><i class="is-green"></i> Evento risolto</span>
			</div>
		</div>
	</section>

	<section class="ba-home-localbar" aria-label="Riepilogo della zona">
		<div class="ba-container--wide ba-home-localbar__inner">
			<div class="ba-home-localbar__place">
				<span class="ba-home-localbar__marker" aria-hidden="true">●</span>
				<div><span>Nelle vicinanze di</span><strong>Torvaianica</strong></div>
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
						<p class="ba-eyebrow">Sentinelle</p>
						<h2>Segui Torvaianica</h2>
						<p>Ricevi solo gli alert per le aree e le categorie che ti interessano. Aiutaci a rendere la tua comunità più sicura e informata.</p>
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
					<div><strong>Proteggi il tuo veicolo a Torvaianica</strong><p>Antifurti satellitari, block shaft e soluzioni di sicurezza personalizzate.</p></div>
				</div>
				<a class="ba-button ba-button--dark" href="#">Scopri di più <span aria-hidden="true">→</span></a>
			</aside>
		</div>
	</section>
</main>
<?php get_footer(); ?>
