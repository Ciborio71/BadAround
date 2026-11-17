<?php
/** Template Name: BadAround — Blog */
get_header();
$page=max(1,(int)get_query_var('paged'),(int)get_query_var('page'));
$latest=badaround_blog_query(1);
$featured_id=$latest->have_posts()?(int)$latest->posts[0]->ID:0;
$articles=badaround_blog_query(12,$page,$featured_id?array($featured_id):array());
?>
<main id="main-content" class="ba-blog ba-blog-index">
<header class="ba-blog-hero ba-container"><p class="ba-blog-eyebrow">Informazioni utili, persone vicine</p><h1>Il blog di BadAround</h1><p>Guide, approfondimenti e novità per conoscere il territorio, condividere informazioni utili e fare rete nella tua comunità.</p></header>
<section class="ba-container ba-blog-content" aria-label="Articoli del blog">
<?php if($page===1&&$featured_id):$latest->the_post();$profile=badaround_blog_profile($featured_id); ?>
<article class="ba-blog-featured">
<a class="ba-blog-featured__image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
<?php if(has_post_thumbnail()):echo get_the_post_thumbnail($featured_id,'large',array('alt'=>'','loading'=>'eager','fetchpriority'=>'high','decoding'=>'async'));else: ?><img src="<?php echo esc_url(badaround_blog_cover($featured_id)); ?>" alt="" width="640" height="360"><?php endif; ?>
</a>
<div class="ba-blog-featured__body"><span class="ba-blog-category"><?php echo badaround_pin_svg($profile['pin']); ?><span><?php echo esc_html($profile['name']); ?></span></span>
<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
<p><?php echo esc_html(badaround_blog_excerpt($featured_id)); ?></p>
<div class="ba-blog-featured__footer"><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time><a class="ba-button" href="<?php the_permalink(); ?>">Leggi l’articolo <span aria-hidden="true">→</span></a></div></div>
</article>
<?php wp_reset_postdata();endif; ?>
<?php badaround_blog_navigation(); ?>
<?php if($articles->have_posts()): ?><div class="ba-blog-grid"><?php while($articles->have_posts()):$articles->the_post();get_template_part('template-parts/blog-card');endwhile; ?></div>
<?php if($articles->max_num_pages>1): ?><nav class="ba-blog-pagination" aria-label="Pagine del blog"><?php echo wp_kses_post(paginate_links(array('total'=>$articles->max_num_pages,'current'=>$page,'base'=>home_url('/blog/').'%_%','format'=>'page/%#%/','prev_text'=>'← Precedente','next_text'=>'Successiva →'))); ?></nav><?php endif; ?>
<?php elseif(!$featured_id): ?><div class="ba-blog-empty"><h2>Le prime storie stanno arrivando</h2><p>Qui troverai guide e approfondimenti per partecipare alla community di BadAround.</p></div><?php endif;wp_reset_postdata(); ?>
</section></main><?php get_footer(); ?>
