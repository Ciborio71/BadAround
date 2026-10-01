<?php
/* Template Name: BadAround - Segnala evento */
get_header();
$form_id = badaround_report_form_id();
?>
<main class="ba-page" id="main-content">
	<section class="ba-section"><div class="ba-container ba-report-shell"><header class="ba-report-intro"><div class="ba-eyebrow">Contribuisci alla comunità</div><h1>Segnala un evento</h1><p class="ba-lead">Ti guideremo passo passo. Le informazioni sensibili e il civico non saranno mostrati pubblicamente.</p></header><div class="ba-card ba-report-mount"><?php if ( $form_id && function_exists( 'wpforms' ) ) { wpforms()->frontend->output( $form_id ); } else { ?><div class="ba-report-placeholder"><strong>Modulo WPForms in preparazione</strong><p>Quando il modulo sarà pronto, inseriremo qui il suo ID senza modificare il layout.</p></div><?php } ?></div></div></section>
</main>
<?php get_footer(); ?>

