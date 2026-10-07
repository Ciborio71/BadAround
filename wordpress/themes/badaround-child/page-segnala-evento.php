<?php
/* Template Name: BadAround - Segnala evento */
if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
$form_id = badaround_native_report_qa_enabled() ? 0 : badaround_report_form_id();
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

		<?php if ( badaround_native_report_qa_enabled() ) : ?>
		<div class="ba-report-stage"><?php get_template_part( 'template-parts/native-report/shell' ); ?></div>
		<?php else : ?>
		<nav class="ba-report-steps" aria-label="Avanzamento della segnalazione">
			<ol>
				<li class="is-active" data-ba-report-step="1" aria-current="step">
					<span class="ba-report-step__number">1</span>
					<span class="ba-report-step__label">Evento</span>
				</li>
				<li data-ba-report-step="2">
					<span class="ba-report-step__number">2</span>
					<span class="ba-report-step__label">Luogo e momento</span>
				</li>
				<li data-ba-report-step="3">
					<span class="ba-report-step__number">3</span>
					<span class="ba-report-step__label">Dettagli</span>
				</li>
				<li data-ba-report-step="4">
					<span class="ba-report-step__number">4</span>
					<span class="ba-report-step__label">Contenuti</span>
				</li>
				<li data-ba-report-step="5">
					<span class="ba-report-step__number">5</span>
					<span class="ba-report-step__label">Verifica</span>
				</li>
			</ol>
		</nav>

		<div class="ba-report-stage">
			<header class="ba-report-stage__intro">
				<div>
					<span class="ba-report-stage__eyebrow" data-ba-report-eyebrow>SEGNALA UN EVENTO</span>
					<h2 data-ba-report-title>Cosa vuoi segnalare?</h2>
					<div class="ba-report-stage__copy" data-ba-report-copy>
						<p>Un <strong>furto</strong>, un <strong>danno</strong>, un <strong>comportamento sospetto</strong>, uno <strong>smarrimento</strong>, un <strong>pericolo</strong> oppure stai <strong>cercando testimoni</strong>?</p>
						
						<p>Ti guideremo noi e ti mostreremo solo le domande necessarie per segnalare l'evento alla community di BadAround.</p>
					</div>
				</div>
				<div class="ba-report-stage__actions">
					<button class="ba-report-save" type="button" data-ba-report-save>
						<span class="ba-report-save__icon" aria-hidden="true">☁</span>
						<span>
							<strong>Salva bozza</strong>
							<small>Riprendi più tardi</small>
						</span>
					</button>
					<div class="ba-report-context" data-ba-report-context hidden></div>
				</div>
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
		</div>

		<?php endif; ?>
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
