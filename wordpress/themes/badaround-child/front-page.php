<?php
get_header();
$demo_events = array(
	array( 'title' => 'Auto rubata nella zona Lungomare', 'status' => 'urgent', 'status_label' => 'Urgente', 'meta' => 'Oggi · posizione approssimativa' ),
	array( 'title' => 'Danneggiamento in un parcheggio', 'status' => 'witness', 'status_label' => 'Cerca testimoni', 'meta' => 'Ieri · circa 2,4 km' ),
	array( 'title' => 'Scooter ritrovato grazie a una segnalazione', 'status' => 'resolved', 'status_label' => 'Risolto', 'meta' => '2 giorni fa · comunità locale' ),
);
?>
<main class="ba-page" id="main-content">
	<section class="ba-hero">
		<div class="ba-hero__grid">
			<div class="ba-hero__content">
				<div class="ba-eyebrow">La comunità che osserva, segnala e aiuta</div>
				<h1 class="ba-title">Scopri cosa accade intorno a te</h1>
				<p class="ba-lead">Furti, danneggiamenti, veicoli sospetti e richieste di testimoni nella tua zona.</p>
				<form class="ba-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
					<label class="ba-sr-only" for="ba-location">Cerca una località</label>
					<input id="ba-location" name="s" type="search" placeholder="Cerca regione, provincia, comune o località">
					<button class="ba-button" type="submit">Cerca</button>
				</form>
				<div class="ba-actions"><a class="ba-button ba-button--outline" href="#ba-map">Usa la mia posizione</a><a class="ba-button ba-button--urgent" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala un evento</a></div>
			</div>
			<div id="ba-map" class="ba-map" aria-label="Mappa delle segnalazioni"><div class="ba-map__notice">Le posizioni pubbliche sono approssimative.</div></div>
		</div>
	</section>

	<section class="ba-section ba-surface">
		<div class="ba-container ba-stack">
			<div><div class="ba-eyebrow">Vicino a te</div><h2>Segnalazioni recenti</h2></div>
			<div class="ba-filterbar" aria-label="Filtra le segnalazioni"><button class="ba-filter" data-ba-filter aria-pressed="true">Tutte</button><button class="ba-filter" data-ba-filter aria-pressed="false">Furti</button><button class="ba-filter" data-ba-filter aria-pressed="false">Cerca testimoni</button><button class="ba-filter" data-ba-filter aria-pressed="false">Degrado</button></div>
			<div class="ba-grid ba-grid--3">
				<?php foreach ( $demo_events as $event ) { get_template_part( 'template-parts/components/event-card', null, $event ); } ?>
			</div>
		</div>
	</section>

	<section class="ba-section"><div class="ba-container ba-card ba-panel"><div class="ba-eyebrow">Sentinelle</div><h2>Ricevi soltanto gli alert che riguardano la tua zona</h2><p class="ba-lead">Scegli territori e categorie. Potrai modificare le preferenze in qualsiasi momento.</p><a class="ba-button" href="#">Attiva gli alert</a></div></section>
</main>
<?php get_footer(); ?>
