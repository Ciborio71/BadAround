<?php
/** Native editorial posts; entirely separate from reports and event publication. */
if(!defined('ABSPATH'))exit;
function badaround_blog_sections(){
 return array('il-progetto-badaround'=>array('Il progetto BadAround','neutral','#087f8c'),'veicoli'=>array('Veicoli','veicoli','#1677E8'),'case-e-attivita'=>array('Case e attività','case-e-attivita','#C9153D'),'pericoli'=>array('Pericoli','pericoli','#F57C00'),'spazi-pubblici'=>array('Spazi pubblici','spazi-pubblici','#0AA184'),'animali'=>array('Animali','animali','#7040C8'),'oggetti-e-documenti'=>array('Oggetti e documenti','oggetti-e-documenti','#E3A415'));
}
function badaround_blog_profile($id){
 $sections=badaround_blog_sections();$terms=get_the_category($id);
 foreach((array)$terms as $term)if(isset($sections[$term->slug]))return array('slug'=>$term->slug,'name'=>$sections[$term->slug][0],'pin'=>$sections[$term->slug][1],'color'=>$sections[$term->slug][2],'term'=>$term);
 return array('slug'=>'il-progetto-badaround','name'=>'Il progetto BadAround','pin'=>'neutral','color'=>'#087f8c','term'=>null);
}
function badaround_blog_query($limit=3,$page=1){
 return new WP_Query(array('post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,'paged'=>max(1,(int)$page),'ignore_sticky_posts'=>true,'orderby'=>array('date'=>'DESC','ID'=>'DESC'),'tax_query'=>array(array('taxonomy'=>'category','field'=>'slug','terms'=>array_keys(badaround_blog_sections())))));
}
function badaround_blog_excerpt($id){
 $value=get_post_field('post_excerpt',$id);if(!$value)$value=get_post_field('post_content',$id);
 return wp_trim_words(trim(wp_strip_all_tags(strip_shortcodes($value))),28,'…');
}
function badaround_blog_cover($id){
 $profile=badaround_blog_profile($id);
 return $profile['pin']==='neutral'?get_stylesheet_directory_uri().'/assets/blog-cover-project.svg':badaround_child_placeholder_url($profile['pin']);
}
function badaround_blog_navigation(){
 echo '<nav class="ba-blog-topics" aria-label="Argomenti del blog"><a href="'.esc_url(home_url('/blog/')).'"'.(is_page('blog')?' aria-current="page"':'').'>Tutti gli articoli</a>';
 foreach(badaround_blog_sections() as $slug=>$section){$term=get_term_by('slug',$slug,'category');if(!$term)continue;$url=get_category_link($term->term_id);if(is_wp_error($url))continue;
 echo '<a class="ba-blog-topic ba-blog-topic--'.esc_attr($slug).'" href="'.esc_url($url).'"'.(is_category($term->term_id)?' aria-current="page"':'').'>'.badaround_pin_svg($section[1]).'<span>'.esc_html($section[0]).'</span></a>';}
 echo '</nav>';
}
function badaround_blog_home(){
 $articles=badaround_blog_query(3);if(!$articles->have_posts())return;
 echo '<section class="ba-blog ba-blog-home" aria-labelledby="ba-blog-home-title"><div class="ba-blog-section-head"><div><h2 id="ba-blog-home-title" class="ba-home-display-heading"><span>Il blog di </span><span class="ba-home-display-heading__accent">BadAround</span></h2><p class="ba-blog-home__intro">Informarsi, condividere, fare rete.</p></div><a class="ba-button ba-button--outline" href="'.esc_url(home_url('/blog/')).'">Tutti gli articoli <span aria-hidden="true">→</span></a></div><div class="ba-blog-grid">';
 while($articles->have_posts()){$articles->the_post();get_template_part('template-parts/blog-card',null,array('heading'=>'h3'));}wp_reset_postdata();echo '</div></section>';
}
add_action('init',static function(){
 add_rewrite_rule('^blog/page/([0-9]+)/?$','index.php?pagename=blog&paged=$matches[1]','top');
 add_rewrite_rule('^blog/([^/]+)/?$','index.php?post_type=post&name=$matches[1]','top');
});
add_filter('post_link',static function($url,$post){
 if($post->post_type!=='post')return $url;
 foreach((array)get_the_category($post->ID) as $term)if(isset(badaround_blog_sections()[$term->slug]))return home_url(user_trailingslashit('blog/'.$post->post_name));
 return $url;
},10,2);
add_action('wp_enqueue_scripts',static function(){
 if(is_front_page()||is_page('blog')||is_singular('post')||is_category())wp_enqueue_style('badaround-blog',get_stylesheet_directory_uri().'/assets/css/blog.css',array('badaround-components'),(string)filemtime(dirname(__DIR__).'/assets/css/blog.css'));
},25);
add_filter('pre_get_document_title',static function($title){
 if(is_page('blog'))return 'Blog BadAround: guide, territorio e community';
 if(is_category())return single_cat_title('',false).' — Blog BadAround';
 if(is_singular('post'))return get_the_title(get_queried_object_id()).' | BadAround';return $title;
},25);
add_filter('wp_robots',static function($robots){
 if(is_category()&&(int)get_queried_object()->count===0){$robots['noindex']=true;unset($robots['index']);}
 if(is_page('blog')){$q=badaround_blog_query(1);if(!$q->have_posts()){$robots['noindex']=true;unset($robots['index']);}}
 return $robots;
});
add_action('wp_head',static function(){
 if(!(is_page('blog')||is_singular('post')||is_category())||defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION'))return;
 $id=get_queried_object_id();$article=is_singular('post');
 $title=$article?get_the_title($id):(is_category()?single_cat_title('',false):'Il blog di BadAround');
 $description=$article?badaround_blog_excerpt($id):(is_category()?wp_strip_all_tags(category_description($id)):'Guide, approfondimenti e novità di BadAround: informazioni utili su segnalazioni locali, territorio e collaborazione tra cittadini.');
 if(!$description)$description='Guide e approfondimenti su '.single_cat_title('',false).' dal blog di BadAround.';
 $url=$article?get_permalink($id):(is_category()?get_category_link($id):get_permalink($id));
 if(!$article&&get_query_var('paged')>1)$url=get_pagenum_link(get_query_var('paged'));
 $description=wp_html_excerpt(trim(preg_replace('/\s+/u',' ',$description)),170,'…');
 echo '<meta name="description" content="'.esc_attr($description).'">'."\n";
 foreach(array('og:type'=>$article?'article':'website','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:site_name'=>'BadAround') as $key=>$value)echo '<meta property="'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n";
 echo '<meta name="twitter:card" content="summary"><meta name="twitter:title" content="'.esc_attr($title).'"><meta name="twitter:description" content="'.esc_attr($description).'">'."\n";
 if(is_category())echo '<link rel="canonical" href="'.esc_url($url).'">'."\n";
 $data=array('@context'=>'https://schema.org','@type'=>$article?'BlogPosting':'CollectionPage','@id'=>$url.'#'.($article?'article':'collection'),'url'=>$url,'name'=>$title,'description'=>$description,'inLanguage'=>'it-IT','publisher'=>array('@type'=>'Organization','name'=>'BadAround','url'=>home_url('/')));
 if($article){$data['headline']=$title;$data['datePublished']=get_post_time(DATE_W3C,true,$id);$data['dateModified']=get_post_modified_time(DATE_W3C,true,$id);$data['author']=array('@type'=>'Organization','name'=>'BadAround');$data['articleSection']=badaround_blog_profile($id)['name'];$data['mainEntityOfPage']=$url;$image=get_the_post_thumbnail_url($id,'full');if($image){$data['image']=$image;echo '<meta property="og:image" content="'.esc_url($image).'">'."\n";}}
 echo '<script type="application/ld+json">'.wp_json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script>'."\n";
},4);

add_filter('get_canonical_url',static function($url,$post){if(is_page('blog')&&get_query_var('paged')>1)return get_pagenum_link(get_query_var('paged'));return $url;},10,2);
