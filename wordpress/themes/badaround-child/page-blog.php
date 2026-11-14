<?php
/** Template Name: BadAround — Blog */
get_header();$page=max(1,(int)get_query_var('paged'),(int)get_query_var('page'));$articles=badaround_blog_query(9,$page);
?>
<main id="main-content" class="ba-blog ba-blog-index"><header class="ba-blog-hero ba-container"><p class="ba-blog-eyebrow">Informazioni utili, persone vicine</p><h1>Il blog di BadAround</h1><p>Guide, approfondimenti e novità per conoscere il territorio, condividere informazioni utili e fare rete nella tua comunità.</p></header>
<section class="ba-container ba-blog-content" aria-label="Articoli del blog"><?php badaround_blog_navigation(); ?>
<?php if($articles->have_posts()): ?><div class="ba-blog-grid"><?php while($articles->have_posts()):$articles->the_post();get_template_part('template-parts/blog-card');endwhile; ?></div>
<nav class="ba-blog-pagination" aria-label="Pagine del blog"><?php echo wp_kses_post(paginate_links(array('total'=>$articles->max_num_pages,'current'=>$page,'base'=>home_url('/blog/').'%_%','format'=>'page/%#%/'))); ?></nav>
<?php else: ?><div class="ba-blog-empty"><h2>Le prime storie stanno arrivando</h2><p>Qui troverai guide e approfondimenti per partecipare alla community di BadAround. Nel frattempo, scopri come funziona la piattaforma.</p><a class="ba-button" href="<?php echo esc_url(home_url('/come-funziona/')); ?>">Scopri BadAround</a></div><?php endif;wp_reset_postdata(); ?>
</section></main><?php get_footer(); ?>
