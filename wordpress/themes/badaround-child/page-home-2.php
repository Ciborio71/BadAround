<?php
/** Template Name: BadAround — Home 2 (anteprima) */
if(!defined('ABSPATH'))exit;
get_header();
$uri=get_stylesheet_directory_uri();
$categories=[
 ['Veicoli','veicoli','Furti, danni e altri eventi che riguardano auto, moto e mezzi.','#1677E8'],
 ['Case e attività','case-e-attivita','Effrazioni, furti ed eventi in abitazioni, negozi e uffici.','#C9153D'],
 ['Pericoli','pericoli','Ostacoli e situazioni di rischio da segnalare.','#F57C00'],
 ['Spazi pubblici','spazi-pubblici','Degrado, vandalismi e problemi nelle aree comuni.','#0AA184'],
 ['Animali','animali','Animali smarriti, avvistati o in difficoltà.','#7040C8'],
 ['Oggetti e documenti','oggetti-e-documenti','Oggetti smarriti, sottratti o ritrovati.','#E3A415'],
];
$recent=class_exists('BadAround_Discovery_Query')?(new BadAround_Discovery_Query())->discover(['page'=>1,'per_page'=>6]):['items'=>[]];
?>
<main class="h2-home" id="main-content">
<section class="h2-hero" aria-labelledby="h2-title">
 <div class="h2-wrap h2-hero-grid">
  <div class="h2-hero-copy"><p class="h2-eyebrow">Osserva. Segnala. Condividi. Fai rete.</p>
   <h1 id="h2-title">Cosa succede<br>intorno a te?</h1>
   <p class="h2-lead">Furti, smarrimenti, pericoli e richieste di testimoni. Segui le segnalazioni della tua zona e contribuisci a far circolare informazioni utili nella community.</p>
   <div class="h2-actions"><a class="h2-button h2-button-red" href="<?php echo esc_url(home_url('/segnala-un-evento/')); ?>"><span aria-hidden="true">＋</span> Segnala un evento</a><a class="h2-button h2-button-ghost" href="<?php echo esc_url(home_url('/mappa/')); ?>">Esplora la mappa <span aria-hidden="true">→</span></a></div>
   <p class="h2-privacy">Scegli come comparire e come mostrare la posizione. I tuoi recapiti restano riservati.</p>
  </div>
  <div class="h2-visual" aria-label="Esempi illustrativi di segnalazioni collegate alla community">
   <img class="h2-network" src="<?php echo esc_url($uri.'/assets/home-two-network-v2-1200.webp'); ?>" srcset="<?php echo esc_url($uri.'/assets/home-two-network-v2-720.webp'); ?> 720w, <?php echo esc_url($uri.'/assets/home-two-network-v2-1200.webp'); ?> 1200w" sizes="(max-width:760px) 100vw, 65vw" width="1200" height="800" alt="Quartiere illustrato: un veicolo, un'abitazione e un animale collegati alle segnalazioni sul telefono" fetchpriority="high" decoding="async">
   <a class="h2-callout h2-callout-car" href="<?php echo esc_url(add_query_arg('categoria','veicoli',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('veicoli'); ?></span><span><strong>Furto di veicolo</strong><small>Informazioni e avvistamenti utili</small></span><b aria-hidden="true">›</b></a>
   <a class="h2-callout h2-callout-animal" href="<?php echo esc_url(add_query_arg('categoria','animali',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('animali'); ?></span><span><strong>Animale smarrito</strong><small>Una segnalazione può aiutare</small></span><b aria-hidden="true">›</b></a>
   <a class="h2-callout h2-callout-house" href="<?php echo esc_url(add_query_arg('categoria','case-e-attivita',home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg('case-e-attivita'); ?></span><span><strong>Tentativo di effrazione</strong><small>Condividi ciò che hai osservato</small></span><b aria-hidden="true">›</b></a>
   <a class="h2-witness" href="<?php echo esc_url(home_url('/segnalazioni/')); ?>"><svg viewBox="0 0 48 36" aria-hidden="true"><circle cx="24" cy="8" r="6"/><circle cx="9" cy="12" r="5"/><circle cx="39" cy="12" r="5"/><path d="M14 34V24a10 10 0 0 1 20 0v10ZM0 32V24a8 8 0 0 1 12-7v15Zm36 0V17a8 8 0 0 1 12 7v8Z"/></svg><span><strong>Si cercano testimoni</strong><small>Hai visto qualcosa? Può fare la differenza.</small></span><b aria-hidden="true">›</b></a>
   <span class="h2-art-label">Situazioni illustrate a titolo di esempio</span>
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
 <h2 id="h2-categories-title">Cosa puoi <em>segnalare?</em></h2><p class="h2-subtitle">Condividi un evento con la community di BadAround: ogni segnalazione può essere utile.</p>
 <nav class="h2-categories" aria-label="Categorie delle segnalazioni"><?php foreach($categories as [$label,$slug,$description,$color]): ?><a class="h2-category" style="--category:<?php echo esc_attr($color); ?>" href="<?php echo esc_url(add_query_arg('categoria',$slug,home_url('/segnalazioni/'))); ?>"><span aria-hidden="true"><?php echo badaround_pin_svg($slug); ?></span><strong><?php echo esc_html($label); ?></strong><small><?php echo esc_html($description); ?></small></a><?php endforeach; ?></nav>
