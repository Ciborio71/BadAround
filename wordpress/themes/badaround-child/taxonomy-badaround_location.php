<?php
get_header();
$term = get_queried_object();
?>
<main class="ba-page" id="main-content">
	<header class="ba-territory-head"><div class="ba-container"><div class="ba-breadcrumb">Italia › Territorio</div><div class="ba-eyebrow">Area territoriale</div><h1>Segnalazioni in <?php echo esc_html( $term->name ?? 'zona' ); ?></h1><p class="ba-lead">Eventi, richieste di aiuto e aggiornamenti pubblicati dalla comunità.</p><div class="ba-actions"><a class="ba-button ba-button--outline" href="#">Attiva alert per questa zona</a><a class="ba-button" href="<?php echo esc_url( home_url( '/segnala-un-evento/' ) ); ?>">Segnala in questa zona</a></div></div></header>
	<section class="ba-section--tight"><div class="ba-container ba-statbar"><div class="ba-card ba-stat"><strong><?php echo esc_html( $term->count ?? 0 ); ?></strong>segnalazioni</div><div class="ba-card ba-stat"><strong>—</strong>richieste testimoni</div><div class="ba-card ba-stat"><strong>—</strong>eventi risolti</div><div class="ba-card ba-stat"><strong>—</strong>nuove oggi</div></div></section>
	<section class="ba-section--tight"><div class="ba-container ba-territory-layout"><div class="ba-map"><div class="ba-map__notice">Posizioni approssimative.</div></div><div class="ba-results"><?php if ( have_posts() ) : while ( have_posts() ) : the_post(); get_template_part( 'template-parts/components/event-card', null, array( 'title' => get_the_title(), 'url' => get_permalink(), 'meta' => get_the_date(), 'image' => get_the_post_thumbnail_url( get_the_ID(), 'medium' ) ) ); endwhile; else : ?><div class="ba-card ba-panel"><h2>Nessuna segnalazione pubblicata</h2><p>Puoi attivare un alert o essere il primo a contribuire.</p></div><?php endif; ?></div></div></section>
</main>
<?php get_footer(); ?>

