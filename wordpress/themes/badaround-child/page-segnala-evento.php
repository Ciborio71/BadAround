<?php
/* Template Name: BadAround - Segnala evento */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
$form_id = badaround_report_form_id();
?>
<main class="ba-report-app" id="main-content">
	<section class="ba-report-stage" aria-labelledby="ba-report-title">
		<div class="ba-form-wrapper">
			<header class="ba-report-header">
				<div class="ba-report-header__meta">
					<span data-ba-report-step-label>SEGNALAZIONE CIVICA • PASSO 1</span>
					<button type="button" data-ba-report-save>Salva bozza</button>
				</div>

				<h1 id="ba-report-title" data-ba-report-title>Cosa riguarda la segnalazione?</h1>
				<p data-ba-report-copy>Tocca la categoria: mostreremo solo le domande necessarie.</p>
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
				<div>
					<strong>Privacy &amp; sicurezza protette d'ufficio</strong>
					<p>La posizione esatta non sarà mai resa pubblica per le categorie riservate.</p>
				</div>
			</footer>
		</div>
	</section>
</main>
<?php get_footer(); ?>
