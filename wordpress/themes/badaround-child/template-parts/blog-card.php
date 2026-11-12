<?php
if(!defined('ABSPATH'))exit;
$id=get_the_ID();$profile=badaround_blog_profile($id);$level=($args['heading']??'h2')==='h3'?'h3':'h2';
?>
<article class="ba-blog-card ba-blog-tone--<?php echo esc_attr($profile['slug']); ?>">
 <a class="ba-blog-card__image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
 <?php if(has_post_thumbnail()):echo get_the_post_thumbnail($id,'ba-map-card',array('alt'=>'','loading'=>'lazy','decoding'=>'async'));else: ?><img src="<?php echo esc_url(badaround_blog_cover($id)); ?>" alt="" loading="lazy" decoding="async" width="640" height="360"><?php endif; ?>
 </a>
 <div class="ba-blog-card__body"><span class="ba-blog-category"><?php echo badaround_pin_svg($profile['pin']); ?><span><?php echo esc_html($profile['name']); ?></span></span>
 <<?php echo $level; ?> class="ba-blog-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></<?php echo $level; ?>>
 <p class="ba-blog-card__excerpt"><?php echo esc_html(badaround_blog_excerpt($id)); ?></p>
 <div class="ba-blog-card__footer"><time datetime="<?php echo esc_attr(get_the_date(DATE_W3C)); ?>"><?php echo esc_html(get_the_date('j F Y')); ?></time><a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr('Leggi: '.get_the_title()); ?>">Leggi l’articolo <span aria-hidden="true">→</span></a></div></div>
</article>
