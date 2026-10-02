<?php
/* Template Name: BadAround - Segnala evento */
get_header();
$form_id = badaround_report_form_id();
?>
<main class="ba-page ba-report-page" id="main-content">
	<section class="ba-report-section" aria-labelledby="ba-report-title">
		<div class="ba-container--wide">
			<div class="ba-report-shell">
				<header class="ba-report-shell__head">
					<div class="ba-report-shell__title">
						<span class="ba-report-shell__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22" focusable="false"><path d="M4 20h4l11-11-4-4L4 16v4Zm9-13 4 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
						<div>
							<p class="ba-eyebrow">Segnala un evento</p>
							<h1 id="ba-report-title">Segnala un evento</h1>
							<p>Ti guideremo passo passo. Mostreremo solo le domande necessarie.</p>
						</div>
					</div>
					<div class="ba-report-shell__save" aria-label="Salvataggio e ripresa del modulo">
						<span aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M7 18h10a4 4 0 0 0 .6-8 6 6 0 0 0-11.4-1.6A4.5 4.5 0 0 0 7 18Z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></span>
						<div><strong>Salva e riprendi</strong><small>Puoi continuare più tardi</small></div>
					</div>
				</header>

				<div class="ba-report-shell__body">
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

				<footer class="ba-report-shell__foot">
					<p><strong>Privacy:</strong> la posizione pubblica viene mostrata in modo approssimativo. Civico e dati di contatto non vengono pubblicati automaticamente.</p>
				</footer>
			</div>
		</div>
	</section>
</main>
<?php get_footer(); ?>
