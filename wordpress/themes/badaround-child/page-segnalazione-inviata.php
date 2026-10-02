<?php
/**
 * Template Name: BadAround — Segnalazione inviata
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<main class="ba-state-focus-page" id="main-content">
	<div class="ba-container ba-state-focus-page__inner">
		<?php
		get_template_part( 'template-parts/components/system-state', null, array(
			'variant' => 'success',
			'icon' => 'success',
			'eyebrow' => 'Segnalazione ricevuta',
			'title' => 'Grazie, la tua segnalazione è stata inviata.',
			'message' => 'Il contenuto è stato acquisito correttamente e verrà verificato prima della pubblicazione.',
			'meta' => 'La posizione precisa e gli eventuali dati riservati non vengono mostrati pubblicamente.',
			'primary_label' => 'Torna alla home',
			'primary_url' => home_url( '/' ),
			'secondary_label' => 'Segnala altro',
			'secondary_url' => home_url( '/segnala-un-evento/' ),
		) );
		?>
	</div>
</main>
<?php get_footer(); ?>
