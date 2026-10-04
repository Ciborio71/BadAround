<?php
/**
 * Template Name: BadAround — Come funziona
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$report_url   = home_url( '/segnala-un-evento/' );
$map_url      = home_url( '/mappa/' );
$sentinel_url = home_url( '/sentinelle/' );

$steps = array(
	array( 'n'=>'1', 'icon'=>'✎', 'title'=>'Racconta cosa è successo', 'text'=>'Scegli la categoria e aggiungi le informazioni utili. Puoi indicare il luogo e, quando serve, allegare immagini.' ),
	array( 'n'=>'2', 'icon'=>'●', 'title'=>'L’evento appare sulla mappa', 'text'=>'La segnalazione diventa consultabile attraverso la mappa e le altre viste pubbliche di BadAround.' ),
	array( 'n'=>'3', 'icon'=>'◎', 'title'=>'La community può scoprirlo', 'text'=>'Le persone possono trovare le segnalazioni tramite mappa, ricerca e filtri e conoscere meglio ciò che accade intorno a loro.' ),
	array( 'n'=>'4', 'icon'=>'◉', 'title'=>'Le Sentinelle possono essere avvisate', 'text'=>'Chi ha attivato una Sentinella compatibile può ricevere gli aggiornamenti previsti senza dover controllare continuamente la mappa.' ),
);

$categories = array(
	array( 'class'=>'vehicles', 'icon'=>'🚗', 'title'=>'Veicoli', 'text'=>'Auto, moto e mezzi: furti, danneggiamenti e altre situazioni utili da condividere.' ),
	array( 'class'=>'places', 'icon'=>'⌂', 'title'=>'Case e attività', 'text'=>'Abitazioni, negozi, uffici e altri luoghi interessati da eventi o situazioni da segnalare.' ),
	array( 'class'=>'hazards', 'icon'=>'!', 'title'=>'Pericoli', 'text'=>'Ostacoli, rischi stradali, ambientali o altre situazioni che richiedono attenzione.' ),
	array( 'class'=>'public', 'icon'=>'♟', 'title'=>'Spazi pubblici', 'text'=>'Aree comuni, vandalismi, degrado e situazioni che interessano la vita del territorio.' ),
	array( 'class'=>'animals', 'icon'=>'●', 'title'=>'Animali', 'text'=>'Animali smarriti, avvistati o situazioni in cui una segnalazione può essere utile.' ),
	array( 'class'=>'objects', 'icon'=>'▤', 'title'=>'Oggetti e documenti', 'text'=>'Chiavi, documenti, dispositivi e altri oggetti personali smarriti o ritrovati.' ),
);
?>
<main id="main-content" class="ba-how">
	<section class="ba-how-hero">
		<div class="ba-container ba-how-hero__grid">
			<div class="ba-how-hero__copy">
				<p class="ba-how-eyebrow">COME FUNZIONA</p>
				<h1>Insieme rendiamo il territorio più <span>consapevole.</span></h1>
				<p class="ba-how-lead">Segnala ciò che accade intorno a te, scopri gli eventi condivisi dalla community e aiuta altre persone a tenere d’occhio il territorio.</p>
				<p class="ba-how-free"><strong>BadAround è gratuito</strong> e aperto alla comunità.</p>
				<div class="ba-how-actions">
					<a class="ba-button ba-how-primary" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
					<a class="ba-button ba-button--outline" href="<?php echo esc_url( $map_url ); ?>">Esplora la mappa</a>
				</div>
			</div>
			<div class="ba-how-hero__visual" aria-label="Una community condivide segnalazioni utili sul territorio">
				<div class="ba-how-orbit ba-how-orbit--one"></div>
				<div class="ba-how-orbit ba-how-orbit--two"></div>
				<div class="ba-how-phone">
					<div class="ba-how-phone__speaker"></div>
					<div class="ba-how-phone__map">
						<span class="ba-how-road ba-how-road--a"></span><span class="ba-how-road ba-how-road--b"></span><span class="ba-how-road ba-how-road--c"></span>
						<span class="ba-how-pin ba-how-pin--1">!</span><span class="ba-how-pin ba-how-pin--2">●</span><span class="ba-how-pin ba-how-pin--3">⌂</span><span class="ba-how-pin ba-how-pin--4">●</span>
						<div class="ba-how-preview"><strong>Evento segnalato</strong><span>Pubblicato dalla community</span></div>
					</div>
				</div>
				<div class="ba-how-callout ba-how-callout--top">Eventi reali<br>segnalati dalla community</div>
				<div class="ba-how-callout ba-how-callout--side">Più occhi sul territorio.<br><strong>Più informazioni utili.</strong></div>
			</div>
		</div>
	</section>

	<section class="ba-how-section ba-how-section--soft">
		<div class="ba-container">
			<p class="ba-how-eyebrow">IL PERCORSO</p>
			<h2>In pochi passaggi</h2>
			<p class="ba-how-intro">Dalla segnalazione alla mappa, tutto è pensato per essere semplice, chiaro e utile alla community.</p>
			<div class="ba-how-steps">
				<?php foreach ( $steps as $step ) : ?>
					<article class="ba-how-step">
						<span class="ba-how-step__number"><?php echo esc_html( $step['n'] ); ?></span>
						<span class="ba-how-step__icon" aria-hidden="true"><?php echo esc_html( $step['icon'] ); ?></span>
						<h3><?php echo esc_html( $step['title'] ); ?></h3>
						<p><?php echo esc_html( $step['text'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="ba-how-section ba-how-section--white">
		<div class="ba-container">
			<p class="ba-how-eyebrow">LE CATEGORIE PRINCIPALI</p>
			<h2>Cosa puoi segnalare</h2>
			<p class="ba-how-intro">Eventi e situazioni che possono essere utili a chi vive, lavora o si muove nello stesso territorio.</p>
			<div class="ba-how-categories">
				<?php foreach ( $categories as $category ) : ?>
					<article class="ba-how-category ba-how-category--<?php echo esc_attr( $category['class'] ); ?>">
						<span class="ba-how-category__icon" aria-hidden="true"><?php echo esc_html( $category['icon'] ); ?></span>
						<div><h3><?php echo esc_html( $category['title'] ); ?></h3><p><?php echo esc_html( $category['text'] ); ?></p></div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="ba-how-section ba-how-social">
		<div class="ba-container ba-how-split">
			<div class="ba-how-social__visual" aria-hidden="true"><span>↗</span><strong>BadAround</strong><small>La segnalazione raggiunge più persone</small></div>
			<div>
				<p class="ba-how-eyebrow">PIÙ VISIBILITÀ ALLA SEGNALAZIONE</p>
				<h2>Dalla piattaforma alla community.</h2>
				<p class="ba-how-intro">BadAround nasce per dare alle segnalazioni utili la massima rapidità e visibilità. I contenuti pubblicati possono essere rilanciati anche attraverso i canali social BadAround e la relativa community, ampliandone la diffusione oltre la piattaforma.</p>
			</div>
		</div>
	</section>

	<section class="ba-how-section ba-how-sentinel">
		<div class="ba-container ba-how-split">
			<div>
				<p class="ba-how-eyebrow">SENTINELLE BADAROUND</p>
				<h2>Non devi controllare continuamente la mappa.</h2>
				<p class="ba-how-intro">Attiva gratuitamente una Sentinella per ricevere gli aggiornamenti previsti per la zona e gli interessi che hai scelto. Non serve creare un account.</p>
				<p class="ba-how-note">La tua identità non viene resa pubblica.</p>
				<a class="ba-button" href="<?php echo esc_url( $sentinel_url ); ?>">Attiva una Sentinella</a>
			</div>
			<div class="ba-how-sentinel__visual" aria-hidden="true"><span class="ba-how-sentinel__pin">⌖</span><span class="ba-how-sentinel__eye">◉</span></div>
		</div>
	</section>

	<section class="ba-how-section ba-how-privacy">
		<div class="ba-container ba-how-split">
			<div>
				<p class="ba-how-eyebrow">LA TUA PRIVACY</p>
				<h2>Le informazioni pubbliche non sono tutte le informazioni raccolte.</h2>
				<p class="ba-how-intro">BadAround separa ciò che serve alla community dai dati che devono restare riservati e applica la moderazione prima della pubblicazione.</p>
			</div>
			<ul class="ba-how-checks">
				<li><span>✓</span> Posizione pubblica approssimata</li>
				<li><span>✓</span> Coordinate precise non esposte pubblicamente</li>
				<li><span>✓</span> Targa completa non mostrata nella vista pubblica</li>
				<li><span>✓</span> Immagini pubblicate solo dopo moderazione</li>
				<li><span>✓</span> Identità della Sentinella non resa pubblica</li>
			</ul>
		</div>
	</section>

	<section class="ba-how-emergency">
		<div class="ba-container ba-how-emergency__grid">
			<div><p class="ba-how-eyebrow">IN CASO DI EMERGENZA</p><h2>BadAround informa. Non sostituisce i servizi di emergenza.</h2><p>Se c’è un pericolo immediato, utilizza il 112 o il servizio di emergenza competente.</p></div>
			<div class="ba-how-emergency__card"><span aria-hidden="true">☎</span><div><small>Emergenza immediata?</small><strong>Chiama il 112</strong></div></div>
		</div>
	</section>

	<section class="ba-how-final">
		<div class="ba-container ba-how-final__grid">
			<div><p class="ba-how-eyebrow">PRONTO A CONTRIBUIRE?</p><h2>Ogni segnalazione può essere utile a qualcuno.</h2><p>Condividere un’informazione richiede pochi minuti. Partecipare a BadAround è gratuito.</p></div>
			<div class="ba-how-actions"><a class="ba-button ba-how-final__primary" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a><a class="ba-button ba-how-final__secondary" href="<?php echo esc_url( $map_url ); ?>">Esplora la mappa</a></div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
