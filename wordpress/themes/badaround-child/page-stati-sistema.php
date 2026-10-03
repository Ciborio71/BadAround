<?php
/**
 * Template Name: BadAround — Stati di sistema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$home = home_url( '/' );
$map  = home_url( '/mappa/' );
$report = home_url( '/segnala-un-evento/' );
?>
<main class="ba-states-page" id="main-content">
	<section class="ba-states-hero">
		<div class="ba-container">
			<span class="ba-eyebrow">UI states</span>
			<h1>Stati di sistema BadAround</h1>
			<p>Riferimento visuale per conferme, moderazione, errori, contenuti non disponibili e stati di caricamento.</p>
		</div>
	</section>

	<section class="ba-states-grid-section">
		<div class="ba-container ba-states-grid">
			<?php
			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'success',
				'icon' => 'success',
				'eyebrow' => 'Segnalazione ricevuta',
				'title' => 'Grazie, la tua segnalazione è stata inviata.',
				'message' => 'Il contenuto è stato acquisito correttamente e verrà sottoposto ai controlli previsti prima della pubblicazione.',
				'meta' => 'Non pubblicheremo automaticamente dati sensibili o posizione precisa.',
				'primary_label' => 'Torna alla home',
				'primary_url' => $home,
				'secondary_label' => 'Segnala altro',
				'secondary_url' => $report,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'pending',
				'icon' => 'pending',
				'eyebrow' => 'In moderazione',
				'title' => 'Stiamo verificando la segnalazione.',
				'message' => 'La segnalazione non è ancora visibile pubblicamente. Potrebbe essere modificata o sanitizzata prima della pubblicazione.',
				'meta' => 'Stato: Da moderare',
				'primary_label' => 'Vai alla mappa',
				'primary_url' => $map,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'success',
				'icon' => 'success',
				'eyebrow' => 'Pubblicata',
				'title' => 'La segnalazione è ora visibile.',
				'message' => 'Il contenuto ha superato la moderazione ed è disponibile nella relativa area territoriale.',
				'meta' => 'Stato: Pubblicato',
				'primary_label' => 'Esplora la zona',
				'primary_url' => $map,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'resolved',
				'icon' => 'success',
				'eyebrow' => 'Risolta',
				'title' => 'Questa segnalazione risulta risolta.',
				'message' => 'Resta consultabile come informazione storica, ma non viene più considerata un evento attivo.',
				'meta' => 'Stato: Risolto',
				'primary_label' => 'Vedi altre segnalazioni',
				'primary_url' => $map,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'warning',
				'icon' => 'warning',
				'eyebrow' => 'Contenuto non disponibile',
				'title' => 'Questa segnalazione non è più pubblica.',
				'message' => 'Può essere stata ritirata, archiviata o rimossa in seguito a moderazione.',
				'meta' => 'La ragione dettagliata può non essere pubblicamente disponibile.',
				'primary_label' => 'Torna alla mappa',
				'primary_url' => $map,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'error',
				'icon' => 'error',
				'eyebrow' => 'Errore',
				'title' => 'Qualcosa non ha funzionato.',
				'message' => 'Non siamo riusciti a completare l’operazione. Puoi riprovare senza perdere i dati inseriti, quando disponibili.',
				'primary_label' => 'Riprova',
				'primary_url' => '#',
				'secondary_label' => 'Torna alla home',
				'secondary_url' => $home,
			) );

			get_template_part( 'template-parts/components/system-state', null, array(
				'variant' => 'empty',
				'icon' => 'empty',
				'eyebrow' => 'Nessun risultato',
				'title' => 'Qui non ci sono ancora segnalazioni.',
				'message' => 'Prova a modificare zona o filtri, attiva una Sentinella oppure contribuisci con una nuova segnalazione.',
				'primary_label' => 'Segnala un evento',
				'primary_url' => $report,
				'secondary_label' => 'Esplora la mappa',
				'secondary_url' => $map,
			) );
			?>

			<section class="ba-system-state ba-system-state--loading" aria-label="Stato di caricamento">
				<div class="ba-system-state__loading-icon" aria-hidden="true"></div>
				<div class="ba-system-state__content">
					<span class="ba-system-state__eyebrow">Caricamento</span>
					<div class="ba-system-skeleton ba-system-skeleton--title"></div>
					<div class="ba-system-skeleton ba-system-skeleton--text"></div>
					<div class="ba-system-skeleton ba-system-skeleton--text ba-system-skeleton--short"></div>
					<div class="ba-system-skeleton ba-system-skeleton--button"></div>
				</div>
			</section>
		</div>
	</section>
</main>
<?php get_footer(); ?>
