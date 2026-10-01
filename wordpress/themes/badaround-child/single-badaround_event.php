<?php
get_header();
the_post();
?>
<main class="ba-page" id="main-content">
	<header class="ba-territory-head"><div class="ba-container"><div class="ba-breadcrumb">Home › Segnalazioni › Evento</div><span class="ba-badge ba-badge--urgent">Evento attivo</span><h1><?php the_title(); ?></h1><p class="ba-muted">Data e posizione pubblica approssimativa</p></div></header>
	<div class="ba-container ba-detail">
		<div class="ba-detail__main">
			<?php if ( has_post_thumbnail() ) : ?><figure class="ba-detail__gallery"><?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?></figure><?php endif; ?>
			<section class="ba-card ba-panel"><h2>Descrizione</h2><?php the_content(); ?></section>
			<section class="ba-card ba-panel"><h2>Informazioni disponibili</h2><dl class="ba-data"><div><dt>Categoria</dt><dd>Segnalazione</dd></div><div><dt>Zona</dt><dd>Posizione approssimativa</dd></div><div><dt>Stato</dt><dd>Attivo</dd></div><div><dt>Aggiornamento</dt><dd>Recente</dd></div></dl></section>
			<section class="ba-card ba-map"><div class="ba-map__notice">Il civico non viene mai pubblicato.</div></section>
		</div>
		<aside class="ba-detail__aside"><section class="ba-card ba-panel"><h2>Hai informazioni?</h2><p>Invia una segnalazione utile senza pubblicare dati personali.</p><a class="ba-button" href="#">Invia una segnalazione</a></section><section class="ba-card ba-sponsor"><small>Contenuto sponsorizzato</small><h3>Attività della zona</h3><p>Spazio pubblicitario geolocalizzato.</p><a href="#">Scopri di più</a></section></aside>
	</div>
</main>
<?php get_footer(); ?>

