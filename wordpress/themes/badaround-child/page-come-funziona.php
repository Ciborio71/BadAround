<?php
/** Template Name: BadAround — Come funziona */
if(!defined('ABSPATH'))exit;
wp_enqueue_style('badaround-how-v2',get_stylesheet_directory_uri().'/assets/css/how-v2.css',array('badaround-shell'),(string)filemtime(__DIR__.'/assets/css/how-v2.css'));
get_header();
$report_url=home_url('/segnala-un-evento/');$map_url=home_url('/mappa/');$sentinel_url=home_url('/sentinelle/');
$steps=array(
array('Racconta cosa è successo','Scegli la categoria, indica luogo e dettagli utili.','M4 16l-1 5 5-1L20 8l-4-4Z M14 6l4 4'),
array('Verifichiamo la segnalazione','Il team esamina le informazioni prima della pubblicazione.','M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z M8 12l3 3 5-6'),
array('L’evento appare sulla mappa','Dopo l’approvazione, la segnalazione diventa consultabile.','M3 5l6-2 6 2 6-2v16l-6 2-6-2-6 2Z M9 3v16 M15 5v16'),
array('La community partecipa','Altre persone possono scoprire l’evento e contribuire con informazioni.','M8 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M16 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6 M2 21v-4a6 6 0 0 1 12 0v4 M14 12a6 6 0 0 1 8 5v4'),
array('Segui ciò che ti interessa','Scopri le Sentinelle per seguire zone e argomenti.','M5 17h14l-2-3V9a5 5 0 0 0-10 0v5Z M10 21h4 M12 2v2')
);
$categories = array(
	array( 'class'=>'vehicles', 'pin'=>'veicoli', 'title'=>'Veicoli', 'text'=>'Auto, moto e mezzi: furti, danneggiamenti e altre situazioni utili da condividere.' ),
	array( 'class'=>'places', 'pin'=>'case-e-attivita', 'title'=>'Case e attività', 'text'=>'Abitazioni, negozi, uffici e altri luoghi interessati da eventi o situazioni da segnalare.' ),
	array( 'class'=>'hazards', 'pin'=>'pericoli', 'title'=>'Pericoli', 'text'=>'Ostacoli, rischi stradali, ambientali o altre situazioni che richiedono attenzione.' ),
	array( 'class'=>'public', 'pin'=>'spazi-pubblici', 'title'=>'Spazi pubblici', 'text'=>'Aree comuni, vandalismi, degrado e situazioni che interessano la vita del territorio.' ),
	array( 'class'=>'animals', 'pin'=>'animali', 'title'=>'Animali', 'text'=>'Animali smarriti, avvistati o situazioni in cui una segnalazione può essere utile.' ),
	array( 'class'=>'objects', 'pin'=>'oggetti-e-documenti', 'title'=>'Oggetti', 'text'=>'Chiavi, documenti, dispositivi e altri oggetti personali smarriti o ritrovati.' ),
);

$privacy=array(
array('Posizione pubblica','Punto esatto o area approssimata, secondo la moderazione.'),
array('Dati riservati','I recapiti personali non vengono pubblicati.'),
array('Immagini verificate','I contenuti vengono controllati prima della pubblicazione.'),
array('Come comparire','Puoi scegliere la modalità di visibilità prevista.')
);
?>
<main id="main-content" class="ba-how-v2">
<section class="how-hero"><div class="how-wrap how-hero-grid"><div>
<p class="how-eyebrow">Come funziona</p><h1>Insieme rendiamo il territorio più <span>consapevole</span></h1>
<p class="how-lead">Segnala ciò che accade intorno a te, esplora la mappa e condividi informazioni utili con la community.</p>
<p class="how-free">Gratuito e aperto alla community</p>
<div class="how-actions"><a class="how-button" href="<?php echo esc_url($report_url); ?>">＋ Segnala un evento</a><a class="how-button how-button-outline" href="<?php echo esc_url($map_url); ?>">Esplora la mappa</a></div>
</div><div class="how-hero-art" role="img" aria-label="Una mappa sul telefono collega le segnalazioni del territorio"><span>✓ Segnalazione approvata</span></div></div></section>
<section class="how-process"><div class="how-wrap"><div class="how-section-head"><h2>In pochi passaggi</h2><p>Dalla segnalazione alla mappa, tutto è semplice, chiaro e pensato per la community.</p></div>
<ol class="how-steps"><?php foreach($steps as $index=>$step): ?><li class="how-step"><span class="how-number"><?php echo esc_html(sprintf('%02d',$index+1)); ?></span><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?php echo esc_attr($step[2]); ?>"/></svg><h3><?php echo esc_html($step[0]); ?></h3><p><?php echo esc_html($step[1]); ?></p></li><?php endforeach; ?></ol></div></section>
<section class="how-category-section how-wrap"><div class="how-section-head"><h2>Cosa puoi segnalare</h2><p>Eventi e situazioni che possono essere utili alla community del tuo territorio.</p></div><div class="how-categories">
<?php foreach($categories as $category): ?><a class="how-category" href="<?php echo esc_url(add_query_arg('categoria',$category['pin'],$report_url)); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg($category['pin']); ?></span><h3><?php echo esc_html($category['title']); ?></h3><p><?php echo esc_html($category['text']); ?></p></a><?php endforeach; ?>
</div></section>
<section class="how-wrap how-panels"><div class="how-sentinel"><div class="how-sentinel-art" aria-hidden="true"><?php echo badaround_pin_svg('neutral'); ?></div><div><p class="how-eyebrow">Sentinelle</p><h2>Resta vicino alla tua zona</h2><p>Scopri come seguire gli eventi e gli argomenti che ti interessano.</p><a class="how-button" href="<?php echo esc_url($sentinel_url); ?>">Scopri le Sentinelle</a></div></div>
<div class="how-privacy"><h2>Informazioni utili, dati protetti</h2><div class="how-privacy-grid"><?php foreach($privacy as $index=>$item): ?><article><span aria-hidden="true"><?php echo esc_html(array('◎','◇','▧','♙')[$index]); ?></span><div><h3><?php echo esc_html($item[0]); ?></h3><p><?php echo esc_html($item[1]); ?></p></div></article><?php endforeach; ?></div></div></section>
<aside class="how-wrap how-emergency"><strong>In caso di emergenza chiama il <a href="tel:112">112</a>.</strong> BadAround non sostituisce i servizi di emergenza.</aside>
<section class="how-final"><div class="how-wrap"><h2>Un’informazione può fare la differenza</h2><p>Partecipa alla community del tuo territorio.</p><div class="how-actions"><a class="how-button" href="<?php echo esc_url($report_url); ?>">＋ Segnala un evento</a><a class="how-button how-button-outline" href="<?php echo esc_url($map_url); ?>">Esplora la mappa</a></div></div></section>
</main><?php get_footer(); ?>
