<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

the_post();

$post_id = get_the_ID();
$title   = get_the_title();
$report_url = home_url( '/segnala-un-evento/' );

$public_location = get_post_meta( $post_id, 'ba_public_location_label', true );
if ( ! $public_location ) { $public_location = get_post_meta( $post_id, 'ba_public_area', true ); }
if ( ! $public_location ) { $public_location = 'Posizione pubblica approssimativa'; }

$status = get_post_meta( $post_id, 'ba_public_status', true );
if ( ! $status ) { $status = 'Evento attivo'; }

$category = '';
foreach ( array( 'ba_categoria', 'badaround_category', 'category' ) as $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$category = $terms[0]->name;
		break;
	}
}
if ( ! $category ) { $category = 'Segnalazione'; }

$territory = '';
foreach ( array( 'ba_territorio', 'badaround_location' ) as $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		$territory = $terms[0]->name;
		break;
	}
}

$event_date = get_post_meta( $post_id, 'ba_public_event_date', true );
if ( ! $event_date ) { $event_date = get_the_date(); }

$has_thumb = has_post_thumbnail();
?>
<main class="ba-page ba-event-detail" id="main-content">
	<header class="ba-event-hero">
		<div class="ba-container">
			<nav class="ba-breadcrumb" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
				<span aria-hidden="true">›</span>
				<a href="<?php echo esc_url( home_url( '/#segnalazioni' ) ); ?>">Segnalazioni</a>
				<span aria-hidden="true">›</span>
				<span aria-current="page"><?php echo esc_html( wp_trim_words( $title, 5, '…' ) ); ?></span>
			</nav>

			<div class="ba-event-hero__layout">
				<div class="ba-event-hero__copy">
					<div class="ba-event-kickers">
						<span class="ba-badge ba-badge--urgent"><?php echo esc_html( $status ); ?></span>
						<span class="ba-event-category"><?php echo esc_html( $category ); ?></span>
					</div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<div class="ba-event-meta">
						<span>
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" fill="currentColor"/></svg>
							<?php echo esc_html( $territory ?: $public_location ); ?>
						</span>
						<span>
							<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
							<?php echo esc_html( $event_date ); ?>
						</span>
					</div>
				</div>

				<div class="ba-event-hero__actions">
					<button class="ba-event-icon-button" type="button" aria-label="Salva segnalazione">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v16l-5-3-5 3V4Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
					</button>
					<button class="ba-event-icon-button" type="button" aria-label="Condividi segnalazione">
						<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="2.3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="6" cy="12" r="2.3" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="18" cy="19" r="2.3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m8 11 8-5m-8 7 8 5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
					</button>
				</div>
			</div>
		</div>
	</header>

	<section class="ba-event-body">
		<div class="ba-container ba-event-layout">
			<div class="ba-event-main">
				<?php if ( $has_thumb ) : ?>
					<figure class="ba-event-media">
						<?php the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); ?>
					</figure>
				<?php else : ?>
					<div class="ba-event-media ba-event-media--placeholder" aria-hidden="true">
						<svg viewBox="0 0 90 90"><path d="M18 65 38 45l13 13 9-9 13 16H18Z" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="60" cy="28" r="7" fill="none" stroke="currentColor" stroke-width="2"/></svg>
					</div>
				<?php endif; ?>

				<section class="ba-card ba-event-section">
					<div class="ba-event-section__head">
						<h2>Che cosa è successo</h2>
						<span class="ba-event-section__eyebrow">Segnalazione pubblica</span>
					</div>
					<div class="ba-event-prose"><?php the_content(); ?></div>
				</section>

				<section class="ba-card ba-event-section">
					<div class="ba-event-section__head">
						<h2>Informazioni utili</h2>
						<span class="ba-event-section__eyebrow">Dati moderati</span>
					</div>
					<dl class="ba-event-facts">
						<div><dt>Categoria</dt><dd><?php echo esc_html( $category ); ?></dd></div>
						<div><dt>Zona</dt><dd><?php echo esc_html( $territory ?: $public_location ); ?></dd></div>
						<div><dt>Stato</dt><dd><?php echo esc_html( $status ); ?></dd></div>
						<div><dt>Data</dt><dd><?php echo esc_html( $event_date ); ?></dd></div>
					</dl>
				</section>

				<section class="ba-card ba-event-map-section">
					<div class="ba-event-section__head">
						<div>
							<h2>Zona dell'evento</h2>
							<p>La posizione mostrata pubblicamente può essere approssimata per proteggere i dati sensibili.</p>
						</div>
					</div>
					<div class="ba-event-map" role="img" aria-label="Mappa indicativa della zona dell'evento">
						<div class="ba-event-map__grid" aria-hidden="true"></div>
						<div class="ba-event-map__pin" aria-hidden="true"></div>
						<div class="ba-event-map__privacy">Posizione pubblica approssimata</div>
					</div>
				</section>

				<section class="ba-card ba-event-section ba-event-updates">
					<div class="ba-event-section__head">
						<h2>Aggiornamenti</h2>
						<span class="ba-event-section__eyebrow">Timeline</span>
					</div>
					<ol>
						<li>
							<span class="ba-event-update__dot"></span>
							<div><strong>Segnalazione pubblicata</strong><p>La segnalazione è stata moderata e resa disponibile alla community.</p><small><?php echo esc_html( get_the_date() ); ?></small></div>
						</li>
					</ol>
				</section>
			</div>

			<aside class="ba-event-aside" aria-label="Azioni sulla segnalazione">
				<section class="ba-card ba-event-help">
					<span class="ba-event-help__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24"><path d="M3 12s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="2.5" fill="currentColor"/></svg>
					</span>
					<h2>Hai informazioni utili?</h2>
					<p>Se hai visto qualcosa o disponi di elementi utili, puoi contribuire senza pubblicare dati personali.</p>
					<a class="ba-button ba-button--urgent" href="<?php echo esc_url( $report_url ); ?>">Invia informazioni</a>
					<p class="ba-event-help__privacy">I contatti non vengono mostrati pubblicamente.</p>
				</section>

				<section class="ba-card ba-event-watch">
					<div class="ba-event-watch__head">
						<span aria-hidden="true">◎</span>
						<div><strong>Segui questa zona</strong><small>Sentinella</small></div>
					</div>
					<p>Ricevi un avviso quando vengono pubblicati nuovi eventi rilevanti nell'area.</p>
					<button class="ba-button ba-button--outline" type="button">Attiva gli alert</button>
				</section>

				<section class="ba-card ba-event-safety">
					<h2>Serve aiuto immediato?</h2>
					<p>BadAround non sostituisce i servizi di emergenza. In caso di pericolo immediato contatta il 112 o l'autorità competente.</p>
				</section>

				<aside class="ba-card ba-event-ad" aria-label="Contenuto sponsorizzato">
					<span>Contenuto sponsorizzato</span>
					<strong>Attività della zona</strong>
					<p>Spazio riservato a servizi locali pertinenti al territorio.</p>
					<a href="#">Scopri di più →</a>
				</aside>
			</aside>
		</div>
	</section>
</main>