</section>
<section class="h2-section h2-wrap" aria-labelledby="h2-recent-title">
 <div class="h2-section-head"><h2 id="h2-recent-title">Ultime <em>segnalazioni</em></h2><a href="<?php echo esc_url(home_url('/segnalazioni/')); ?>">Vedi tutte <span aria-hidden="true">→</span></a></div>
 <div class="h2-feed-intro"><p>Ultime segnalazioni pubblicate in Italia. Cerca una zona per esplorare quelle vicine a te.</p><?php get_template_part('template-parts/components/precision-legend'); ?></div>
 <div class="h2-events"><?php foreach(($recent['items']??[]) as $item):
 $category=function_exists('badaround_child_event_category_slug')?badaround_child_event_category_slug((int)$item['id']):'';
 $meta=array_filter([$item['occurred_date']??'',$item['occurred_time']??'',$item['public_place_name']??($item['territory']['name']??'')]);
 get_template_part('template-parts/components/event-card',null,['event_id'=>(int)$item['id'],'title'=>$item['title'],'url'=>$item['permalink'],'status'=>'info','status_label'=>$item['event_type']['name']??'Segnalazione','meta'=>implode(' · ',$meta),'image'=>badaround_child_event_image_url((int)$item['id'],'thumb'),'image_alt'=>$item['title'],'excerpt'=>$item['excerpt']??'','link_label'=>'Vai alla segnalazione','category'=>$category]);
 endforeach; ?><?php if(empty($recent['items'])): ?><div class="h2-empty"><h3>Le prossime segnalazioni compariranno qui</h3><p>Gli eventi diventano visibili dopo la moderazione e la pubblicazione.</p><a href="<?php echo esc_url(home_url('/segnala-un-evento/')); ?>">Condividi una segnalazione →</a></div><?php endif; ?></div>
</section>
<section class="h2-process"><div class="h2-wrap"><h2>Un’informazione può fare la differenza</h2><ol><li><span>1</span><div><h3>Segnala</h3><p>Racconta l’evento che hai osservato, indicando luogo e dettagli utili.</p></div></li><li><span>2</span><div><h3>Condividi</h3><p>Dopo la moderazione, la segnalazione può essere pubblicata per la community.</p></div></li><li><span>3</span><div><h3>Fai rete</h3><p>Contribuisci con informazioni, avvistamenti e testimonianze pertinenti.</p></div></li></ol><a href="<?php echo esc_url(home_url('/come-funziona/')); ?>">Scopri come funziona BadAround →</a></div></section>
<section class="h2-section h2-wrap"><div class="h2-sentinel"><div><p class="h2-eyebrow">Il tuo senso civico può fare la differenza</p><h2>Diventa una <em>Sentinella</em></h2><p>Segui la tua zona e contribuisci attivamente alla community di BadAround.</p><a class="h2-button h2-button-red" href="<?php echo esc_url(home_url('/sentinelle/')); ?>">Diventa una Sentinella <span aria-hidden="true">→</span></a></div><img src="<?php echo esc_url($uri.'/assets/home-two-network-v2-720.webp'); ?>" width="720" height="480" loading="lazy" decoding="async" alt=""></div></section>
<div class="h2-section h2-wrap h2-blog"><?php badaround_blog_home(); ?></div>
</main>
<?php get_footer(); ?>
