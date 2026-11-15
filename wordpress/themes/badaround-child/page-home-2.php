<?php
/** Template Name: BadAround — Home 2 (anteprima) */
if(!defined('ABSPATH'))exit;
get_header();
$uri=get_stylesheet_directory_uri();
$categories=[
 ['Veicoli','veicoli','Auto, moto e altri mezzi: furti, danneggiamenti e situazioni da segnalare.','#1677E8'],
 ['Case e attività','case-e-attivita','Furti, tentati furti e altri eventi che riguardano abitazioni, negozi e uffici.','#C9153D'],
 ['Pericoli','pericoli','Ostacoli, rischi stradali o ambientali e situazioni che richiedono attenzione.','#F57C00'],
 ['Spazi pubblici','spazi-pubblici','Vandalismi, degrado e altre situazioni che interessano aree comuni.','#0AA184'],
 ['Animali','animali','Animali smarriti, avvistati o in difficoltà.','#7040C8'],
 ['Oggetti e documenti','oggetti-e-documenti','Chiavi, documenti, dispositivi e altri oggetti smarriti o ritrovati.','#E3A415'],
];
$recent=class_exists('BadAround_Discovery_Query')?(new BadAround_Discovery_Query())->discover(['page'=>1,'per_page'=>3]):['items'=>[]];
?>
<main class="h2-home" id="main-content">
<section class="h2-hero" aria-labelledby="h2-title">
 <div class="h2-wrap h2-hero-grid">
  <div class="h2-hero-copy"><p class="h2-eyebrow">Osserva. Segnala. Condividi. Fai rete.</p>
   <h1 id="h2-title">Cosa succede<br>intorno a te?</h1>
   <p class="h2-lead">Furti, smarrimenti, pericoli e richieste di testimoni. Segui le segnalazioni della tua zona e contribuisci a far circolare informazioni utili nella community.</p>
   <div class="h2-actions"><a class="h2-button h2-button-red" href="<?php echo esc_url(home_url('/segnala-un-evento/')); ?>"><span aria-hidden="true">+</span> Segnala un evento</a><a class="h2-button h2-button-ghost" href="<?php echo esc_url(home_url('/mappa/')); ?>"><svg class="h2-ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg> Esplora la mappa</a></div>
   <p class="h2-privacy"><svg class="h2-ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6Z"/><path d="m7 12 3 3 7-7"/></svg><span>Scegli come comparire e come mostrare la posizione. I tuoi recapiti restano riservati.</span></p>
  </div>
  <div class="h2-visual" aria-label="Esempi illustrativi di segnalazioni collegate alla community">
   <img class="h2-network" src="<?php echo esc_url($uri.'/assets/home-two-network-v3-1600.webp'); ?>" srcset="<?php echo esc_url($uri.'/assets/home-two-network-v3-900.webp'); ?> 900w, <?php echo esc_url($uri.'/assets/home-two-network-v3-1600.webp'); ?> 1600w" sizes="100vw" width="1600" height="900" alt="Quartiere illustrato: un veicolo, un'abitazione e un animale collegati alle segnalazioni sul telefono" fetchpriority="high" decoding="async">
   <a class="h2-callout h2-callout-car" href="<?php echo esc_url(add_query_arg('categoria','veicoli',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('veicoli'); ?></span><span><strong>Furto di veicolo</strong><small>Informazioni e avvistamenti utili</small></span><b aria-hidden="true">›</b><span class="h2-scene-marker" aria-hidden="true"><?php echo badaround_pin_svg('veicoli'); ?></span></a>
   <a class="h2-callout h2-callout-animal" href="<?php echo esc_url(add_query_arg('categoria','animali',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('animali'); ?></span><span><strong>Animale smarrito</strong><small>Una segnalazione può aiutare</small></span><b aria-hidden="true">›</b><span class="h2-scene-marker" aria-hidden="true"><?php echo badaround_pin_svg('animali'); ?></span></a>
   <a class="h2-callout h2-callout-house" href="<?php echo esc_url(add_query_arg('categoria','case-e-attivita',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('case-e-attivita'); ?></span><span><strong>Tentativo di effrazione</strong><small>Condividi ciò che hai osservato</small></span><b aria-hidden="true">›</b><span class="h2-scene-marker" aria-hidden="true"><?php echo badaround_pin_svg('case-e-attivita'); ?></span></a>
   <a class="h2-witness" href="<?php echo esc_url(home_url('/segnalazioni/')); ?>"><svg viewBox="0 0 48 36" aria-hidden="true"><circle cx="24" cy="8" r="6"/><circle cx="9" cy="12" r="5"/><circle cx="39" cy="12" r="5"/><path d="M14 34V24a10 10 0 0 1 20 0v10ZM0 32V24a8 8 0 0 1 12-7v15Zm36 0V17a8 8 0 0 1 12 7v8Z"/></svg><span><strong>Si cercano testimoni</strong><small>Hai visto qualcosa? Può fare la differenza.</small></span><b aria-hidden="true">›</b></a>
  </div>
 </div>
