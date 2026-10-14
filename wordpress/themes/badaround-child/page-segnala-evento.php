<?php
/* Template Name: BadAround - Segnala evento */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>
<main class="ba-report-app" id="main-content">
	<section class="ba-report-workspace ba-container--wide" aria-labelledby="ba-report-page-title">
		<header class="ba-report-workspace__head">
			<div class="ba-report-workspace__identity">
				<span class="ba-report-workspace__icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false">
						<path d="M5 4h9a2 2 0 0 1 2 2v2M5 4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2v-3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
						<path d="m13.5 13.5 6-6 2 2-6 6-3 1 1-3Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
						<path d="M7 9h5M7 13h3M7 17h4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
					</svg>
				</span>
				<div>
					<h1 id="ba-report-page-title">Segnala un evento</h1>
					<p>Aiuta la tua comunità a essere più sicura e informata.</p>
				</div>
			</div>

		</header>

		<div class="ba-report-stage"><?php get_template_part( 'template-parts/native-report/shell' ); ?></div>
		
		<footer class="ba-report-trust">
			<div>
				<span aria-hidden="true">🔒</span>
				<p><strong>Privacy by design.</strong> Dati personali, posizione precisa e informazioni riservate non vengono pubblicati automaticamente.</p>
			</div>
			<a href="<?php echo esc_url( home_url( '/come-funziona/' ) ); ?>">Come funziona la moderazione</a>
		</footer>
	</section>
</main>
<?php get_footer(); ?>
