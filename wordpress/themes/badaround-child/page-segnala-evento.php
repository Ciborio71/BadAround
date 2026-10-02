<?php
/* Template Name: BadAround - Segnala evento */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
$form_id = badaround_report_form_id();
?>
<main class="ba-report-app" id="main-content">
	<header class="ba-report-topbar">
		<div class="ba-report-topbar__brand">
			<span class="ba-report-topbar__mark" aria-hidden="true">◎</span>
			<div>
				<strong>Segnalazione civica</strong>
				<small data-ba-report-step-label>Passo 1 di 3</small>
			</div>
		</div>

		<div class="ba-report-topbar__actions">
			<button class="ba-report-topbar__save" type="button" data-ba-report-save>Salva bozza</button>
			<a class="ba-report-topbar__close" href="<?php echo esc_url( home_url( '/mappa/' ) ); ?>" aria-label="Chiudi segnalazione">×</a>
		</div>
	</header>

	<div class="ba-report-progress" aria-hidden="true">
		<span class="is-active" data-ba-report-progress="1"></span>
		<span data-ba-report-progress="2"></span>
		<span data-ba-report-progress="3"></span>
	</div>

	<section class="ba-report-stage" aria-labelledby="ba-report-title">
		<div class="ba-report-stage__inner">
			<header class="ba-report-intro">
				<p class="ba-report-intro__eyebrow" data-ba-report-eyebrow>Cosa</p>
				<h1 id="ba-report-title" data-ba-report-title>Cosa riguarda la segnalazione?</h1>
				<p data-ba-report-copy>Tocca il soggetto o l'ambiente coinvolto. Mostreremo solo le domande necessarie.</p>
			</header>

			<div class="ba-report-mount" data-form-id="<?php echo esc_attr( $form_id ); ?>">
				<?php
				if ( $form_id && function_exists( 'wpforms' ) ) {
					wpforms()->frontend->output( $form_id );
				} else {
					?>
					<div class="ba-report-placeholder" role="status">
						<strong>Modulo WPForms non disponibile</strong>
						<p>Il contenitore è predisposto per il modulo WPForms ID 6.</p>
					</div>
					<?php
				}
				?>
			</div>

			<footer class="ba-report-privacy">
				<span aria-hidden="true">🔒</span>
				<p><strong>Privacy & sicurezza</strong> La posizione pubblica viene mostrata in modo approssimativo. Civico, contatti e dati riservati non vengono pubblicati automaticamente.</p>
			</footer>
		</div>
	</section>
</main>
<?php get_footer(); ?>