</section>
<div class="h2-wrap h2-search-wrap">
 <form class="h2-search" action="<?php echo esc_url(home_url('/mappa/')); ?>" role="search" method="get" data-h2-search>
  <label class="ba-sr-only" for="h2-area">Cerca indirizzo, comune o quartiere</label>
  <span class="h2-search-pin" aria-hidden="true"><?php echo badaround_pin_svg('neutral'); ?></span>
  <div class="h2-search-field"><input id="h2-area" type="search" name="area" placeholder="Cerca indirizzo, comune o quartiere…" autocomplete="off" required aria-describedby="h2-search-status" data-h2-input><div class="h2-suggestions" data-h2-suggestions hidden></div></div>
  <?php foreach(['place_id','lat','lng','north','south','east','west'] as $field): ?><input type="hidden" name="<?php echo esc_attr($field); ?>" value=""><?php endforeach; ?>
  <button class="h2-button h2-button-teal" type="submit">Cerca <span aria-hidden="true">→</span></button>
  <button class="h2-nearby" type="button" data-h2-nearby><span aria-hidden="true">◎</span> Vicino a me</button>
 </form>
 <p id="h2-search-status" class="h2-search-status" role="status" aria-live="polite"></p>
</div>
<section class="h2-section h2-wrap" aria-labelledby="h2-categories-title">
 <h2 id="h2-categories-title">Cosa puoi <em>segnalare?</em></h2><p class="h2-subtitle">Condividi un evento con la community di BadAround.</p>
 <nav class="h2-categories" aria-label="Categorie delle segnalazioni"><?php foreach($categories as [$label,$slug,$description,$color]): ?><a class="h2-category" style="--category:<?php echo esc_attr($color); ?>" href="<?php echo esc_url(add_query_arg('categoria',$slug,home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg($slug); ?></span><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($description); ?></small></a><?php endforeach; ?></nav>
