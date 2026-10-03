<?php
/**
 * Template Name: BadAround — Mappa
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$report_url = home_url( '/segnala-un-evento/' );

$events = new WP_Query(
	array(
		'post_type'      => array( 'ba_evento', 'badaround_event' ),
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'no_found_rows'  => true,
	)
);
?>
<main class="ba-map-page" id="main-content">
	<section class="ba-map-page__toolbar">
		<div class="ba-container--wide">
			<div class="ba-map-toolbar">
				<div class="ba-map-toolbar__title">
					<span class="ba-eyebrow">Mappa BadAround</span>
					<h1>Cosa succede intorno a te</h1>
				</div>

				<form class="ba-map-search" role="search" action="#" onsubmit="return false;">
					<label class="ba-sr-only" for="ba-map-search-input">Cerca zona o località</label>
					<span aria-hidden="true">
						<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m16 16 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
					</span>
					<input id="ba-map-search-input" type="search" placeholder="Cerca una zona, un Comune o una località…">
					<button class="ba-button" type="submit">Cerca</button>
				</form>

				<div class="ba-map-toolbar__actions">
					<button class="ba-map-tool-button" type="button" data-ba-map-locate>
						<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
						<span>Vicino a me</span>
					</button>
					<a class="ba-button ba-button--urgent ba-map-report-cta" href="<?php echo esc_url( $report_url ); ?>">Segnala</a>
				</div>
			</div>

			<div class="ba-map-filters" aria-label="Filtri mappa">
				<button class="ba-map-filter is-active" type="button" data-ba-map-filter="all" aria-pressed="true">Tutte</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="furti" aria-pressed="false">Furti</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="sicurezza" aria-pressed="false">Sicurezza</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="degrado" aria-pressed="false">Degrado</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="pericoli" aria-pressed="false">Pericoli</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="animali" aria-pressed="false">Animali</button>
				<button class="ba-map-filter" type="button" data-ba-map-filter="mobilita" aria-pressed="false">Mobilità</button>
				<button class="ba-map-filter ba-map-filter--more" type="button" data-ba-map-more>Altri filtri</button>
			</div>
		</div>
	</section>

	<section class="ba-map-workspace">
		<div class="ba-map-results-panel" data-ba-map-results>
			<div class="ba-map-results-panel__top">
				<div>
					<span class="ba-map-results-panel__eyebrow">Area visualizzata</span>
					<h2>Segnalazioni recenti</h2>
				</div>
				<button class="ba-map-results-panel__close" type="button" data-ba-map-close aria-label="Chiudi elenco">×</button>
			</div>

			<div class="ba-map-results-panel__meta">
				<span><?php echo esc_html( (string) $events->post_count ); ?> risultati</span>
				<button type="button">Più recenti ▾</button>
			</div>

			<div class="ba-map-results-list">
				<?php if ( $events->have_posts() ) : ?>
					<?php while ( $events->have_posts() ) : $events->the_post(); ?>
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
					<?php wp_reset_postdata(); ?>
				<?php else : ?>
					<div class="ba-map-empty">
						<span aria-hidden="true">◎</span>
						<h3>Nessuna segnalazione visibile</h3>
						<p>Prova a cambiare zona o filtri, oppure contribuisci con una nuova segnalazione.</p>
						<a class="ba-button" href="<?php echo esc_url( $report_url ); ?>">Segnala un evento</a>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="ba-map-canvas" data-ba-map-canvas aria-label="Mappa interattiva delle segnalazioni">
			<div class="ba-map-canvas__grid" aria-hidden="true"></div>
			<div class="ba-map-canvas__coast" aria-hidden="true"></div>

			<button class="ba-map-cluster ba-map-cluster--1" type="button" aria-label="12 segnalazioni in questa zona">12</button>
			<button class="ba-map-cluster ba-map-cluster--2" type="button" aria-label="5 segnalazioni in questa zona">5</button>
			<button class="ba-map-pin ba-map-pin--1" type="button" aria-label="Apri segnalazione"></button>
			<button class="ba-map-pin ba-map-pin--2" type="button" aria-label="Apri segnalazione"></button>
			<button class="ba-map-pin ba-map-pin--3" type="button" aria-label="Apri segnalazione"></button>

			<div class="ba-map-zoom" aria-label="Controlli zoom">
				<button type="button" aria-label="Zoom avanti">+</button>
				<button type="button" aria-label="Zoom indietro">−</button>
			</div>

			<button class="ba-map-mobile-results" type="button" data-ba-map-open>
				<span><?php echo esc_html( (string) $events->post_count ); ?> risultati</span>
				<strong>Mostra elenco</strong>
			</button>

			<div class="ba-map-privacy-note">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.6 2.8 7.8 7 10 4.2-2.2 7-5.4 7-10V6l-7-3Z" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>
				<span>Le posizioni pubbliche possono essere approssimate.</span>
			</div>

			<div class="ba-map-demo-note">Anteprima UI — collegamento alla mappa reale previsto nello strato applicativo.</div>
		</div>
	</section>

	<div class="ba-map-mobile-backdrop" data-ba-map-backdrop hidden></div>
</main>
<?php get_footer(); ?>
