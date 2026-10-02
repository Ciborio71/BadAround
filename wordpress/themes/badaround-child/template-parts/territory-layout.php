<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

$term = get_queried_object();
if ( ! $term || empty( $term->term_id ) ) { return; }

$taxonomy = $term->taxonomy;
$term_link = get_term_link( $term );
$report_url = home_url( '/segnala-un-evento/' );

$ancestors = array_reverse( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
$breadcrumbs = array();
foreach ( $ancestors as $ancestor_id ) {
	$ancestor = get_term( $ancestor_id, $taxonomy );
	if ( $ancestor && ! is_wp_error( $ancestor ) ) {
		$breadcrumbs[] = $ancestor;
	}
}

$term_count = isset( $term->count ) ? (int) $term->count : 0;
$today_count = 0;
?>
<main class="ba-page ba-territory-page" id="main-content">
	<header class="ba-territory-hero">
		<div class="ba-container">
			<nav class="ba-breadcrumb" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
				<span aria-hidden="true">›</span>
				<?php foreach ( $breadcrumbs as $ancestor ) : ?>
					<a href="<?php echo esc_url( get_term_link( $ancestor ) ); ?>"><?php echo esc_html( $ancestor->name ); ?></a>
					<span aria-hidden="true">›</span>
				<?php endforeach; ?>
				<span aria-current="page"><?php echo esc_html( $term->name ); ?></span>
			</nav>

			<div class="ba-territory-hero__grid">
				<div>
					<div class="ba-eyebrow">Intelligence locale</div>
					<h1>Cosa succede a <?php echo esc_html( $term->name ); ?></h1>
					<p class="ba-lead">Segnalazioni, richieste di aiuto e aggiornamenti utili dalla zona, raccolti e moderati dalla community.</p>

					<div class="ba-territory-hero__actions">
						<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
						<button class="ba-button ba-button--outline" type="button">Segui questa zona</button>
					</div>
				</div>

				<div class="ba-territory-radar" aria-hidden="true">
					<div class="ba-territory-radar__ring ba-territory-radar__ring--1"></div>
					<div class="ba-territory-radar__ring ba-territory-radar__ring--2"></div>
					<div class="ba-territory-radar__ring ba-territory-radar__ring--3"></div>
					<div class="ba-territory-radar__pulse"></div>
				</div>
			</div>
		</div>
	</header>

	<section class="ba-territory-stats">
		<div class="ba-container ba-territory-statgrid">
			<div class="ba-card ba-territory-stat">
				<span>Segnalazioni attive</span>
				<strong><?php echo esc_html( $term_count ); ?></strong>
				<small>nella zona</small>
			</div>
			<div class="ba-card ba-territory-stat">
				<span>Nuove oggi</span>
				<strong><?php echo esc_html( $today_count ?: '—' ); ?></strong>
				<small>ultime 24 ore</small>
			</div>
			<div class="ba-card ba-territory-stat">
				<span>Richieste testimoni</span>
				<strong>—</strong>
				<small>da verificare</small>
			</div>
			<div class="ba-card ba-territory-stat">
				<span>Risolte</span>
				<strong>—</strong>
				<small>negli ultimi 30 giorni</small>
			</div>
		</div>
	</section>

	<section class="ba-territory-live">
		<div class="ba-container">
			<div class="ba-territory-section-head">
				<div>
					<span class="ba-eyebrow">Radar locale</span>
					<h2>Segnalazioni nella zona</h2>
				</div>
				<div class="ba-filterbar" aria-label="Filtra segnalazioni">
					<button class="ba-filter" type="button" data-ba-filter aria-pressed="true">Tutte</button>
					<button class="ba-filter" type="button" data-ba-filter aria-pressed="false">Furti</button>
					<button class="ba-filter" type="button" data-ba-filter aria-pressed="false">Sicurezza</button>
					<button class="ba-filter" type="button" data-ba-filter aria-pressed="false">Degrado</button>
					<button class="ba-filter" type="button" data-ba-filter aria-pressed="false">Pericoli</button>
				</div>
			</div>

			<div class="ba-territory-live__grid">
				<div class="ba-territory-map ba-card" aria-label="Mappa delle segnalazioni">
					<div class="ba-territory-map__grid" aria-hidden="true"></div>
					<div class="ba-territory-map__water" aria-hidden="true"></div>
					<span class="ba-territory-pin ba-territory-pin--1" aria-hidden="true"></span>
					<span class="ba-territory-pin ba-territory-pin--2" aria-hidden="true"></span>
					<span class="ba-territory-pin ba-territory-pin--3" aria-hidden="true"></span>
					<div class="ba-territory-map__label">
						<strong><?php echo esc_html( $term->name ); ?></strong>
						<span>Posizioni pubbliche approssimate</span>
					</div>
				</div>

				<div class="ba-territory-results">
					<div class="ba-territory-results__head">
						<strong>Più recenti</strong>
						<span><?php echo esc_html( $term_count ); ?> segnalazioni</span>
					</div>

					<div class="ba-results">
						<?php if ( have_posts() ) : ?>
							<?php while ( have_posts() ) : the_post(); ?>
								<?php
								$status_label = get_post_meta( get_the_ID(), 'ba_public_status', true );
								$status_label = $status_label ?: 'Segnalazione';
								get_template_part(
									'template-parts/components/event-card',
									null,
									array(
										'title'        => get_the_title(),
										'url'          => get_permalink(),
										'status'       => 'info',
										'status_label' => $status_label,
										'meta'         => get_the_date(),
										'image'        => get_the_post_thumbnail_url( get_the_ID(), 'medium' ),
									)
								);
								?>
							<?php endwhile; ?>
						<?php else : ?>
							<div class="ba-card ba-territory-empty">
								<span class="ba-territory-empty__icon" aria-hidden="true">◎</span>
								<h3>Nessuna segnalazione pubblicata</h3>
								<p>Puoi seguire questa zona oppure essere il primo a contribuire.</p>
								<a class="ba-button" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
							</div>
						<?php endif; ?>
					</div>

					<?php if ( have_posts() ) : ?>
						<div class="ba-territory-results__footer">
							<?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>

	<section class="ba-territory-insights">
		<div class="ba-container ba-territory-insights__grid">
			<section class="ba-card ba-territory-trend">
				<div class="ba-territory-section-head ba-territory-section-head--compact">
					<div>
						<span class="ba-eyebrow">Andamento locale</span>
						<h2>Ultimi 7 giorni</h2>
					</div>
					<span class="ba-territory-trend__period">Aggiornamento giornaliero</span>
				</div>
				<div class="ba-territory-bars" aria-label="Grafico dimostrativo dell'andamento delle segnalazioni">
					<div><span style="height:32%"></span><small>Lun</small></div>
					<div><span style="height:48%"></span><small>Mar</small></div>
					<div><span style="height:41%"></span><small>Mer</small></div>
					<div><span style="height:68%"></span><small>Gio</small></div>
					<div><span style="height:54%"></span><small>Ven</small></div>
					<div><span style="height:78%"></span><small>Sab</small></div>
					<div><span style="height:61%"></span><small>Dom</small></div>
				</div>
				<p class="ba-territory-demo-note">Dati statistici reali disponibili quando il volume di segnalazioni sarà sufficiente.</p>
			</section>

			<section class="ba-card ba-territory-sentinel">
				<div class="ba-territory-sentinel__icon" aria-hidden="true">◎</div>
				<span class="ba-eyebrow">Sentinella</span>
				<h2>Segui <?php echo esc_html( $term->name ); ?></h2>
				<p>Ricevi un avviso quando viene pubblicata una nuova segnalazione rilevante in questa zona.</p>
				<button class="ba-button" type="button">Segui questa zona</button>
				<small>Potrai scegliere categorie e frequenza delle notifiche.</small>
			</section>

			<aside class="ba-card ba-territory-sponsor" aria-label="Contenuto sponsorizzato">
				<span>Contenuto sponsorizzato</span>
				<h2>Servizi vicino a te</h2>
				<p>Spazio riservato ad attività locali pertinenti a <?php echo esc_html( $term->name ); ?>.</p>
				<a href="#">Scopri di più →</a>
			</aside>
		</div>
	</section>
</main>