</section>
<section class="h2-section h2-wrap" aria-labelledby="h2-recent-title">
 <div class="h2-section-head"><h2 id="h2-recent-title">Ultime <em>segnalazioni</em></h2><a href="<?php echo esc_url(home_url('/segnalazioni/')); ?>">Vedi tutte <span aria-hidden="true">→</span></a></div>
 <div class="h2-feed-intro"><p>Ultime segnalazioni pubblicate in Italia. Cerca una zona per esplorare quelle vicine a te.</p><?php get_template_part('template-parts/components/precision-legend'); ?></div>
 <div class="h2-events"><?php foreach(array_slice(($recent['items']??[]),0,3) as $item):
 $category=function_exists('badaround_child_event_category_slug')?badaround_child_event_category_slug((int)$item['id']):'';
 $meta=array_filter([$item['occurred_date']??'',$item['occurred_time']??'',$item['public_place_name']??($item['territory']['name']??'')]);
 get_template_part('template-parts/components/event-card',null,['event_id'=>(int)$item['id'],'title'=>$item['title'],'url'=>$item['permalink'],'status'=>'info','status_label'=>$item['event_type']['name']??'Segnalazione','meta'=>implode(' · ',$meta),'image'=>badaround_child_event_image_url((int)$item['id'],'thumb'),'image_alt'=>$item['title'],'excerpt'=>$item['excerpt']??'','link_label'=>'Vai alla segnalazione','category'=>$category]);
 endforeach; ?><?php if(empty($recent['items'])): ?><div class="h2-empty"><h3>Le prossime segnalazioni compariranno qui</h3><p>Gli eventi diventano visibili dopo la moderazione e la pubblicazione.</p><a href="<?php echo esc_url(home_url('/segnala-un-evento/')); ?>">Condividi una segnalazione →</a></div><?php endif; ?></div>
</section>
<section class="h2-process"><div class="h2-wrap"><h2>Un’informazione può fare la differenza</h2><ol><li><span><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 22 16-16 5 5-16 16-7 1Z"/><path d="m19 9 5 5"/></svg></span><div><h3>1. Segnala</h3><p>Racconta l’evento che hai osservato, indicando luogo e dettagli utili.</p></div></li><li><span><svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="7" cy="16" r="4"/><circle cx="25" cy="6" r="4"/><circle cx="25" cy="26" r="4"/><path d="m11 14 10-6M11 18l10 6"/></svg></span><div><h3>2. Condividi</h3><p>Dopo la moderazione, la segnalazione può essere pubblicata per la community.</p></div></li><li><span><svg viewBox="0 0 48 36" fill="currentColor" aria-hidden="true"><circle cx="24" cy="8" r="6"/><circle cx="9" cy="12" r="5"/><circle cx="39" cy="12" r="5"/><path d="M14 34V24a10 10 0 0 1 20 0v10ZM0 32V24a8 8 0 0 1 12-7v15Zm36 0V17a8 8 0 0 1 12 7v8Z"/></svg></span><div><h3>3. Fai rete</h3><p>Contribuisci con informazioni, avvistamenti e testimonianze pertinenti.</p></div></li></ol><a href="<?php echo esc_url(home_url('/come-funziona/')); ?>">Scopri come funziona BadAround →</a></div></section>
<section class="h2-section h2-wrap"><div class="h2-sentinel"><div><p class="h2-eyebrow">Il tuo senso civico può fare la differenza</p><h2>Diventa una <em>Sentinella</em></h2><p>Segui la tua zona e contribuisci attivamente alla community di BadAround.</p><a class="h2-button h2-button-red" href="<?php echo esc_url(home_url('/sentinelle/')); ?>">Diventa una Sentinella <span aria-hidden="true">→</span></a></div><figure class="h2-sentinel-art" aria-hidden="true"><img src="<?php echo esc_url($uri.'/assets/home-two-network-v3-900.webp'); ?>" width="900" height="506" loading="lazy" decoding="async" alt=""><svg class="h2-sentinel-bell" viewBox="0 0 80 100" fill="none"><path d="M40 96S4 59 4 39a36 36 0 1 1 72 0c0 20-36 57-36 57Z" fill="#16bcc4" stroke="white" stroke-width="4"/><circle cx="40" cy="39" r="27" fill="#129ba5" stroke="white" stroke-width="3"/><path d="M27 49h26l-4-6V32a9 9 0 0 0-18 0v11Z" fill="white"/><path d="M35 53a5 5 0 0 0 10 0M40 20v4" stroke="white" stroke-width="3" stroke-linecap="round"/></svg></figure></div></section>
<div class="h2-section h2-wrap h2-blog"><?php badaround_blog_home(); ?></div>
</main>
<?php get_footer(); ?>
